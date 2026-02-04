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
    die("Database connection failed");
}

/* ================== FETCH DEVICE DETAILS FOR MODAL ================== */
$device_details = null;
$assignment_history = [];

// Check if we're requesting device details via AJAX
if (isset($_GET['get_device_details']) && is_numeric($_GET['get_device_details'])) {
    $device_id = (int) $_GET['get_device_details'];

    // Get device information
    $device_query = mysqli_prepare($conn, "
        SELECT 
            i.*,
            b.brand_name,
            c.category_name,
            d.department_name,
            l.location_name,
            (SELECT COUNT(*) FROM device_user_assignments WHERE inventory_id = i.id) as total_assignments,
            (SELECT COUNT(DISTINCT user_id) FROM device_user_assignments WHERE inventory_id = i.id) as unique_users
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN departments d ON i.department_id = d.id
        LEFT JOIN locations l ON i.location_id = l.id
        WHERE i.id = ?
    ");

    mysqli_stmt_bind_param($device_query, "i", $device_id);
    mysqli_stmt_execute($device_query);
    $device_result = mysqli_stmt_get_result($device_query);
    $device_details = mysqli_fetch_assoc($device_result);
    mysqli_stmt_close($device_query);

    if ($device_details) {
        // Get assignment history
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

        mysqli_stmt_bind_param($history_query, "i", $device_id);
        mysqli_stmt_execute($history_query);
        $history_result = mysqli_stmt_get_result($history_query);

        while ($row = mysqli_fetch_assoc($history_result)) {
            $assignment_history[] = $row;
        }
        mysqli_stmt_close($history_query);
    }

    // If it's an AJAX request, return JSON and exit
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'device' => $device_details,
            'history' => $assignment_history
        ]);
        exit;
    }
}

/* ================== FETCH ALL DEVICES WITH HISTORY ================== */
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$category_filter = isset($_GET['category']) ? (int) $_GET['category'] : 0;

// Build WHERE clause
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

// Count total devices
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

// Add limit and offset to params
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

$status_labels = [
    'active' => 'Active',
    'in_use' => 'In Use',
    'in_storage' => 'Store',
    'repairing' => 'Repairing',
    'faulty' => 'Faulty',
    'retired' => 'Retired'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Device Assignment History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        /* Modal Styles */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            max-height: 90vh;
            animation: modalSlideIn 0.3s ease-out;
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
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3b82f6;
        }
        
        .timeline-item:after {
            content: '';
            position: absolute;
            left: 5px;
            top: 12px;
            width: 2px;
            height: calc(100% + 1.5rem);
            background: #e5e7eb;
        }
        
        .timeline-item:last-child:after {
            display: none;
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
        
        .loading-spinner {
            display: none;
        }
        
        .loading-spinner.active {
            display: block;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
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
                    <button onclick="exportToCSV()" 
                            class="bg-gradient-to-r from-green-50 to-emerald-50  text-green-700 px-4 py-2 text-sm rounded-lg hover:bg-green-700">
                        <i class="fas fa-download text-xs mr-1"></i> Export
                    </button>
                </div>
            </div>

            <!-- Statistics Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <?php
                // Calculate statistics
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
                                <div class="w-12 h-12 <?= $stats_colors[$index]['from'] ?> <?= $stats_colors[$index]['to'] ?> rounded-lg flex items-center justify-center">
                                    <i class="fas <?= $stat['icon'] ?> text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="w-full">
                    <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-end">
                        <!-- Search -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Search Devices</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                    placeholder="Search by asset tag, device type, model, or brand..."autocomplete="off"
                                    class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent search-glow">
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Status</label>
                            <select name="status" 
                                    class="w-full border border-gray-200 p-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <?php foreach ($statuses as $status): ?>
                                        <option value="<?= $status ?>" <?= $status_filter == $status ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($status_labels[$status] ?? ucfirst($status)) ?>
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
                        <p class="text-gray-500 text-sm">Showing <?= count($devices) ?> of <?= $total_devices ?> devices</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignments</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Assigned</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Assigned</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($devices)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                    <i class="fas fa-search text-gray-400 text-xl"></i>
                                                </div>
                                                <p class="text-gray-500">No devices found matching your criteria</p>
                                            </div>
                                        </td>
                                    </tr>
                            <?php else: ?>
                                    <?php foreach ($devices as $device): ?>
                                            <?php
                                            $status_colors = [
                                                'active' => 'bg-green-100 text-green-700 border-green-200',
                                                'in_use' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                                'in_storage' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                                'repairing' => 'bg-gray-100 text-gray-700 border-gray-200',
                                                'faulty' => 'bg-pink-100 text-pink-700 border-pink-200',
                                                'retired' => 'bg-red-100 text-red-700 border-red-200'
                                            ];
                                            $status_class = $status_colors[$device['status']] ?? 'bg-gray-100 text-gray-700 border-gray-200';

                                            // Calculate assignment percentage (max 5 assignments = 100%)
                                            $assignment_percentage = min(100, ($device['total_assignments'] * 20));
                                            ?>
                                            <tr class="table-row-hover">
                                                <!-- Device Info -->
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
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
                                                            <div class="font-bold text-gray-900"><?= $device['total_assignments'] ?></div>
                                                            <div class="text-xs text-gray-500">assignments</div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Users Count -->
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-2">
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
                                                            <div class="text-gray-900"><?= date('M d, Y', strtotime($device['first_assigned'])) ?></div>
                                                            <div class="text-xs text-gray-500"><?= date('h:i A', strtotime($device['first_assigned'])) ?></div>
                                                    <?php else: ?>
                                                            <span class="text-gray-400 italic">Never assigned</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Last Assigned -->
                                                <td class="px-6 py-4">
                                                    <?php if ($device['last_assigned']): ?>
                                                            <div class="text-gray-900"><?= date('M d, Y', strtotime($device['last_assigned'])) ?></div>
                                                            <div class="text-xs text-gray-500"><?= date('h:i A', strtotime($device['last_assigned'])) ?></div>
                                                    <?php else: ?>
                                                            <span class="text-gray-400 italic">Not applicable</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Status -->
                                                <td class="px-6 py-4">
                                                    <span class="status-badge <?= $status_class ?>">
                                                        <?= htmlspecialchars($status_labels[$device['status']] ?? ucfirst($device['status'])) ?>
                                                    </span>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <?= htmlspecialchars($device['condition']) ?>
                                                    </div>
                                                </td>

                                                <!-- Actions -->
                                                <td class="px-6 py-4">
                                                    <div class="flex gap-2">
                                                        <button onclick="openDeviceHistoryModal(<?= $device['id'] ?>, '<?= htmlspecialchars($device['asset_tag']) ?>')"
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
                                            <a href="<?= $base_url ?>page=<?= $i ?>"
                                               class="px-3 py-2 rounded-lg font-medium text-sm <?= $i == $page
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

            <!-- Top Devices Card -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <!-- Most Assigned Devices -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Most Assigned Devices</h3>
                        <p class="text-gray-500 text-sm">Devices with highest assignment counts</p>
                    </div>
                    <div class="p-6">
                        <?php
                        $top_devices_query = mysqli_query($conn, "
                            SELECT 
                                i.id,
                                i.asset_tag,
                                i.device_type,
                                COUNT(dua.id) as assignment_count
                            FROM inventory_items i
                            LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
                            GROUP BY i.id
                            HAVING assignment_count > 0
                            ORDER BY assignment_count DESC
                            LIMIT 5
                        ");

                        $top_devices = [];
                        while ($row = mysqli_fetch_assoc($top_devices_query)) {
                            $top_devices[] = $row;
                        }
                        ?>
                        
                        <?php if (empty($top_devices)): ?>
                                <p class="text-gray-500 text-center py-4">No assignment data available</p>
                        <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($top_devices as $index => $device): ?>
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                        <span class="text-blue-600 font-bold"><?= $index + 1 ?></span>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($device['asset_tag']) ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?= htmlspecialchars($device['device_type']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="font-bold text-gray-900"><?= $device['assignment_count'] ?></div>
                                                    <div class="text-xs text-gray-500">assignments</div>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recently Assigned -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Recently Assigned Devices</h3>
                        <p class="text-gray-500 text-sm">Devices with recent assignments</p>
                    </div>
                    <div class="p-6">
                        <?php
                        $recent_query = mysqli_query($conn, "
                            SELECT 
                                i.id,
                                i.asset_tag,
                                i.device_type,
                                dua.assigned_at,
                                u.firstname,
                                u.lastname
                            FROM device_user_assignments dua
                            JOIN inventory_items i ON dua.inventory_id = i.id
                            JOIN users u ON dua.user_id = u.id
                            WHERE dua.status = 'assigned'
                            ORDER BY dua.assigned_at DESC
                            LIMIT 5
                        ");

                        $recent_assignments = [];
                        while ($row = mysqli_fetch_assoc($recent_query)) {
                            $recent_assignments[] = $row;
                        }
                        ?>
                        
                        <?php if (empty($recent_assignments)): ?>
                                <p class="text-gray-500 text-center py-4">No recent assignments</p>
                        <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-laptop text-green-600"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900">
                                                            <?= htmlspecialchars($assignment['asset_tag']) ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            Assigned to <?= htmlspecialchars($assignment['firstname'] . ' ' . $assignment['lastname']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm text-gray-900">
                                                        <?= date('M d', strtotime($assignment['assigned_at'])) ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?= date('h:i A', strtotime($assignment['assigned_at'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Device History Modal -->
    <div id="deviceHistoryModal" class="fixed inset-0 z-50 hidden modal-backdrop">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl modal-content">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 rounded-t-2xl flex items-center justify-between">
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
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mb-4"></div>
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

                        <!-- Assignment History Timeline -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-lg font-semibold text-gray-800">Assignment Timeline</h3>
                                <p class="text-gray-600 text-sm" id="timelineDescription"></p>
                            </div>
                            
                            <div class="p-6">
                                <div id="assignmentTimeline">
                                    <!-- Timeline will be loaded here -->
                                </div>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-lg font-semibold text-gray-800">Assignment Statistics</h3>
                            </div>
                            
                            <div class="p-6">
                                <div id="assignmentStatistics">
                                    <!-- Statistics will be loaded here -->
                                </div>
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

    <?php include __DIR__ . '/footer.php'; ?>
    <script>
        // Pagination function
        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
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
        
        // Modal functions
        let currentDeviceId = null;
        
        function openDeviceHistoryModal(deviceId, assetTag) {
            currentDeviceId = deviceId;
            const modal = document.getElementById('deviceHistoryModal');
            const title = document.getElementById('modalTitle');
            const subtitle = document.getElementById('modalSubtitle');
            
            // Update modal title
            subtitle.textContent = 'Loading history for ' + assetTag;
            
            // Show modal and loading spinner
            modal.classList.remove('hidden');
            document.getElementById('loadingSpinner').classList.add('active');
            document.getElementById('modalContent').classList.add('hidden');
            document.getElementById('noHistoryMessage').classList.add('hidden');
            
            // Fetch device details
            fetchDeviceHistory(deviceId);
        }
        
        function closeDeviceHistoryModal() {
            const modal = document.getElementById('deviceHistoryModal');
            modal.classList.add('hidden');
        }
        
        function fetchDeviceHistory(deviceId) {
            fetch(`device_history.php?get_device_details=${deviceId}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.device) {
                        populateModal(data.device, data.history);
                    } else {
                        showNoHistory();
                    }
                })
                .catch(error => {
                    console.error('Error fetching device history:', error);
                    showNoHistory();
                });
        }
        
        function populateModal(device, history) {
            // Update basic device info
            document.getElementById('modalAssetTag').textContent = device.asset_tag || 'N/A';
            document.getElementById('modalBrand').textContent = device.brand_name || 'N/A';
            document.getElementById('modalModel').textContent = device.model || 'N/A';
            document.getElementById('modalSerial').textContent = device.serial_number || 'N/A';
            document.getElementById('modalCategory').textContent = device.category_name || 'N/A';
            document.getElementById('modalTotalAssignments').textContent = device.total_assignments || 0;
            document.getElementById('modalUniqueUsers').textContent = (device.unique_users || 0) + ' unique users';
            
            // Update status
            const statusColors = {
                'active': 'bg-green-100 text-green-700 border-green-200',
                'in_use': 'bg-indigo-100 text-indigo-700 border-indigo-200',
                'in_storage': 'bg-yellow-100 text-yellow-700 border-yellow-200',
                'repairing': 'bg-gray-100 text-gray-700 border-gray-200',
                'faulty': 'bg-pink-100 text-pink-700 border-pink-200',
                'retired': 'bg-red-100 text-red-700 border-red-200'
            };
            const statusClass = statusColors[device.status] || 'bg-gray-100 text-gray-700 border-gray-200';
            const statusText = device.status ? device.status.charAt(0).toUpperCase() + device.status.slice(1) : 'Unknown';
            document.getElementById('modalStatus').className = `status-badge ${statusClass}`;
            document.getElementById('modalStatus').textContent = statusText;
            
            // Find current assignment
            const currentAssignment = history.find(a => a.status === 'assigned');
            if (currentAssignment) {
                document.getElementById('modalCurrentAssignment').textContent = 
                    currentAssignment.firstname + ' ' + currentAssignment.lastname;
                document.getElementById('modalAssignmentDate').textContent = 
                    'Since ' + new Date(currentAssignment.assigned_at).toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
            } else {
                document.getElementById('modalCurrentAssignment').textContent = 'Not assigned';
                document.getElementById('modalAssignmentDate').textContent = 'Available for assignment';
            }
            
            // Update timeline description
            document.getElementById('timelineDescription').textContent = 
                `Complete history of all assignments for ${device.asset_tag}`;
            
            // Populate timeline
            populateTimeline(history);
            
            // Populate statistics
            populateStatistics(history);
            
            // Hide loading, show content
            document.getElementById('loadingSpinner').classList.remove('active');
            document.getElementById('modalContent').classList.remove('hidden');
        }
        
        function populateTimeline(history) {
            const timelineContainer = document.getElementById('assignmentTimeline');
            
            if (history.length === 0) {
                timelineContainer.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-slash text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500">No assignment history available</p>
                    </div>
                `;
                return;
            }
            
            let timelineHTML = '<div class="space-y-4">';
            
            history.forEach((assignment, index) => {
                const isActive = assignment.status === 'assigned';
                const assignedDate = new Date(assignment.assigned_at);
                const returnedDate = assignment.returned_at ? new Date(assignment.returned_at) : null;
                
                timelineHTML += `
                    <div class="timeline-item">
                        <div class="history-card bg-gray-50 rounded-lg p-5 border-l-4 ${isActive ? 'border-blue-400' : 'border-gray-400'}">
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-start gap-3">
                                        <div class="user-avatar flex-shrink-0">
                                            ${assignment.firstname.charAt(0).toUpperCase()}
                                        </div>
                                        
                                        <div class="flex-1">
                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                <span class="px-2 py-1 rounded text-xs font-medium ${isActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'}">
                                                    ${isActive ? 'ASSIGNED' : 'RETRIEVED'}
                                                </span>
                                                <span class="text-sm text-gray-500">
                                                    ${assignedDate.toLocaleDateString('en-US', {
                                                        month: 'short',
                                                        day: 'numeric',
                                                        year: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit'
                                                    })}
                                                </span>
                                            </div>
                                            
                                            <h4 class="font-medium text-gray-800 mb-2">
                                                ${assignment.firstname} ${assignment.lastname}
                                                ${isActive ? '<span class="text-green-600 ml-2">• Currently Assigned</span>' : ''}
                                            </h4>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <div class="text-gray-600 mb-1">
                                                        <i class="fas fa-envelope mr-2"></i>
                                                        ${assignment.email}
                                                    </div>
                                                    <div class="text-gray-600 mb-1">
                                                        <i class="fas fa-user-tag mr-2"></i>
                                                        Role: ${assignment.role}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-gray-600 mb-1">
                                                        <i class="fas fa-calendar-day mr-2"></i>
                                                        Duration: ${assignment.days_assigned || 0} days
                                                    </div>
                                                    ${returnedDate ? `
                                                        <div class="text-gray-600">
                                                            <i class="fas fa-calendar-times mr-2"></i>
                                                            Returned: ${returnedDate.toLocaleDateString('en-US', {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                year: 'numeric',
                                                                hour: '2-digit',
                                                                minute: '2-digit'
                                                            })}
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="md:text-right">
                                    <div class="text-gray-500 text-sm mb-2">
                                        Assignment #${history.length - index}
                                    </div>
                                    ${isActive ? `
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Active
                                        </span>
                                    ` : `
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <i class="fas fa-times-circle mr-1"></i>
                                            Ended
                                        </span>
                                    `}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            timelineHTML += '</div>';
            timelineContainer.innerHTML = timelineHTML;
        }
        
        function populateStatistics(history) {
            const statsContainer = document.getElementById('assignmentStatistics');
            
            if (history.length === 0) {
                statsContainer.innerHTML = '<p class="text-gray-500 text-center">No statistics available</p>';
                return;
            }
            
            // Calculate statistics
            let totalDays = 0;
            let activeAssignments = 0;
            let completedAssignments = 0;
            let uniqueUserIds = [];
            let userCounts = {};
            
            history.forEach(assignment => {
                if (assignment.status === 'assigned') {
                    activeAssignments++;
                } else {
                    completedAssignments++;
                }
                
                totalDays += assignment.days_assigned || 0;
                
                if (!uniqueUserIds.includes(assignment.user_id)) {
                    uniqueUserIds.push(assignment.user_id);
                }
                
                if (!userCounts[assignment.user_id]) {
                    userCounts[assignment.user_id] = {
                        name: `${assignment.firstname} ${assignment.lastname}`,
                        count: 0
                    };
                }
                userCounts[assignment.user_id].count++;
            });
            
            const avgDays = history.length > 0 ? Math.round((totalDays / history.length) * 10) / 10 : 0;
            
            // Convert userCounts object to array and sort
            const userCountsArray = Object.values(userCounts);
            userCountsArray.sort((a, b) => b.count - a.count);
            const topUsers = userCountsArray.slice(0, 5);
            
            let statsHTML = `
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
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
                        <div class="text-purple-600 text-sm font-medium mb-1">Avg. Assignment Days</div>
                        <div class="text-2xl font-bold text-gray-800">${avgDays}</div>
                        <div class="text-gray-500 text-sm">Average duration per assignment</div>
                    </div>
                    
                    <div class="bg-amber-50 rounded-lg p-4">
                        <div class="text-amber-600 text-sm font-medium mb-1">Total Days Assigned</div>
                        <div class="text-2xl font-bold text-gray-800">${Math.round(totalDays)}</div>
                        <div class="text-gray-500 text-sm">Cumulative days in use</div>
                    </div>
                </div>
            `;
            
            if (topUsers.length > 0) {
                statsHTML += `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-4">User Assignment Frequency</h4>
                        <div class="space-y-3">
                `;
                
                topUsers.forEach(user => {
                    const percentage = Math.min(100, (user.count * 20));
                    statsHTML += `
                        <div class="flex items-center justify-between">
                            <div class="text-gray-700">${user.name}</div>
                            <div class="flex items-center">
                                <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                                </div>
                                <span class="text-gray-700 font-medium">${user.count} times</span>
                            </div>
                        </div>
                    `;
                });
                
                statsHTML += `
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
                        @media print {
                            .no-print { display: none; }
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
        }
        
        // Close modal when clicking outside
        document.getElementById('deviceHistoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeviceHistoryModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeviceHistoryModal();
            }
        });
        
        // Focus search input on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>

</html>