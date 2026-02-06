<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/config/database.php";

// Initialize database connection
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Status labels
$statusLabels = [
    'active' => 'Active',
    'in_use' => 'In Use',
    'in_storage' => 'Store',
    'repairing' => 'Repairing',
    'faulty' => 'Faulty',
    'retired' => 'Retired'
];

// Output encoding helper
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Status label helper function
function getStatusLabel($status)
{
    global $statusLabels;
    return $statusLabels[$status] ?? ucfirst($status);
}

// Condition labels
const CONDITION_LABELS = [
    'New' => 'New',
    'Good' => 'Good',
    'Fair' => 'Fair',
    'Poor' => 'Poor',
    'Faulty' => 'Faulty'
];

/* ================== INVENTORY STATISTICS ================== */

// Initialize statistics with defaults
$stats = [
    'total_items' => 0,
    'total_users' => 0,
    'unassigned_devices' => 0,
    'faulty_devices' => 0,
    'active_users' => 0,
    'admin_users' => 0,
    'today_changes' => 0,
    'in_use' => 0,
    'in_storage' => 0,
    'repairing' => 0,
    'retired' => 0,
    'total_brands' => 0,
    'total_categories' => 0,
    'total_departments' => 0
];

// Additional statistics for charts
$deviceStatusStats = [];
$deviceConditionStats = [];
$topBrands = [];
$topCategories = [];
$departmentsArr = [];

try {
    // Get inventory statistics - Fixed: Calculate faulty devices correctly and include storage/retired in unassigned
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM inventory_items) as total_items,
            (SELECT COUNT(*) FROM users) as total_users,
            -- Unassigned devices: those not assigned to any user OR in storage OR retired
            (SELECT COUNT(*) FROM inventory_items 
             WHERE (assigned_user IS NULL OR assigned_user = 0 
                    OR status IN ('in_storage', 'retired'))) as unassigned_devices,
            -- Faulty devices: those with status='faulty' OR condition='Faulty'
            (SELECT COUNT(*) FROM inventory_items 
             WHERE status='faulty' OR `condition`='Faulty') as faulty_devices,
            (SELECT COUNT(*) FROM users WHERE status='active') as active_users,
            (SELECT COUNT(*) FROM users WHERE role='admin') as admin_users,
            (SELECT COUNT(*) FROM inventory_items WHERE created_at >= CURDATE()) as today_changes,
            (SELECT COUNT(*) FROM inventory_items WHERE status='in_use') as in_use,
            (SELECT COUNT(*) FROM inventory_items WHERE status='in_storage') as in_storage,
            (SELECT COUNT(*) FROM inventory_items WHERE status='repairing') as repairing,
            (SELECT COUNT(*) FROM inventory_items WHERE status='retired') as retired,
            (SELECT COUNT(*) FROM brands) as total_brands,
            (SELECT COUNT(*) FROM categories) as total_categories,
            (SELECT COUNT(*) FROM departments) as total_departments
    ";

    $statsResult = $conn->query($statsQuery);
    if ($statsResult) {
        $statsRow = $statsResult->fetch_assoc();
        if ($statsRow) {
            $stats = [
                'total_items' => (int) ($statsRow['total_items'] ?? 0),
                'total_users' => (int) ($statsRow['total_users'] ?? 0),
                'unassigned_devices' => (int) ($statsRow['unassigned_devices'] ?? 0),
                'faulty_devices' => (int) ($statsRow['faulty_devices'] ?? 0),
                'active_users' => (int) ($statsRow['active_users'] ?? 0),
                'admin_users' => (int) ($statsRow['admin_users'] ?? 0),
                'today_changes' => (int) ($statsRow['today_changes'] ?? 0),
                'in_use' => (int) ($statsRow['in_use'] ?? 0),
                'in_storage' => (int) ($statsRow['in_storage'] ?? 0),
                'repairing' => (int) ($statsRow['repairing'] ?? 0),
                'retired' => (int) ($statsRow['retired'] ?? 0),
                'total_brands' => (int) ($statsRow['total_brands'] ?? 0),
                'total_categories' => (int) ($statsRow['total_categories'] ?? 0),
                'total_departments' => (int) ($statsRow['total_departments'] ?? 0)
            ];
        }
    }

    // Fetch device status statistics for chart
    $statusQuery = "
        SELECT 
            status,
            COUNT(*) as count
        FROM inventory_items 
        WHERE status IS NOT NULL 
        GROUP BY status
        ORDER BY count DESC
    ";

    $statusResult = $conn->query($statusQuery);
    if ($statusResult) {
        while ($row = $statusResult->fetch_assoc()) {
            $deviceStatusStats[] = [
                'status' => $row['status'],
                'count' => (int) $row['count'],
                'label' => getStatusLabel($row['status'])
            ];
        }
    }

    // Fetch device condition statistics for chart
    $conditionQuery = "
        SELECT 
            `condition`,
            COUNT(*) as count
        FROM inventory_items 
        WHERE `condition` IS NOT NULL 
        GROUP BY `condition`
        ORDER BY count DESC
    ";

    $conditionResult = $conn->query($conditionQuery);
    if ($conditionResult) {
        while ($row = $conditionResult->fetch_assoc()) {
            $deviceConditionStats[] = [
                'condition' => $row['condition'],
                'count' => (int) $row['count'],
                'label' => CONDITION_LABELS[$row['condition']] ?? ucfirst($row['condition'])
            ];
        }
    }

    // Fetch top brands
    $brandsQuery = "
        SELECT 
            b.brand_name,
            COUNT(i.id) as device_count
        FROM inventory_items i
        JOIN brands b ON i.brand_id = b.id
        GROUP BY b.id, b.brand_name
        ORDER BY device_count DESC
        LIMIT 5
    ";

    $brandsResult = $conn->query($brandsQuery);
    if ($brandsResult) {
        while ($row = $brandsResult->fetch_assoc()) {
            $topBrands[] = [
                'brand_name' => $row['brand_name'],
                'device_count' => (int) $row['device_count']
            ];
        }
    }

    // Fetch top categories
    $categoriesQuery = "
        SELECT 
            c.category_name,
            COUNT(i.id) as device_count
        FROM inventory_items i
        JOIN categories c ON i.category_id = c.id
        GROUP BY c.id, c.category_name
        ORDER BY device_count DESC
        LIMIT 5
    ";

    $categoriesResult = $conn->query($categoriesQuery);
    if ($categoriesResult) {
        while ($row = $categoriesResult->fetch_assoc()) {
            $topCategories[] = [
                'category_name' => $row['category_name'],
                'device_count' => (int) $row['device_count']
            ];
        }
    }

    // Fetch Departments
    $deptQuery = "SELECT id, department_name FROM departments ORDER BY department_name";
    $deptResult = $conn->query($deptQuery);
    if ($deptResult) {
        while ($row = $deptResult->fetch_assoc()) {
            $departmentsArr[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Store stats in variables
$totalItems = $stats['total_items'];
$totalUsers = $stats['total_users'];
$unassignedDevices = $stats['unassigned_devices'];
$faultyDevices = $stats['faulty_devices'];
$activeUsers = $stats['active_users'];
$adminUsers = $stats['admin_users'];
$todayChanges = $stats['today_changes'];
$inUseDevices = $stats['in_use'];
$inStorageDevices = $stats['in_storage'];
$repairingDevices = $stats['repairing'];
$retiredDevices = $stats['retired'];
$totalBrands = $stats['total_brands'];
$totalCategories = $stats['total_categories'];
$totalDepartments = $stats['total_departments'];

/* ================== RECENT INVENTORY CHANGES ================== */

$recentActivities = [];

try {
    // Get recent inventory changes with user assignments
    $activitiesQuery = "
        SELECT 
            i.*,
            b.brand_name,
            d.department_name,
            c.category_name,
            u.firstname as assigned_firstname,
            u.lastname as assigned_lastname,
            u.email as assigned_email,
            u.role as assigned_role,
            dua.assigned_at,
            dua.returned_at,
            dua.status as assignment_status,
            
            CASE 
                WHEN i.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'new_device'
                WHEN dua.assigned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND dua.status = 'assigned' THEN 'assigned'
                WHEN dua.returned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'retrieved'
                WHEN i.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 
                    CASE 
                        WHEN i.status = 'retired' THEN 'retired'
                        WHEN i.status = 'faulty' THEN 'faulty'
                        WHEN i.status = 'repairing' THEN 'repairing'
                        ELSE 'updated'
                    END
                ELSE 'updated'
            END as activity_type
            
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN departments d ON i.department_id = d.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id 
            AND dua.status = 'assigned'
        LEFT JOIN users u ON dua.user_id = u.id
        WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           OR i.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           OR dua.assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           OR dua.returned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY GREATEST(
            COALESCE(i.created_at, '1970-01-01'),
            COALESCE(i.updated_at, '1970-01-01'),
            COALESCE(dua.assigned_at, '1970-01-01'),
            COALESCE(dua.returned_at, '1970-01-01')
        ) DESC
        LIMIT 100
    ";

    $activitiesResult = $conn->query($activitiesQuery);
    if ($activitiesResult) {
        while ($row = $activitiesResult->fetch_assoc()) {
            $activity = formatInventoryActivity($row);
            if ($activity) {
                $recentActivities[] = $activity;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent activities: " . $e->getMessage());
}

function formatInventoryActivity($row)
{
    global $statusLabels;

    $activityType = $row['activity_type'] ?? 'updated';

    // Determine activity details based on type
    switch ($activityType) {
        case 'new_device':
            $title = 'New Device Added';
            $icon = 'fa-plus-circle';
            $color = 'from-emerald-500 to-emerald-600';
            $description = 'New device has been added to inventory';
            break;

        case 'assigned':
            $title = 'Device Assigned';
            $icon = 'fa-user-check';
            $color = 'from-green-500 to-green-600';
            $description = !empty($row['assigned_firstname'])
                ? "Device assigned to " . e($row['assigned_firstname'] . ' ' . $row['assigned_lastname'])
                : "Device assigned to a user";
            break;

        case 'retrieved':
            $title = 'Device Retrieved';
            $icon = 'fa-undo';
            $color = 'from-red-500 to-red-600';
            $description = 'Device retrieved from user';
            break;

        case 'retired':
            $title = 'Device Retired';
            $icon = 'fa-archive';
            $color = 'from-red-500 to-red-600';
            $description = 'Device has been retired';
            break;

        case 'faulty':
            $title = 'Device Marked as Faulty';
            $icon = 'fa-exclamation-triangle';
            $color = 'from-red-400 to-red-500';
            $description = 'Device marked as faulty';
            break;

        case 'repairing':
            $title = 'Device Sent for Repair';
            $icon = 'fa-tools';
            $color = 'from-orange-500 to-orange-600';
            $description = 'Device sent for repair';
            break;

        case 'updated':
        default:
            $title = 'Device Updated';
            $icon = 'fa-edit';
            $color = 'from-blue-500 to-blue-600';
            $description = 'Device information was updated';
            break;
    }

    // Determine timestamp
    $activityTime = $row['created_at'];
    if ($activityType === 'assigned' && !empty($row['assigned_at'])) {
        $activityTime = $row['assigned_at'];
    } elseif ($activityType === 'retrieved' && !empty($row['returned_at'])) {
        $activityTime = $row['returned_at'];
    } elseif (!empty($row['updated_at']) && strtotime($row['updated_at']) > strtotime($row['created_at'])) {
        $activityTime = $row['updated_at'];
    }

    return [
        'id' => $row['id'] ?? null,
        'type' => $activityType,
        'title' => $title,
        'icon' => $icon,
        'color' => $color,
        'description' => $description,
        'device_name' => ($row['brand_name'] ?? '') . ' ' . ($row['model'] ?? ''),
        'asset_tag' => $row['asset_tag'] ?? 'N/A',
        'device_type' => $row['device_type'] ?? '',
        'model' => $row['model'] ?? '',
        'specifications' => $row['specifications'] ?? '',
        'condition' => $row['condition'] ?? '',
        'status' => $row['status'] ?? '',
        'status_label' => $statusLabels[$row['status']] ?? ucfirst($row['status'] ?? ''),
        'remarks' => $row['remarks'] ?? '',
        'category_name' => $row['category_name'] ?? '',
        'department_name' => $row['department_name'] ?? '',
        'assigned_user' => [
            'name' => !empty($row['assigned_firstname']) ? $row['assigned_firstname'] . ' ' . $row['assigned_lastname'] : '',
            'email' => $row['assigned_email'] ?? '',
            'role' => $row['assigned_role'] ?? ''
        ],
        'updated_at' => $activityTime,
        'is_new' => (time() - strtotime($activityTime)) < 300 // New if less than 5 minutes
    ];
}

/* ================== PAGINATION ================== */

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$totalActivities = count($recentActivities);
$totalPages = ceil($totalActivities / $perPage);
$offset = ($page - 1) * $perPage;

// Get current page activities
$currentPageActivities = array_slice($recentActivities, $offset, $perPage);

/* ================== ACTIVITY SUMMARY ================== */

// Calculate activity summary
$activitySummary = [
    'new_device' => ['icon' => 'fa-plus-circle', 'color' => 'bg-emerald-100 text-emerald-700', 'label' => 'New Devices', 'count' => 0],
    'assigned' => ['icon' => 'fa-user-check', 'color' => 'bg-green-100 text-green-700', 'label' => 'Assignments', 'count' => 0],
    'retrieved' => ['icon' => 'fa-undo', 'color' => 'bg-yellow-100 text-yellow-700', 'label' => 'Retrieved', 'count' => 0],
    'retired' => ['icon' => 'fa-archive', 'color' => 'bg-gray-100 text-gray-700', 'label' => 'Retired', 'count' => 0],
    'faulty' => ['icon' => 'fa-exclamation-triangle', 'color' => 'bg-red-100 text-red-700', 'label' => 'Faulty', 'count' => 0],
    'repairing' => ['icon' => 'fa-tools', 'color' => 'bg-orange-100 text-orange-700', 'label' => 'Repairing', 'count' => 0],
    'updated' => ['icon' => 'fa-edit', 'color' => 'bg-blue-100 text-blue-700', 'label' => 'Updated', 'count' => 0],
];

// Count each activity type
foreach ($recentActivities as $activity) {
    $type = $activity['type'] ?? 'updated';
    if (isset($activitySummary[$type])) {
        $activitySummary[$type]['count']++;
    } else {
        $activitySummary['updated']['count']++;
    }
}

// Prepare chart data
$chartData = [];
$chartLabels = [];
$chartColors = [];

foreach ($activitySummary as $type => $summary) {
    if ($summary['count'] > 0) {
        $chartData[] = $summary['count'];
        $chartLabels[] = $summary['label'];

        // Assign colors based on type
        switch ($type) {
            case 'new_device':
                $chartColors[] = 'rgba(16, 185, 129, 0.8)';
                break;
            case 'assigned':
                $chartColors[] = 'rgba(34, 197, 94, 0.8)';
                break;
            case 'retrieved':
                $chartColors[] = 'rgba(245, 158, 11, 0.8)';
                break;
            case 'retired':
                $chartColors[] = 'rgba(107, 114, 128, 0.8)';
                break;
            case 'faulty':
                $chartColors[] = 'rgba(239, 68, 68, 0.8)';
                break;
            case 'repairing':
                $chartColors[] = 'rgba(249, 115, 22, 0.8)';
                break;
            default:
                $chartColors[] = 'rgba(59, 130, 246, 0.8)';
        }
    }
}

// Prepare chart data for JavaScript
$deviceStatusChartData = [
    'labels' => [],
    'data' => [],
    'colors' => []
];

foreach ($deviceStatusStats as $stat) {
    $deviceStatusChartData['labels'][] = $stat['label']; // Use the label from getStatusLabel()
    $deviceStatusChartData['data'][] = $stat['count'];

    // Assign colors based on status
    switch ($stat['status']) {
        case 'in_use':
            $deviceStatusChartData['colors'][] = 'rgba(34, 197, 94, 0.8)'; // Green
            break;
        case 'in_storage':
            $deviceStatusChartData['colors'][] = 'rgba(245, 158, 11, 0.8)'; // Yellow
            break;
        case 'faulty':
            $deviceStatusChartData['colors'][] = 'rgba(239, 68, 68, 0.8)'; // Red
            break;
        case 'repairing':
            $deviceStatusChartData['colors'][] = 'rgba(249, 115, 22, 0.8)'; // Orange
            break;
        case 'retired':
            $deviceStatusChartData['colors'][] = 'rgba(107, 114, 128, 0.8)'; // Gray
            break;
        case 'active':
            $deviceStatusChartData['colors'][] = 'rgba(59, 130, 246, 0.8)'; // Blue
            break;
        default:
            $deviceStatusChartData['colors'][] = 'rgba(156, 163, 175, 0.8)'; // Default gray
    }
}

$deviceConditionChartData = [
    'labels' => [],
    'data' => [],
    'colors' => []
];

foreach ($deviceConditionStats as $stat) {
    $deviceConditionChartData['labels'][] = $stat['label'];
    $deviceConditionChartData['data'][] = $stat['count'];

    // Assign colors based on condition
    switch ($stat['condition']) {
        case 'New':
            $deviceConditionChartData['colors'][] = 'rgba(59, 130, 246, 0.8)'; // Blue
            break;
        case 'Good':
            $deviceConditionChartData['colors'][] = 'rgba(34, 197, 94, 0.8)'; // Green
            break;
        case 'Fair':
            $deviceConditionChartData['colors'][] = 'rgba(245, 158, 11, 0.8)'; // Yellow
            break;
        case 'Poor':
            $deviceConditionChartData['colors'][] = 'rgba(249, 115, 22, 0.8)'; // Orange
            break;
        case 'Faulty':
            $deviceConditionChartData['colors'][] = 'rgba(239, 68, 68, 0.8)'; // Red
            break;
        default:
            $deviceConditionChartData['colors'][] = 'rgba(156, 163, 175, 0.8)'; // Default gray
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Dashboard - Real-time Activity</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulseGlow {

            0%,
            100% {
                box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
            }

            50% {
                box-shadow: 0 0 20px rgba(59, 130, 246, 0.8);
            }
        }

        @keyframes highlightNew {
            0% {
                background-color: rgba(59, 130, 246, 0.2);
                border-left-color: #3b82f6;
            }

            100% {
                background-color: transparent;
                border-left-color: transparent;
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .animate-pulse-glow {
            animation: pulseGlow 2s infinite;
        }

        .animate-highlight-new {
            animation: highlightNew 3s ease-out;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .user-status-active {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .user-status-inactive {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }

        .activity-item {
            position: relative;
        }

        .activity-item:not(:last-child):after {
            content: '';
            position: absolute;
            left: 24px;
            top: 60px;
            bottom: -20px;
            width: 2px;
            background: linear-gradient(to bottom, #e5e7eb, transparent);
        }

        .realtime-indicator {
            position: relative;
        }

        .realtime-indicator:after {
            content: '';
            position: absolute;
            top: -3px;
            right: -3px;
            width: 12px;
            height: 12px;
            background: #10b981;
            border-radius: 50%;
            border: 2px solid white;
        }

        .live-pulse:after {
            animation: pulseGlow 1.5s infinite;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-in_use {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-in_storage {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-repairing {
            background-color: #fed7aa;
            color: #9a3412;
        }

        .status-faulty {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-retired {
            background-color: #e5e7eb;
            color: #374151;
        }

        .status-active {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .condition-New {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .condition-Good {
            background-color: #d1fae5;
            color: #065f46;
        }

        .condition-Fair {
            background-color: #fef3c7;
            color: #92400e;
        }

        .condition-Poor {
            background-color: #fed7aa;
            color: #9a3412;
        }

        .condition-Faulty {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .device-specs {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .brand-progress {
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-4 md:p-8 ml-0 md:ml-64">

        <!-- Header -->
        <div class="mb-8 animate-fade-in-up">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-6">
                <!-- Left Section: Title & Info -->
                <div class="flex-1">
                    <h1
                        class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Inventory Dashboard
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <div class="flex items-center gap-2 text-gray-600">
                            <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center">
                                <i class="fas fa-calendar-day text-blue-500 text-xs"></i>
                            </div>
                            <span><?= date('l, F j, Y') ?></span>
                        </div>
                        <span class="text-gray-300">•</span>
                        <div
                            class="realtime-indicator live-pulse flex items-center gap-2 px-3 py-1.5 bg-emerald-50 rounded-full">
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span class="text-emerald-700 font-medium">Live Inventory Tracking</span>
                        </div>
                    </div>
                </div>

                <!-- Right Section: Action Buttons -->
                <div class="flex items-center gap-3">
                    <!-- Admin Badge -->
                    <div
                        class="px-4 py-2 bg-blue-600 text-white rounded-xl shadow-lg flex items-center gap-2 hover:shadow-xl transition-shadow">
                        <i class="fas fa-shield-alt"></i>
                        <span class="font-semibold text-sm">ADMIN</span>
                    </div>

                    <!-- Refresh Button -->
                    <button onclick="refreshData()"
                        class="group w-10 h-10 rounded-xl bg-emerald-600 text-white shadow-md hover:shadow-lg transition-all flex items-center justify-center hover:scale-105 active:scale-95"
                        title="Refresh dashboard">
                        <i class="fas fa-sync-alt group-hover:rotate-180 transition-transform duration-500"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <?php
            $mainStats = [
                [
                    'title' => 'Total Number of Devices',
                    'value' => $totalItems,
                    'icon' => 'fa-laptop',
                    'gradient' => 'from-blue-500 to-blue-600',
                    'change' => '+12',
                    'id' => 'totalDevices'
                ],
                [
                    'title' => 'Current Devices In Use',
                    'value' => $inUseDevices,
                    'icon' => 'fa-user-check',
                    'gradient' => 'from-green-500 to-green-600',
                    'change' => '+5',
                    'id' => 'inUse'
                ],
                [
                    'title' => 'Total Number of In Storage',
                    'value' => $inStorageDevices,
                    'icon' => 'fa-warehouse',
                    'gradient' => 'from-yellow-500 to-yellow-600',
                    'change' => '+3',
                    'id' => 'inStorage'
                ],
                [
                    'title' => 'Total Unassigned/Stored Devices',
                    'value' => $unassignedDevices,
                    'icon' => 'fa-user-slash',
                    'gradient' => 'from-cyan-500 to-cyan-600',
                    'change' => $unassignedDevices > 0 ? '+' . $unassignedDevices : '0',
                    'id' => 'unassigned',
                    'subtitle' => 'Includes: Stored (' . $inStorageDevices . ') + Retired (' . $retiredDevices . ')'
                ],
            ];
            ?>

            <?php foreach ($mainStats as $index => $stat):
                $isPositive = strpos($stat['change'], '+') === 0;
                $trendColor = $isPositive ? 'text-green-600' : 'text-gray-400';
                $trendIcon = $isPositive ? 'fa-arrow-up' : 'fa-minus';
                ?>
                <div id="<?= $stat['id'] ?>"
                    class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up"
                    style="animation-delay: <?= $index * 0.1 ?>s;">

                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-3">
                                <div
                                    class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $stat['gradient'] ?> flex items-center justify-center shadow-lg">
                                    <i class="fas <?= $stat['icon'] ?> text-white text-xl"></i>
                                </div>
                            </div>

                            <p class="text-sm text-gray-500 font-medium mb-1">
                                <?= e($stat['title']) ?>
                            </p>

                            <p class="text-3xl font-bold text-gray-800">
                                <?= number_format($stat['value']) ?>
                            </p>

                            <?php if (isset($stat['subtitle'])): ?>
                                <div class="mt-2">
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= e($stat['subtitle']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($stat['change'] !== '0'): ?>
                                <div class="mt-3 flex items-center gap-1">
                                    <span class="text-xs font-semibold flex items-center gap-1 <?= $trendColor ?>">
                                        <i class="fas <?= $trendIcon ?>"></i>
                                        <?= $stat['change'] ?>
                                    </span>
                                    <span class="text-xs text-gray-400">this week</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <!-- Repairing -->
            <div
                class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-tools text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-medium mb-1">Repairing</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($repairingDevices) ?></p>
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-xs text-gray-400">Under maintenance</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Faulty -->
            <div
                class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-medium mb-1">Faulty</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($faultyDevices) ?></p>
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-xs text-gray-400">Require attention</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Retired -->
            <div
                class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-archive text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-medium mb-1">Retired</p>
                        <p class="text-3xl font-bold text-gray-800">
                            <?= number_format($retiredDevices) ?>
                        </p>
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-xs text-gray-400">Decommissioned</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Users -->
            <div
                class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-medium mb-1">Active Users</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($activeUsers) ?></p>
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-xs text-gray-400"><?= number_format($adminUsers) ?> admins</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Changes -->
            <div
                class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-history text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-medium mb-1">Today's Changes</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($todayChanges) ?></p>
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-xs text-gray-400">Activities today</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts & Overview Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Device Status Distribution -->
            <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-pie text-blue-500"></i>
                    Device Status Distribution
                </h3>
                <div class="chart-container">
                    <canvas id="deviceStatusChart"></canvas>
                </div>
                <?php if (empty($deviceStatusChartData['data'])): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500">No device status data</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Device Condition Distribution -->
            <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-star-half-alt text-amber-500"></i>
                    Device Condition Distribution
                </h3>
                <div class="chart-container">
                    <canvas id="deviceConditionChart"></canvas>
                </div>
                <?php if (empty($deviceConditionChartData['data'])): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500">No device condition data</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Brands & Categories -->
            <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-crown text-purple-500"></i>
                    Top Brands & Categories
                </h3>

                <!-- Top Brands -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Top Brands</h4>
                    <div class="space-y-3">
                        <?php if (empty($topBrands)): ?>
                            <p class="text-gray-500 text-sm">No brand data available</p>
                        <?php else: ?>
                            <?php
                            $maxBrandCount = !empty($topBrands) ? max(array_column($topBrands, 'device_count')) : 1;
                            ?>
                            <?php foreach ($topBrands as $brand): ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="font-medium text-gray-700"><?= e($brand['brand_name']) ?></span>
                                        <span class="text-gray-600"><?= $brand['device_count'] ?> devices</span>
                                    </div>
                                    <div class="brand-progress"
                                        style="width: <?= ($brand['device_count'] / $maxBrandCount) * 100 ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Categories -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Top Categories</h4>
                    <div class="space-y-3">
                        <?php if (empty($topCategories)): ?>
                            <p class="text-gray-500 text-sm">No category data available</p>
                        <?php else: ?>
                            <?php
                            $maxCatCount = !empty($topCategories) ? max(array_column($topCategories, 'device_count')) : 1;
                            ?>
                            <?php foreach ($topCategories as $category): ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="font-medium text-gray-700"><?= e($category['category_name']) ?></span>
                                        <span class="text-gray-600"><?= $category['device_count'] ?> devices</span>
                                    </div>
                                    <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 h-2 rounded-full"
                                        style="width: <?= ($category['device_count'] / $maxCatCount) * 100 ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Stream Header -->
        <div class="glass-effect rounded-2xl shadow-lg p-6 mb-6 border border-gray-100">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-history text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Recent Inventory Activity</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <span class="font-semibold text-blue-600"><?= number_format($totalActivities) ?></span>
                            activities in last 7 days • Showing latest updates
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div class="relative flex-1 lg:flex-initial">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input id="searchInput" type="text" placeholder="Search devices, users, activities..."
                            autocomplete="off"
                            class="w-full lg:w-80 pl-11 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="flex gap-2">
                        <button onclick="toggleAutoRefresh()" id="autoRefreshBtn"
                            class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl text-sm font-medium hover:shadow-lg transition-all flex items-center gap-2">
                            <i class="fas fa-sync"></i> Auto-refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100 mb-8">
            <div class="p-6">
                <div class="space-y-6" id="activityTimeline">
                    <?php if (empty($currentPageActivities)): ?>
                        <div class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="fas fa-inbox text-4xl text-gray-300"></i>
                                </div>
                                <p class="text-gray-400 font-medium">No recent activity</p>
                                <p class="text-xs text-gray-400">Activity will appear here as inventory is updated</p>
                                </div>
                        </div>
                    <?php else:
                        foreach ($currentPageActivities as $index => $activity):
                            $timeAgo = date('M j, Y g:i A', strtotime($activity['updated_at']));
                            $assignedUser = $activity['assigned_user'];
                            $isNew = $activity['is_new'];
                            $newClass = $isNew ? 'animate-highlight-new border-l-4 border-l-blue-500 pl-3' : '';
                            ?>
                            <div class="activity-item flex gap-4 <?= $newClass ?>"
                                data-activity-type="<?= e($activity['type']) ?>">
                                <!-- Activity Icon -->
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-12 h-12 rounded-full bg-gradient-to-br <?= $activity['color'] ?> flex items-center justify-center shadow-lg">
                                        <i class="fas <?= $activity['icon'] ?> text-white text-lg"></i>
                                    </div>
                                </div>

                                <!-- Activity Content -->
                                <div
                                    class="flex-1 bg-gray-50 rounded-xl p-4 border border-gray-200 hover:bg-gray-100 transition-colors">
                                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                        <div class="flex-1">
                                            <!-- Activity Header -->
                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                <h3 class="font-bold text-lg text-gray-900">
                                                    <?= e($activity['title']) ?>
                                                </h3>
                                                <span
                                                    class="text-sm px-3 py-1 rounded-lg bg-blue-100 text-blue-700 font-semibold">
                                                    <?= e($activity['asset_tag']) ?>
                                                </span>
                                                <?php if (!empty($activity['status'])): ?>
                                                    <span class="status-badge status-<?= e($activity['status']) ?>">
                                                        <i class="fas fa-circle text-[10px]"></i>
                                                        <?= e($activity['status_label']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($activity['condition'])): ?>
                                                    <span class="condition-badge condition-<?= e($activity['condition']) ?>">
                                                        <i class="fas fa-certificate text-[10px]"></i>
                                                        <?= CONDITION_LABELS[$activity['condition']] ?? ucfirst($activity['condition']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($isNew): ?>
                                                    <span
                                                        class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700 font-semibold animate-pulse">
                                                        <i class="fas fa-star mr-1"></i>NEW
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Activity Description -->
                                            <p class="text-gray-700 mb-3">
                                                <i class="fas <?= $activity['icon'] ?> text-gray-400 mr-2"></i>
                                                <?= e($activity['description']) ?>
                                            </p>

                                            <!-- Device Details -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-3">
                                                <?php if (!empty($activity['device_type'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-tag text-gray-400"></i>
                                                        <span class="font-medium">Type:</span>
                                                        <span class="text-gray-700"><?= e($activity['device_type']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($activity['model'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-microchip text-gray-400"></i>
                                                        <span class="font-medium">Model:</span>
                                                        <span class="text-gray-700"><?= e($activity['model']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($activity['category_name'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-folder text-gray-400"></i>
                                                        <span class="font-medium">Category:</span>
                                                        <span class="text-gray-700"><?= e($activity['category_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($activity['department_name'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-building text-gray-400"></i>
                                                        <span class="font-medium">Dept:</span>
                                                        <span class="text-gray-700"><?= e($activity['department_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Specifications -->
                                            <?php if (!empty($activity['specifications'])): ?>
                                                <div class="mb-3">
                                                    <p class="text-xs text-gray-500 font-medium mb-1">Specifications:</p>
                                                    <p class="text-sm text-gray-700 device-specs">
                                                        <?= e($activity['specifications']) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <!-- User Assignment Info -->
                                            <?php if (!empty($assignedUser['name']) && in_array($activity['type'], ['assigned'])): ?>
                                                <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-sm font-bold shadow-sm">
                                                        <?= strtoupper(substr($assignedUser['name'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-800">Assigned to:
                                                            <?= e($assignedUser['name']) ?>
                                                        </p>
                                                        <?php if (!empty($assignedUser['email'])): ?>
                                                            <p class="text-xs text-gray-500"><?= e($assignedUser['email']) ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($assignedUser['role'])): ?>
                                                            <span
                                                                class="text-xs px-2 py-1 rounded-full <?= $assignedUser['role'] === 'admin' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' ?>">
                                                                <?= e($assignedUser['role']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php elseif ($activity['type'] === 'new_device'): ?>
                                                <div
                                                    class="flex items-center gap-3 p-3 bg-emerald-50 rounded-lg border border-emerald-200">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 text-sm font-bold shadow-sm">
                                                        <i class="fas fa-plus"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-emerald-800">New device added to inventory</p>
                                                        <p class="text-xs text-emerald-600">Ready for assignment</p>
                                                    </div>
                                                </div>
                                            <?php elseif ($activity['type'] === 'retired'): ?>
                                                <div
                                                    class="flex items-center gap-3 p-3 bg-gray-100 rounded-lg border border-gray-200">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-700 text-sm font-bold shadow-sm">
                                                        <i class="fas fa-archive"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-800">Device has been retired</p>
                                                        <p class="text-xs text-gray-600">No longer available</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Timestamp -->
                                        <div class="flex flex-col items-end gap-2">
                                            <span class="text-xs text-gray-500 whitespace-nowrap"
                                                title="<?= e($activity['updated_at']) ?>">
                                                <i class="fas fa-clock mr-1"></i><?= $timeAgo ?>
                                            </span>
                                            <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-700 capitalize">
                                                <?= str_replace('_', ' ', $activity['type']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-blue-50 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            Showing <span class="font-semibold text-blue-600"><?= $offset + 1 ?></span> to
                            <span
                                class="font-semibold text-blue-600"><?= min($offset + $perPage, $totalActivities) ?></span>
                            of
                            <span class="font-semibold text-blue-600"><?= number_format($totalActivities) ?></span>
                            activities
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 hover:shadow-md transition-all font-medium">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++):
                                $activeClass = $i === $page
                                    ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-lg'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 hover:shadow-md';
                                ?>
                                <a href="?page=<?= $i ?>"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-all font-medium <?= $activeClass ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 hover:shadow-md transition-all font-medium">
                                    Next <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center h-full">
            <div class="bg-white rounded-2xl p-8 flex flex-col items-center gap-4">
                <div class="w-16 h-16 rounded-full border-4 border-blue-200 border-t-blue-600 animate-spin"></div>
                <p class="text-gray-700 font-medium">Refreshing data...</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <!-- JavaScript -->
    <script>
        // Device Status Chart Data
        const deviceStatusData = {
            labels: <?= json_encode($deviceStatusChartData['labels']) ?>,
            data: <?= json_encode($deviceStatusChartData['data']) ?>,
            colors: <?= json_encode($deviceStatusChartData['colors']) ?>
        };

        // Device Condition Chart Data
        const deviceConditionData = {
            labels: <?= json_encode($deviceConditionChartData['labels']) ?>,
            data: <?= json_encode($deviceConditionChartData['data']) ?>,
            colors: <?= json_encode($deviceConditionChartData['colors']) ?>
        };

        // Initialize Device Status Chart
        if (deviceStatusData.data.length > 0) {
            const statusCtx = document.getElementById('deviceStatusChart').getContext('2d');
            const deviceStatusChart = new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: deviceStatusData.labels,
                    datasets: [{
                        data: deviceStatusData.data,
                        backgroundColor: deviceStatusData.colors,
                        borderColor: deviceStatusData.colors.map(color => color.replace('0.8', '1')),
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} devices (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize Device Condition Chart
        if (deviceConditionData.data.length > 0) {
            const conditionCtx = document.getElementById('deviceConditionChart').getContext('2d');
            const deviceConditionChart = new Chart(conditionCtx, {
                type: 'doughnut',
                data: {
                    labels: deviceConditionData.labels,
                    datasets: [{
                        data: deviceConditionData.data,
                        backgroundColor: deviceConditionData.colors,
                        borderColor: deviceConditionData.colors.map(color => color.replace('0.8', '1')),
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} devices (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Live search with debounce
        const searchInput = document.getElementById('searchInput');
        let searchTimer;

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                const query = searchInput.value.toLowerCase();
                const activities = document.querySelectorAll('.activity-item');

                activities.forEach(activity => {
                    const text = activity.textContent.toLowerCase();
                    activity.style.display = text.includes(query) ? '' : 'none';
                });
            }, 300);
        });

        // Auto-refresh functionality
        let autoRefreshInterval = null;
        let autoRefreshEnabled = false;

        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');

            if (autoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                autoRefreshEnabled = false;
                btn.innerHTML = '<i class="fas fa-sync"></i> Auto-refresh';
                btn.classList.remove('animate-pulse-glow');
                showToast('Auto-refresh disabled', 'info');
            } else {
                autoRefreshEnabled = true;
                autoRefreshInterval = setInterval(refreshData, 30000); // Every 30 seconds
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auto-refresh ON';
                btn.classList.add('animate-pulse-glow');
                showToast('Auto-refresh enabled (30s)', 'success');
            }
        }

        // Refresh data function
        function refreshData() {
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('hidden');

            // Reload the page
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-6 py-3 rounded-xl shadow-lg z-50 transform transition-all duration-300 translate-x-full ${type === 'success' ? 'bg-emerald-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'} text-white`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 10);

            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Show welcome toast if there are activities
            if (<?= $totalActivities ?> > 0) {
                setTimeout(() => {
                    showToast('Loaded <?= $totalActivities ?> recent activities', 'info');
                }, 1000);
            }

            // Remove new activity highlights after 5 seconds
            setTimeout(() => {
                document.querySelectorAll('.animate-highlight-new').forEach(el => {
                    el.classList.remove('animate-highlight-new', 'border-l-4', 'border-l-blue-500', 'pl-3');
                });
            }, 5000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshData();
            }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
            }
            if (e.key === 'Escape') {
                searchInput.value = '';
                document.querySelectorAll('.activity-item').forEach(el => {
                    el.style.display = '';
                });
            }
        });

        // Filter by activity type (optional feature)
        function filterByType(type) {
            document.querySelectorAll('.activity-item').forEach(el => {
                const activityType = el.getAttribute('data-activity-type');
                el.style.display = (!type || activityType === type) ? '' : 'none';
            });
        }
    </script>

</body>

</html>