<?php
session_start();

/* ================== ERROR REPORTING ================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ================== DB CONNECTION ================== */
require_once "./config/database.php";

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    die("Database connection failed");
}

/* ================== STATUS LABELS ================== */
$statusLabels = [
    'active' => 'Active',
    'in_use' => 'In Use',
    'in_storage' => 'In Storage',
    'repairing' => 'Repairing',
    'faulty' => 'Faulty',
    'retired' => 'Retired'
];

$conditionLabels = [
    'New' => 'New',
    'Good' => 'Good',
    'Fair' => 'Fair',
    'Faulty' => 'Faulty'
];

/* ================== FETCH TOP DEVICES AND USERS ================== */

// Top 5 most assigned devices
$topDevicesQuery = mysqli_query($conn, "
    SELECT 
        i.id,
        i.asset_tag,
        i.device_type,
        i.model,
        i.status,
        b.brand_name,
        c.category_name,
        COUNT(DISTINCT dua.id) as assignment_count,
        COUNT(DISTINCT dua.user_id) as user_count,
        DATEDIFF(NOW(), MIN(dua.assigned_at)) as days_in_service
    FROM inventory_items i
    LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN categories c ON i.category_id = c.id
    GROUP BY i.id
    HAVING assignment_count > 0
    ORDER BY assignment_count DESC, user_count DESC
    LIMIT 5
");

$topDevices = [];
if ($topDevicesQuery) {
    while ($row = mysqli_fetch_assoc($topDevicesQuery)) {
        $topDevices[] = $row;
    }
}

// Top 5 users with most assignments - FIXED: Removed department join since department_id doesn't exist in users table
$topUsersQuery = mysqli_query($conn, "
    SELECT 
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        u.role,
        u.status as user_status,
        COUNT(DISTINCT dua.id) as assignment_count,
        COUNT(DISTINCT dua.inventory_id) as device_count,
        AVG(TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW()))) as avg_days_per_assignment,
        SUM(TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW()))) as total_days_assigned
    FROM users u
    LEFT JOIN device_user_assignments dua ON u.id = dua.user_id
    GROUP BY u.id
    HAVING assignment_count > 0
    ORDER BY assignment_count DESC, total_days_assigned DESC
    LIMIT 5
");

$topUsers = [];
if ($topUsersQuery) {
    while ($row = mysqli_fetch_assoc($topUsersQuery)) {
        $topUsers[] = $row;
    }
}

/* ================== FETCH DEVICE DETAILS FOR MODAL ================== */
$device_details = null;
$assignment_history = [];

if (isset($_GET['get_device_details']) && is_numeric($_GET['get_device_details'])) {
    $device_id = (int) $_GET['get_device_details'];

    $device_query = mysqli_prepare($conn, "
        SELECT 
            i.id,
            i.asset_tag,
            i.device_type,
            i.model,
            i.serial_number,
            i.status,
            i.condition,
            i.brand_id,
            i.category_id,
            i.department_id,
            i.remarks,
            i.created_at,
            i.updated_at,
            b.brand_name,
            c.category_name,
            d.department_name,
            (SELECT COUNT(*) FROM device_user_assignments WHERE inventory_id = i.id) as total_assignments,
            (SELECT COUNT(DISTINCT user_id) FROM device_user_assignments WHERE inventory_id = i.id) as unique_users
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN departments d ON i.department_id = d.id
        WHERE i.id = ?
    ");

    if (!$device_query) {
        if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database query preparation failed: ' . mysqli_error($conn)]);
            exit;
        }
    }

    mysqli_stmt_bind_param($device_query, "i", $device_id);
    mysqli_stmt_execute($device_query);
    $device_result = mysqli_stmt_get_result($device_query);
    $device_details = mysqli_fetch_assoc($device_result);
    mysqli_stmt_close($device_query);

    if ($device_details) {
        $history_query = mysqli_prepare($conn, "
            SELECT 
                dua.*,
                u.firstname,
                u.lastname,
                u.email,
                u.role,
                TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW())) as days_assigned
            FROM device_user_assignments dua
            JOIN users u ON dua.user_id = u.id
            WHERE dua.inventory_id = ?
            ORDER BY dua.assigned_at DESC
        ");

        if ($history_query) {
            mysqli_stmt_bind_param($history_query, "i", $device_id);
            mysqli_stmt_execute($history_query);
            $history_result = mysqli_stmt_get_result($history_query);

            while ($row = mysqli_fetch_assoc($history_result)) {
                $assignment_history[] = $row;
            }
            mysqli_stmt_close($history_query);
        }
    }

    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');

        $response = [
            'success' => true,
            'device' => $device_details,
            'history' => $assignment_history
        ];

        if ($device_details === null) {
            $response['success'] = false;
            $response['error'] = 'Device not found';
        }

        echo json_encode($response);
        exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

/* ================== FETCH ALL DEVICES WITH HISTORY ================== */
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$category_filter = isset($_GET['category']) ? (int) $_GET['category'] : 0;

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(i.asset_tag LIKE ? OR i.device_type LIKE ? OR i.model LIKE ? OR b.brand_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($category_filter)) {
    $where_conditions[] = "i.category_id = ?";
    $params[] = $category_filter;
    $param_types .= 'i';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

/* ================== PAGINATION ================== */
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 25, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

$count_query = mysqli_prepare($conn, "
    SELECT COUNT(DISTINCT i.id) as total
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    $where_sql
");

if (!empty($params)) {
    mysqli_stmt_bind_param($count_query, $param_types, ...$params);
}

mysqli_stmt_execute($count_query);
$count_result = mysqli_stmt_get_result($count_query);
$total_devices = mysqli_fetch_assoc($count_result)['total'] ?? 0;
mysqli_stmt_close($count_query);

$total_pages = ceil($total_devices / $limit);

/* ================== FETCH DEVICES WITH ASSIGNMENT COUNTS ================== */
$devices_query = mysqli_prepare($conn, "
    SELECT 
        i.id,
        i.asset_tag,
        i.device_type,
        i.model,
        i.serial_number,
        i.status,
        i.condition,
        i.created_at,
        b.brand_name,
        c.category_name,
        COUNT(DISTINCT dua.user_id) as total_users,
        COUNT(DISTINCT dua.id) as total_assignments,
        MAX(dua.assigned_at) as last_assigned,
        MIN(dua.assigned_at) as first_assigned
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
    $where_sql
    GROUP BY i.id
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
");

$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    mysqli_stmt_bind_param($devices_query, $param_types, ...$params);
}

mysqli_stmt_execute($devices_query);
$devices_result = mysqli_stmt_get_result($devices_query);
$devices = [];

while ($row = mysqli_fetch_assoc($devices_result)) {
    $devices[] = $row;
}
mysqli_stmt_close($devices_query);

/* ================== FETCH DROPDOWN DATA ================== */
$categories = [];
$categories_result = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name");
if ($categories_result) {
    $categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
}

$statuses = [];
$status_result = mysqli_query($conn, "SELECT DISTINCT status FROM inventory_items ORDER BY status");
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        $statuses[] = $row['status'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Device Assignment History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid;
            white-space: nowrap;
        }

        .history-card {
            transition: all 0.2s ease;
            border-left: 4px solid #3b82f6;
        }

        .history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background-color: #e5e7eb;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .table-row-hover:hover {
            background-color: #f9fafb;
        }

        .search-glow:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }

        .modal-content {
            max-height: 90vh;
            animation: modalSlideIn 0.3s ease-out;
            z-index: 9999;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .loading-spinner {
            display: none;
        }

        .loading-spinner.active {
            display: block;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }

        .toast {
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(100%);
            opacity: 0;
            animation: slideIn 0.3s forwards, slideOut 0.3s forwards 4s;
        }

        .toast-success {
            background: #10b981;
            color: white;
            border-left: 4px solid #059669;
        }

        .toast-error {
            background: #ef4444;
            color: white;
            border-left: 4px solid #dc2626;
        }

        .toast-warning {
            background: #f59e0b;
            color: white;
            border-left: 4px solid #d97706;
        }

        .toast-info {
            background: #3b82f6;
            color: white;
            border-left: 4px solid #1d4ed8;
        }

        @keyframes slideIn {
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .confirmation-modal {
            position: fixed;
            inset: 0;
            z-index: 10001;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .confirmation-backdrop {
            position: absolute;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .confirmation-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            z-index: 1;
            animation: modalSlideIn 0.3s ease-out;
        }

        .confirmation-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .confirmation-body {
            padding: 24px;
        }

        .confirmation-footer {
            padding: 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .assignment-card {
            transition: all 0.2s ease;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .assignment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .rank-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            color: white;
            margin-right: 12px;
        }

        .rank-1 {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: #7c2d12;
        }

        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%);
            color: #374151;
        }

        .rank-3 {
            background: linear-gradient(135deg, #CD7F32 0%, #B87333 100%);
            color: #7c2d12;
        }

        .rank-other {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }

        .top-list-item {
            transition: all 0.2s ease;
            padding: 16px;
            border-radius: 8px;
            background: white;
            border: 1px solid #e5e7eb;
        }

        .top-list-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }

        .device-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .user-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="confirmation-modal hidden">
        <div class="confirmation-backdrop"></div>
        <div class="confirmation-content">
            <div class="confirmation-header">
                <h3 class="text-lg font-semibold text-gray-800" id="confirmationTitle">Confirm Action</h3>
            </div>
            <div class="confirmation-body">
                <p class="text-gray-600" id="confirmationMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="confirmation-footer">
                <button onclick="closeConfirmation()"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button onclick="confirmAction()" id="confirmButton"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <div class="flex">
        <?php include "sidebar.php"; ?>
        <main id="mainContent" class="w-full p-6">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Device Assignment History</h1>
                    <p class="text-gray-500">Track all device assignments and user history</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="showExportConfirmation()"
                        class="bg-gradient-to-r from-green-50 to-emerald-50 text-green-700 px-4 py-2 text-sm rounded-lg hover:bg-green-700">
                        <i class="fas fa-download text-xs mr-1"></i> Export
                    </button>
                </div>
            </div>

            <!-- Statistics Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <?php
                $stats_query = mysqli_query($conn, "
                    SELECT 
                        COUNT(DISTINCT i.id) as total_devices,
                        COUNT(DISTINCT dua.user_id) as total_users,
                        COUNT(dua.id) as total_assignments,
                        AVG(TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW()))) as avg_assignment_days
                    FROM inventory_items i
                    LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
                ");
                $stats = mysqli_fetch_assoc($stats_query);

                $stats_colors = [
                    ['from' => 'from-blue-500', 'to' => 'to-blue-600', 'bg' => 'bg-blue-500'],
                    ['from' => 'from-green-500', 'to' => 'to-green-600', 'bg' => 'bg-green-500'],
                    ['from' => 'from-purple-500', 'to' => 'to-purple-600', 'bg' => 'bg-purple-500'],
                    ['from' => 'from-amber-500', 'to' => 'to-amber-600', 'bg' => 'bg-amber-500']
                ];

                $stat_items = [
                    [
                        'title' => 'Total Devices',
                        'value' => $stats['total_devices'] ?? 0,
                        'icon' => 'fa-laptop',
                        'description' => 'Devices in system'
                    ],
                    [
                        'title' => 'Total Users',
                        'value' => $stats['total_users'] ?? 0,
                        'icon' => 'fa-users',
                        'description' => 'Unique users assigned'
                    ],
                    [
                        'title' => 'Total Assignments',
                        'value' => $stats['total_assignments'] ?? 0,
                        'icon' => 'fa-exchange-alt',
                        'description' => 'All assignments'
                    ],
                    [
                        'title' => 'Avg. Assignment',
                        'value' => $stats['avg_assignment_days'] ? round($stats['avg_assignment_days'], 1) . ' days' : 'N/A',
                        'icon' => 'fa-calendar-alt',
                        'description' => 'Average duration'
                    ]
                ];
                ?>

                <?php foreach ($stat_items as $index => $stat): ?>
                    <div class="stat-card bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-gray-500 text-sm font-medium mb-1"><?= $stat['title'] ?></div>
                                <div class="text-2xl font-bold text-gray-800"><?= $stat['value'] ?></div>
                                <div class="text-gray-400 text-xs mt-1"><?= $stat['description'] ?></div>
                            </div>
                            <div
                                class="w-12 h-12 <?= $stats_colors[$index]['from'] ?> <?= $stats_colors[$index]['to'] ?> rounded-lg flex items-center justify-center">
                                <i class="fas <?= $stat['icon'] ?> text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Top Devices and Users Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Top 5 Most Assigned Devices -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800">Top 5 Most Assigned Devices</h2>
                                <p class="text-gray-500 text-sm">Devices with the highest assignment counts</p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-trophy text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($topDevices)): ?>
                            <div class="text-center py-8">
                                <div
                                    class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-inbox text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No device assignment data available</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($topDevices as $index => $device): ?>
                                    <?php
                                    $rank = $index + 1;
                                    $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                                    $deviceIconColor = match ($device['device_type']) {
                                        'Laptop' => 'bg-blue-100 text-blue-600',
                                        'Desktop' => 'bg-purple-100 text-purple-600',
                                        'Tablet' => 'bg-green-100 text-green-600',
                                        'Mobile' => 'bg-red-100 text-red-600',
                                        'Monitor' => 'bg-amber-100 text-amber-600',
                                        default => 'bg-gray-100 text-gray-600'
                                    };
                                    ?>
                                    <div class="top-list-item">
                                        <div class="flex items-center">
                                            <div class="rank-badge <?= $rankClass ?>">
                                                <?= $rank ?>
                                            </div>
                                            <div class="device-icon <?= $deviceIconColor ?>">
                                                <i class="fas fa-laptop"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($device['asset_tag']) ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?= htmlspecialchars($device['device_type']) ?> •
                                                            <?= htmlspecialchars($device['model']) ?>
                                                        </div>
                                                        <div class="text-xs text-gray-400 mt-1">
                                                            <?= htmlspecialchars($device['brand_name'] ?? 'N/A') ?> •
                                                            <?= htmlspecialchars($device['category_name'] ?? 'N/A') ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="font-bold text-gray-900 text-xl">
                                                            <?= $device['assignment_count'] ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">assignments</div>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="flex justify-between text-sm mb-1">
                                                        <span class="text-gray-600">Unique Users:</span>
                                                        <span class="font-medium"><?= $device['user_count'] ?></span>
                                                    </div>
                                                    <div class="flex justify-between text-sm">
                                                        <span class="text-gray-600">Status:</span>
                                                        <span
                                                            class="status-badge <?= $device['status'] === 'in_use' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' : 'bg-gray-100 text-gray-700 border-gray-200' ?>">
                                                            <?= htmlspecialchars($statusLabels[$device['status']] ?? ucfirst(str_replace('_', ' ', $device['status']))) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top 5 Users with Most Assignments -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800">Top 5 Active Users</h2>
                                <p class="text-gray-500 text-sm">Users with the highest assignment counts</p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($topUsers)): ?>
                            <div class="text-center py-8">
                                <div
                                    class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-users text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">No user assignment data available</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($topUsers as $index => $user): ?>
                                    <?php
                                    $rank = $index + 1;
                                    $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                                    $userStatusColor = $user['user_status'] === 'active' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-gray-100 text-gray-700 border-gray-200';
                                    $roleColor = $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700';
                                    ?>
                                    <div class="top-list-item">
                                        <div class="flex items-center">
                                            <div class="rank-badge <?= $rankClass ?>">
                                                <?= $rank ?>
                                            </div>
                                            <div class="user-icon bg-gradient-to-r from-blue-500 to-purple-600">
                                                <span class="text-white font-semibold">
                                                    <?= strtoupper(substr($user['firstname'] ?? '', 0, 1) . substr($user['lastname'] ?? '', 0, 1)) ?>
                                                </span>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <i class="fas fa-envelope mr-1"></i>
                                                            <?= htmlspecialchars($user['email']) ?>
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-1">
                                                            <span class="text-xs px-2 py-1 rounded-full <?= $roleColor ?>">
                                                                <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                                            </span>
                                                            <span
                                                                class="text-xs px-2 py-1 rounded-full <?= $userStatusColor ?>">
                                                                <?= htmlspecialchars(ucfirst($user['user_status'])) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="font-bold text-gray-900 text-xl">
                                                            <?= $user['assignment_count'] ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">assignments</div>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                                        <div>
                                                            <div class="text-gray-600">Devices Assigned:</div>
                                                            <div class="font-medium"><?= $user['device_count'] ?> devices</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-gray-600">Avg. Duration:</div>
                                                            <div class="font-medium">
                                                                <?= round($user['avg_days_per_assignment'] ?? 0, 1) ?> days
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2">
                                                        <div class="text-sm text-gray-600">
                                                            Total Days Assigned: <span
                                                                class="font-medium"><?= round($user['total_days_assigned'] ?? 0) ?>
                                                                days</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="w-full">
                    <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-end">
                        <!-- Search -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Search Devices</label>
                            <div class="relative">
                                <i
                                    class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                    placeholder="Search by asset tag, device type, model, or brand..."
                                    autocomplete="off"
                                    class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent search-glow">
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Status</label>
                            <select name="status"
                                class="w-full border border-gray-200 p-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <?php foreach ($statusLabels as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $status_filter == $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Category</label>
                            <select name="category"
                                class="w-full border border-gray-200 p-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <button type="submit"
                                class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-200 font-medium">
                                <i class="fas fa-filter mr-2"></i>Filter
                            </button>
                            <a href="device_history.php"
                                class="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 font-medium">
                                <i class="fas fa-redo mr-2"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Devices Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Device Assignment History</h2>
                        <p class="text-gray-500 text-sm">Showing <?= count($devices) ?> of <?= $total_devices ?> devices
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Device</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Assignments</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Users</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    First Assigned</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Last Assigned</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($devices)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div
                                                class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                <i class="fas fa-search text-gray-400 text-xl"></i>
                                            </div>
                                            <p class="text-gray-500">No devices found matching your criteria</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-700 border-green-200',
                                    'in_use' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                    'in_storage' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                    'repairing' => 'bg-orange-100 text-orange-700 border-orange-200',
                                    'faulty' => 'bg-red-100 text-red-700 border-red-200',
                                    'retired' => 'bg-gray-100 text-gray-700 border-gray-200'
                                ];

                                $conditionColors = [
                                    'New' => 'bg-green-100 text-green-700 border-green-200',
                                    'Good' => 'bg-blue-100 text-blue-700 border-blue-200',
                                    'Fair' => 'bg-amber-100 text-amber-700 border-amber-200',
                                    'Faulty' => 'bg-red-100 text-red-700 border-red-200'
                                ];
                                ?>

                                <?php foreach ($devices as $device): ?>
                                    <?php
                                    $status_class = $statusColors[$device['status']] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                    $condition_class = $conditionColors[$device['condition']] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                    $assignment_percentage = min(100, ($device['total_assignments'] * 20));
                                    ?>
                                    <tr class="table-row-hover">
                                        <!-- Device Info -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div
                                                    class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                    <i class="fas fa-laptop text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900">
                                                        <?= htmlspecialchars($device['asset_tag']) ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?= htmlspecialchars($device['device_type']) ?>
                                                        <?php if ($device['model']): ?>
                                                            • <?= htmlspecialchars($device['model']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs text-gray-400">
                                                        <?= htmlspecialchars($device['brand_name'] ?? 'N/A') ?>
                                                        • <?= htmlspecialchars($device['category_name'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Assignments Count -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-24 mr-3">
                                                    <div class="progress-bar">
                                                        <div class="progress-fill bg-blue-500"
                                                            style="width: <?= $assignment_percentage ?>%"></div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="font-bold text-gray-900"><?= $device['total_assignments'] ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">assignments</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Users Count -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div
                                                    class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-2">
                                                    <i class="fas fa-user text-purple-600 text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-gray-900"><?= $device['total_users'] ?></div>
                                                    <div class="text-xs text-gray-500">unique users</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- First Assigned -->
                                        <td class="px-6 py-4">
                                            <?php if ($device['first_assigned']): ?>
                                                <div class="text-gray-900">
                                                    <?= date('M d, Y', strtotime($device['first_assigned'])) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= date('h:i A', strtotime($device['first_assigned'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Never assigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Last Assigned -->
                                        <td class="px-6 py-4">
                                            <?php if ($device['last_assigned']): ?>
                                                <div class="text-gray-900">
                                                    <?= date('M d, Y', strtotime($device['last_assigned'])) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= date('h:i A', strtotime($device['last_assigned'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Not applicable</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4">
                                            <span class="status-badge <?= $status_class ?>">
                                                <?= htmlspecialchars($statusLabels[$device['status']] ?? ucfirst(str_replace('_', ' ', $device['status']))) ?>
                                            </span>
                                            <div class="mt-1">
                                                <span class="text-xs status-badge <?= $condition_class ?>">
                                                    <?= htmlspecialchars($conditionLabels[$device['condition']] ?? ucfirst($device['condition'])) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4">
                                            <div class="flex gap-2">
                                                <button
                                                    onclick="openDeviceHistoryModal(<?= $device['id'] ?>, '<?= htmlspecialchars(addslashes($device['asset_tag'])) ?>')"
                                                    class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                                                    <i class="fas fa-history text-xs mr-1"></i>
                                                    View History
                                                </button>
                                                <a href="inventory.php?edit=<?= $device['id'] ?>"
                                                    class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors inline-flex items-center">
                                                    <i class="fas fa-edit text-xs mr-1"></i>
                                                    Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <?php
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $base_url = '?' . (!empty($query_params) ? http_build_query($query_params) . '&' : '');
                    ?>

                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                            <div class="text-sm text-gray-600">
                                Page <?= $page ?> of <?= $total_pages ?> •
                                Showing <?= count($devices) ?> of <?= $total_devices ?> devices
                            </div>

                            <div class="flex items-center gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $base_url ?>page=<?= $page - 1 ?>"
                                        class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <a href="<?= $base_url ?>page=<?= $i ?>" class="px-3 py-2 rounded-lg font-medium text-sm <?= $i == $page
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= $base_url ?>page=<?= $page + 1 ?>"
                                        class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">Show:</span>
                                <select onchange="changeItemsPerPage(this)"
                                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <span class="text-sm text-gray-600">per page</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Device History Modal -->
    <div id="deviceHistoryModal" class="fixed inset-0 z-50 hidden modal-backdrop">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl modal-content">
                <!-- Modal Header -->
                <div
                    class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 rounded-t-2xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                            <i class="fas fa-history text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white" id="modalTitle">Device Assignment History</h2>
                            <p class="text-blue-100 text-sm" id="modalSubtitle">Loading...</p>
                        </div>
                    </div>
                    <button onclick="closeDeviceHistoryModal()" class="text-white/80 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto max-h-[70vh]">
                    <div id="loadingSpinner" class="loading-spinner text-center py-12">
                        <div
                            class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mb-4">
                        </div>
                        <p class="text-gray-600">Loading device history...</p>
                    </div>

                    <div id="modalContent" class="hidden">
                        <!-- Device Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="text-blue-600 text-sm font-medium mb-1">Asset Tag</div>
                                <div class="text-gray-800 font-bold font-mono" id="modalAssetTag"></div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="text-green-600 text-sm font-medium mb-1">Brand & Model</div>
                                <div class="text-gray-800 font-semibold" id="modalBrand"></div>
                                <div class="text-gray-600 text-sm" id="modalModel"></div>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-4">
                                <div class="text-purple-600 text-sm font-medium mb-1">Serial Number</div>
                                <div class="text-gray-800 font-semibold font-mono" id="modalSerial"></div>
                            </div>
                            <div class="bg-amber-50 rounded-lg p-4">
                                <div class="text-amber-600 text-sm font-medium mb-1">Category</div>
                                <div class="text-gray-800 font-semibold" id="modalCategory"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-gray-600 text-sm font-medium mb-1">Current Status</div>
                                <span class="status-badge" id="modalStatus"></span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-gray-600 text-sm font-medium mb-1">Total Assignments</div>
                                <div class="text-gray-800 font-bold text-xl" id="modalTotalAssignments"></div>
                                <div class="text-gray-500 text-sm" id="modalUniqueUsers"></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-gray-600 text-sm font-medium mb-1">Current Assignment</div>
                                <div class="text-gray-800 font-semibold" id="modalCurrentAssignment"></div>
                                <div class="text-gray-500 text-sm" id="modalAssignmentDate"></div>
                            </div>
                        </div>

                        <!-- Assignment History -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-lg font-semibold text-gray-800">Assignment History</h3>
                                <p class="text-gray-600 text-sm" id="timelineDescription"></p>
                            </div>

                            <div class="p-6">
                                <div id="assignmentHistory"></div>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-lg font-semibold text-gray-800">Assignment Statistics</h3>
                            </div>

                            <div class="p-6">
                                <div id="assignmentStatistics"></div>
                            </div>
                        </div>
                    </div>

                    <div id="noHistoryMessage" class="hidden text-center py-12">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-slash text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-700 mb-2">No Assignment History</h3>
                        <p class="text-gray-500">This device has never been assigned to any user.</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t rounded-b-2xl">
                    <button onclick="printModalContent()"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors inline-flex items-center">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button onclick="closeDeviceHistoryModal()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Status labels mapping
        const statusLabels = {
            'active': 'Active',
            'in_use': 'In Use',
            'in_storage': 'In Storage',
            'repairing': 'Repairing',
            'faulty': 'Faulty',
            'retired': 'Retired'
        };

        // Status colors mapping
        const statusColors = {
            'active': 'bg-green-100 text-green-700 border-green-200',
            'in_use': 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'in_storage': 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'repairing': 'bg-orange-100 text-orange-700 border-orange-200',
            'faulty': 'bg-red-100 text-red-700 border-red-200',
            'retired': 'bg-gray-100 text-gray-700 border-gray-200'
        };

        // Condition colors mapping
        const conditionColors = {
            'New': 'bg-green-100 text-green-700 border-green-200',
            'Good': 'bg-blue-100 text-blue-700 border-blue-200',
            'Fair': 'bg-amber-100 text-amber-700 border-amber-200',
            'Faulty': 'bg-red-100 text-red-700 border-red-200'
        };

        // Toast Notification System
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;

            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'warning') icon = 'fa-exclamation-triangle';

            toast.innerHTML = `
                <i class="fas ${icon} text-lg"></i>
                <div class="flex-1">${message}</div>
                <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideOut 0.3s forwards';
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }

        // Confirmation Modal System
        let currentConfirmationCallback = null;

        function showConfirmation(title, message, confirmCallback, confirmText = 'Confirm') {
            document.getElementById('confirmationTitle').textContent = title;
            document.getElementById('confirmationMessage').textContent = message;
            document.getElementById('confirmButton').textContent = confirmText;

            currentConfirmationCallback = confirmCallback;
            document.getElementById('confirmationModal').classList.remove('hidden');
        }

        function closeConfirmation() {
            document.getElementById('confirmationModal').classList.add('hidden');
            currentConfirmationCallback = null;
        }

        function confirmAction() {
            if (currentConfirmationCallback) {
                currentConfirmationCallback();
            }
            closeConfirmation();
        }

        function showExportConfirmation() {
            showConfirmation(
                'Export Device History',
                'Are you sure you want to export the device assignment history?',
                () => {
                    exportToCSV();
                    showToast('Export started successfully', 'success');
                },
                'Export'
            );
        }

        // Pagination function
        function changeItemsPerPage(select) {
            showConfirmation(
                'Change Items Per Page',
                'Changing items per page will reset to the first page. Continue?',
                () => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('limit', select.value);
                    url.searchParams.set('page', 1);
                    window.location.href = url.toString();
                    showToast('Items per page updated', 'info');
                },
                'Continue'
            );
        }

        // Export function
        function exportToCSV() {
            const search = "<?= htmlspecialchars($search) ?>";
            const status = "<?= htmlspecialchars($status_filter) ?>";
            const category = "<?= $category_filter ?>";

            let exportUrl = 'export_device_history.php?';
            if (search) exportUrl += 'search=' + encodeURIComponent(search) + '&';
            if (status) exportUrl += 'status=' + encodeURIComponent(status) + '&';
            if (category) exportUrl += 'category=' + category + '&';

            window.location.href = exportUrl;
        }

        // Date formatting helper
        function formatDate(dateString) {
            if (!dateString) return 'Unknown date';

            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    return 'Invalid date';
                }
                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return 'Invalid date';
            }
        }

        // Modal functions
        let currentDeviceId = null;

        function openDeviceHistoryModal(deviceId, assetTag) {
            console.log('Opening modal for device:', deviceId, assetTag);

            currentDeviceId = deviceId;
            const modal = document.getElementById('deviceHistoryModal');
            const title = document.getElementById('modalTitle');
            const subtitle = document.getElementById('modalSubtitle');

            subtitle.textContent = 'Loading history for ' + assetTag;

            modal.classList.remove('hidden');
            document.getElementById('loadingSpinner').classList.add('active');
            document.getElementById('modalContent').classList.add('hidden');
            document.getElementById('noHistoryMessage').classList.add('hidden');

            fetchDeviceHistory(deviceId, assetTag);
        }

        function closeDeviceHistoryModal() {
            const modal = document.getElementById('deviceHistoryModal');
            modal.classList.add('hidden');
        }

        function fetchDeviceHistory(deviceId, assetTag) {
            console.log('Fetching history for device ID:', deviceId);

            const xhr = new XMLHttpRequest();
            const url = `device_history.php?get_device_details=${deviceId}&ajax=1&_=${Date.now()}`;

            xhr.open('GET', url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    console.log('Response status:', xhr.status);
                    console.log('Response text (first 500 chars):', xhr.responseText.substring(0, 500));

                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            console.log('Parsed JSON data:', data);

                            if (data.success === false || data.error) {
                                showToast(data.error || 'Error loading device history', 'error');
                                showNoHistory();
                                return;
                            }

                            if (data.device && Object.keys(data.device).length > 0) {
                                console.log('Device data found, populating modal...');
                                populateModal(data.device, data.history || []);
                                showToast(`Loaded history for ${assetTag}`, 'success');
                            } else {
                                console.log('No device data found');
                                showNoHistory();
                            }
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Full response:', xhr.responseText);
                            showToast('Invalid response from server. Please check console for details.', 'error');
                            showNoHistory();
                        }
                    } else {
                        showToast('Server error: ' + xhr.status, 'error');
                        showNoHistory();
                    }
                }
            };

            xhr.onerror = function () {
                console.error('Network error');
                showToast('Network error. Please check your connection.', 'error');
                showNoHistory();
            };

            xhr.send();
        }

        function populateModal(device, history) {
            console.log('Populating modal with device:', device);
            console.log('Populating modal with history:', history);

            // Update basic device info
            document.getElementById('modalAssetTag').textContent = device.asset_tag || 'N/A';
            document.getElementById('modalBrand').textContent = device.brand_name || 'N/A';
            document.getElementById('modalModel').textContent = device.model || 'N/A';
            document.getElementById('modalSerial').textContent = device.serial_number || 'N/A';
            document.getElementById('modalCategory').textContent = device.category_name || 'N/A';
            document.getElementById('modalTotalAssignments').textContent = device.total_assignments || 0;
            document.getElementById('modalUniqueUsers').textContent = (device.unique_users || 0) + ' unique users';

            // Update status with proper formatting
            const statusClass = statusColors[device.status] || 'bg-gray-100 text-gray-700 border-gray-200';
            const conditionClass = conditionColors[device.condition] || 'bg-gray-100 text-gray-700 border-gray-200';

            const statusText = device.status ?
                (statusLabels[device.status] || device.status.charAt(0).toUpperCase() + device.status.slice(1).replace('_', ' ')) :
                'Unknown';

            const conditionText = device.condition ?
                (device.condition.charAt(0).toUpperCase() + device.condition.slice(1)) :
                'Unknown';

            const statusBadge = document.getElementById('modalStatus');
            statusBadge.className = `status-badge ${statusClass}`;
            statusBadge.innerHTML = `
                ${statusText}
                <span class="ml-2 text-xs status-badge ${conditionClass}">
                    ${conditionText}
                </span>
            `;

            // Find current assignment
            const currentAssignment = Array.isArray(history) ?
                history.find(a => a.status === 'assigned') : null;

            if (currentAssignment) {
                document.getElementById('modalCurrentAssignment').textContent =
                    currentAssignment.firstname + ' ' + currentAssignment.lastname;
                document.getElementById('modalAssignmentDate').textContent =
                    'Since ' + formatDate(currentAssignment.assigned_at);
            } else {
                document.getElementById('modalCurrentAssignment').textContent = 'Not assigned';
                document.getElementById('modalAssignmentDate').textContent = 'Available for assignment';
            }

            // Update timeline description
            document.getElementById('modalSubtitle').textContent = `History for ${device.asset_tag}`;
            document.getElementById('timelineDescription').textContent =
                `Showing ${history.length} assignments for ${device.asset_tag}`;

            // Populate assignment history
            populateAssignmentHistory(history || []);

            // Populate statistics
            populateStatistics(history || []);

            // Hide loading, show content
            document.getElementById('loadingSpinner').classList.remove('active');
            document.getElementById('modalContent').classList.remove('hidden');
        }

        function populateAssignmentHistory(history) {
            const historyContainer = document.getElementById('assignmentHistory');

            if (!Array.isArray(history) || history.length === 0) {
                historyContainer.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-slash text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500">No assignment history available</p>
                    </div>
                `;
                return;
            }

            let historyHTML = '<div class="space-y-4">';

            history.forEach((assignment, index) => {
                console.log('Processing assignment:', assignment);

                const isActive = assignment.status === 'assigned';
                const assignedDate = new Date(assignment.assigned_at);
                const returnedDate = assignment.returned_at ? new Date(assignment.returned_at) : null;

                // Format dates safely
                let assignedDateStr = 'Invalid date';
                let returnedDateStr = 'N/A';

                try {
                    if (!isNaN(assignedDate.getTime())) {
                        assignedDateStr = assignedDate.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }

                    if (returnedDate && !isNaN(returnedDate.getTime())) {
                        returnedDateStr = returnedDate.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                } catch (e) {
                    console.error('Date formatting error:', e);
                }

                // Calculate duration
                const daysAssigned = assignment.days_assigned || 0;
                const durationText = daysAssigned > 0 ?
                    `${daysAssigned} day${daysAssigned > 1 ? 's' : ''}` :
                    'Less than a day';

                historyHTML += `
                    <div class="assignment-card">
                        <div class="p-5">
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-start gap-4">
                                        <div class="user-avatar flex-shrink-0">
                                            ${assignment.firstname ? assignment.firstname.charAt(0).toUpperCase() : '?'}
                                        </div>
                                        
                                        <div class="flex-1">
                                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium ${isActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'}">
                                                    <i class="fas ${isActive ? 'fa-user-check' : 'fa-user-times'} mr-1"></i>
                                                    ${isActive ? 'ACTIVE' : 'COMPLETED'}
                                                </span>
                                                <span class="text-sm text-gray-500">
                                                    <i class="far fa-calendar mr-1"></i>${assignedDateStr}
                                                </span>
                                                <span class="text-sm text-gray-500">
                                                    <i class="far fa-clock mr-1"></i>${durationText}
                                                </span>
                                            </div>
                                            
                                            <h4 class="font-medium text-gray-800 mb-2">
                                                ${assignment.firstname || ''} ${assignment.lastname || ''}
                                                ${isActive ? '<span class="text-green-600 ml-2">• Currently Assigned</span>' : ''}
                                            </h4>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <div class="text-gray-600 mb-2">
                                                        <i class="fas fa-envelope mr-2"></i>
                                                        ${assignment.email || 'N/A'}
                                                    </div>
                                                    <div class="text-gray-600 mb-2">
                                                        <i class="fas fa-user-tag mr-2"></i>
                                                        Role: ${assignment.role || 'N/A'}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-gray-600 mb-2">
                                                        <i class="fas fa-calendar-alt mr-2"></i>
                                                        Assignment Date: ${assignedDateStr}
                                                    </div>
                                                    ${returnedDate ? `
                                                        <div class="text-gray-600 mb-2">
                                                            <i class="fas fa-calendar-times mr-2"></i>
                                                            Returned: ${returnedDateStr}
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="md:text-right">
                                    <div class="text-gray-500 text-sm mb-2">
                                        <i class="fas fa-hashtag mr-1"></i>
                                        Assignment #${index + 1}
                                    </div>
                                    <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                                        <i class="fas ${isActive ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>
                                        ${isActive ? 'Active' : 'Completed'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            historyHTML += '</div>';
            historyContainer.innerHTML = historyHTML;
        }

        function populateStatistics(history) {
            const statsContainer = document.getElementById('assignmentStatistics');

            if (!Array.isArray(history) || history.length === 0) {
                statsContainer.innerHTML = '<p class="text-gray-500 text-center">No statistics available</p>';
                return;
            }

            // Calculate statistics
            let totalDays = 0;
            let activeAssignments = 0;
            let completedAssignments = 0;
            let uniqueUserIds = [];
            let userCounts = {};
            let totalActiveDays = 0;
            let longestAssignment = 0;
            let shortestAssignment = Infinity;

            history.forEach(assignment => {
                if (assignment.status === 'assigned') {
                    activeAssignments++;
                    totalActiveDays += parseInt(assignment.days_assigned) || 0;
                } else {
                    completedAssignments++;
                }

                const daysAssigned = parseInt(assignment.days_assigned) || 0;
                totalDays += daysAssigned;

                if (daysAssigned > longestAssignment) longestAssignment = daysAssigned;
                if (daysAssigned < shortestAssignment) shortestAssignment = daysAssigned;

                if (assignment.user_id && !uniqueUserIds.includes(assignment.user_id)) {
                    uniqueUserIds.push(assignment.user_id);
                }

                if (assignment.user_id) {
                    if (!userCounts[assignment.user_id]) {
                        userCounts[assignment.user_id] = {
                            name: `${assignment.firstname || ''} ${assignment.lastname || ''}`.trim() || 'Unknown User',
                            count: 0,
                            totalDays: 0
                        };
                    }
                    userCounts[assignment.user_id].count++;
                    userCounts[assignment.user_id].totalDays += daysAssigned;
                }
            });

            if (shortestAssignment === Infinity) shortestAssignment = 0;

            const avgDays = history.length > 0 ? Math.round((totalDays / history.length) * 10) / 10 : 0;
            const avgActiveDays = activeAssignments > 0 ? Math.round((totalActiveDays / activeAssignments) * 10) / 10 : 0;

            // Convert userCounts object to array and sort
            const userCountsArray = Object.values(userCounts);
            userCountsArray.sort((a, b) => b.count - a.count);
            const topUsers = userCountsArray.slice(0, 5);

            let statsHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-blue-600 text-sm font-medium mb-1">Total Assignments</div>
                        <div class="text-2xl font-bold text-gray-800">${history.length}</div>
                        <div class="text-gray-500 text-sm">${activeAssignments} active, ${completedAssignments} completed</div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-green-600 text-sm font-medium mb-1">Unique Users</div>
                        <div class="text-2xl font-bold text-gray-800">${uniqueUserIds.length}</div>
                        <div class="text-gray-500 text-sm">Distinct users assigned</div>
                    </div>
                    
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="text-purple-600 text-sm font-medium mb-1">Avg. Duration</div>
                        <div class="text-2xl font-bold text-gray-800">${avgDays} days</div>
                        <div class="text-gray-500 text-sm">Average per assignment</div>
                    </div>
                    
                    <div class="bg-amber-50 rounded-lg p-4">
                        <div class="text-amber-600 text-sm font-medium mb-1">Longest Assignment</div>
                        <div class="text-2xl font-bold text-gray-800">${longestAssignment} days</div>
                        <div class="text-gray-500 text-sm">Shortest: ${shortestAssignment} days</div>
                    </div>
                </div>
            `;

            if (topUsers.length > 0) {
                statsHTML += `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-4">Top Users by Assignments</h4>
                        <div class="space-y-4">
                `;

                topUsers.forEach(user => {
                    const percentage = Math.min(100, (user.count / history.length * 100));
                    const avgUserDays = user.count > 0 ? Math.round(user.totalDays / user.count * 10) / 10 : 0;

                    statsHTML += `
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="user-avatar w-8 h-8 text-xs">
                                        ${user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-800">${user.name}</div>
                                        <div class="text-gray-500 text-sm">Avg: ${avgUserDays} days per assignment</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-800">${user.count} assignment${user.count > 1 ? 's' : ''}</div>
                                    <div class="text-gray-500 text-sm">Total: ${user.totalDays} days</div>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                            </div>
                        </div>
                    `;
                });

                statsHTML += `
                        </div>
                    </div>
                `;
            }

            // Add assignment timeline visualization
            if (history.length > 0) {
                const firstAssignment = history[history.length - 1];
                const lastAssignment = history[0];

                statsHTML += `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-4">Assignment Timeline Overview</h4>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-gray-600">First Assignment:</span>
                                <span class="font-medium">${formatDate(firstAssignment.assigned_at)}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-gray-600">Last Assignment:</span>
                                <span class="font-medium">${formatDate(lastAssignment.assigned_at)}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Total Time Period:</span>
                                <span class="font-medium">${Math.round(totalDays)} days</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            statsContainer.innerHTML = statsHTML;
        }

        function showNoHistory() {
            document.getElementById('loadingSpinner').classList.remove('active');
            document.getElementById('noHistoryMessage').classList.remove('hidden');
        }

        function printModalContent() {
            showConfirmation(
                'Print Device History',
                'Do you want to print the device assignment history?',
                () => {
                    const printContent = document.getElementById('modalContent').innerHTML;
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Device History - Print</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                                .device-info { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
                                .info-card { border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
                                .status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
                                .timeline-item { margin-bottom: 20px; padding-left: 20px; border-left: 3px solid #3b82f6; }
                                .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                                .assignment-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
                                @media print {
                                    body { font-size: 12px; }
                                    .print-header { margin-bottom: 20px; }
                                    .device-info { grid-template-columns: repeat(2, 1fr); }
                                    .stats-grid { grid-template-columns: repeat(2, 1fr); }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="print-header">
                                <h1>Device Assignment History</h1>
                                <p>Generated on ${new Date().toLocaleDateString()}</p>
                            </div>
                            ${printContent}
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                    showToast('Print dialog opened', 'info');
                },
                'Print'
            );
        }

        // Close modal when clicking outside
        document.getElementById('deviceHistoryModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeDeviceHistoryModal();
            }
        });

        // Close confirmation modal when clicking outside
        document.getElementById('confirmationModal').addEventListener('click', function (e) {
            if (e.target.classList.contains('confirmation-backdrop')) {
                closeConfirmation();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDeviceHistoryModal();
                closeConfirmation();
            }
        });

        // Focus search input on page load
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
            }

            setTimeout(() => {
                showToast('Welcome to Device Assignment History', 'info', 3000);
            }, 1000);
        });
    </script>
</body>

</html>