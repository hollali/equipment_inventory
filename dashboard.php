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
    // Get inventory statistics
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM inventory_items) as total_items,
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM inventory_items 
             WHERE (assigned_user IS NULL OR assigned_user = 0 
                    OR status IN ('in_storage', 'retired'))) as unassigned_devices,
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
    // Get recent inventory changes with user assignments - SIMPLIFIED VERSION
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
            dua.status as assignment_status
            
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

    // Determine activity type based on timestamps
    $activityType = 'updated'; // default

    // Check if this is a new device (created in last 24 hours)
    if (strtotime($row['created_at']) >= strtotime('-24 hours')) {
        $activityType = 'new_device';
    }
    // Check if assigned in last 24 hours
    elseif (!empty($row['assigned_at']) && strtotime($row['assigned_at']) >= strtotime('-24 hours')) {
        $activityType = 'assigned';
    }
    // Check if retrieved in last 24 hours
    elseif (!empty($row['returned_at']) && strtotime($row['returned_at']) >= strtotime('-24 hours')) {
        $activityType = 'retrieved';
    }
    // Check if updated in last 24 hours
    elseif (strtotime($row['updated_at']) >= strtotime('-24 hours')) {
        // Check the current status to determine specific update type
        switch ($row['status']) {
            case 'retired':
                $activityType = 'retired';
                break;
            case 'faulty':
                $activityType = 'faulty';
                break;
            case 'repairing':
                $activityType = 'repairing';
                break;
            case 'in_storage':
                $activityType = 'stored';
                break;
            case 'in_use':
                // Check if it's an assignment or just status change
                if (!empty($row['assigned_at']) && strtotime($row['assigned_at']) >= strtotime('-24 hours')) {
                    $activityType = 'assigned';
                } else {
                    $activityType = 'put_in_use';
                }
                break;
            default:
                $activityType = 'updated';
        }
    }

    // Device details
    $deviceName = ($row['brand_name'] ?? '') . ' ' . ($row['model'] ?? '');
    $assetTag = $row['asset_tag'] ?? 'N/A';

    // Determine activity details based on type
    switch ($activityType) {
        case 'new_device':
            $title = 'New Device Added';
            $icon = 'fa-plus-circle';
            $color = 'from-emerald-500 to-emerald-600';
            $description = "New device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") has been added to inventory";
            break;

        case 'assigned':
            $title = 'Device Assigned';
            $icon = 'fa-user-check';
            $color = 'from-green-500 to-green-600';
            if (!empty($row['assigned_firstname'])) {
                $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") assigned to " .
                    e($row['assigned_firstname'] . ' ' . $row['assigned_lastname']);
            } else {
                $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") assigned to a user";
            }
            break;

        case 'retrieved':
            $title = 'Device Retrieved';
            $icon = 'fa-undo';
            $color = 'from-red-500 to-red-600';
            $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") retrieved from user";
            break;

        case 'retired':
            $title = 'Device Retired';
            $icon = 'fa-archive';
            $color = 'from-red-500 to-red-600';
            $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") has been retired and decommissioned";
            break;

        case 'faulty':
            $title = 'Device Marked as Faulty';
            $icon = 'fa-exclamation-triangle';
            $color = 'from-red-400 to-red-500';
            $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") marked as faulty and requires attention";
            break;

        case 'repairing':
            $title = 'Device Sent for Repair';
            $icon = 'fa-tools';
            $color = 'from-orange-500 to-orange-600';
            $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") sent for repair/maintenance";
            break;

        case 'stored':
            $title = 'Device Placed in Storage';
            $icon = 'fa-warehouse';
            $color = 'from-yellow-500 to-yellow-600';
            $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") placed in storage";
            break;

        case 'put_in_use':
            $title = 'Device Put in Use';
            $icon = 'fa-play-circle';
            $color = 'from-blue-500 to-blue-600';
            $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") marked as in use";
            break;

        case 'updated':
        default:
            $title = 'Device Updated';
            $icon = 'fa-edit';
            $color = 'from-blue-500 to-blue-600';
            $description = "Device <strong>" . e($deviceName) . "</strong> (Asset: " . e($assetTag) . ") information was updated";
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
        'device_name' => $deviceName,
        'asset_tag' => $assetTag,
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
    'stored' => ['icon' => 'fa-warehouse', 'color' => 'bg-amber-100 text-amber-700', 'label' => 'Stored', 'count' => 0],
    'put_in_use' => ['icon' => 'fa-play-circle', 'color' => 'bg-blue-100 text-blue-700', 'label' => 'Put in Use', 'count' => 0],
    'updated' => ['icon' => 'fa-edit', 'color' => 'bg-indigo-100 text-indigo-700', 'label' => 'Updated', 'count' => 0],
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Management Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

   <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    * {
        font-family: 'Inter', sans-serif;
    }

    /* Smooth transitions */
    .transition-all {
        transition: all 0.2s ease-in-out;
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Card hover effects */
    .stats-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
    }

    /* Status badges with colors */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-in_use {
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #dcfce7;
    }
    
    .status-in_storage {
        background-color: #fefce8;
        color: #854d0e;
        border: 1px solid #fef9c3;
    }
    
    .status-repairing {
        background-color: #fff7ed;
        color: #9a3412;
        border: 1px solid #ffedd5;
    }
    
    .status-faulty {
        background-color: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fee2e2;
    }
    
    .status-retired {
        background-color: #f9fafb;
        color: #374151;
        border: 1px solid #e5e7eb;
    }
    
    .status-active {
        background-color: #eff6ff;
        color: #1e40af;
        border: 1px solid #dbeafe;
    }

    /* Condition badges */
    .condition-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .condition-New {
        background-color: #eff6ff;
        color: #1e40af;
        border: 1px solid #dbeafe;
    }

    .condition-Good {
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #dcfce7;
    }

    .condition-Fair {
        background-color: #fefce8;
        color: #854d0e;
        border: 1px solid #fef9c3;
    }

    .condition-Poor {
        background-color: #fff7ed;
        color: #9a3412;
        border: 1px solid #ffedd5;
    }

    .condition-Faulty {
        background-color: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fee2e2;
    }

    /* Activity timeline */
    .timeline-item {
        position: relative;
    }

    .timeline-item:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 2rem;
        top: 4rem;
        bottom: -1rem;
        width: 2px;
        background: linear-gradient(to bottom, #e2e8f0, #f1f5f9);
    }

    /* New item highlight */
    @keyframes highlight {
        0% {
            background-color: rgba(59, 130, 246, 0.1);
        }
        100% {
            background-color: transparent;
        }
    }

    .highlight-new {
        animation: highlight 3s ease-out;
    }

    /* Live indicator */
    .live-indicator {
        position: relative;
    }

    .live-indicator::after {
        content: '';
        position: absolute;
        top: -2px;
        right: -2px;
        width: 8px;
        height: 8px;
        background-color: #10b981;
        border-radius: 50%;
        border: 2px solid white;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Loading spinner */
    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3b82f6;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Search input */
    .search-input {
        transition: all 0.2s;
    }

    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Button styles */
    .btn-primary {
        background-color: #2563eb;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
    }
    
    .btn-primary:hover {
        background-color: #1d4ed8;
    }
    
    .btn-primary:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.5);
    }

    .btn-outline {
        border: 1px solid #d1d5db;
        background-color: white;
        color: #374151;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        cursor: pointer;
    }
    
    .btn-outline:hover {
        background-color: #f9fafb;
    }
    
    .btn-outline:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(209, 213, 219, 0.5);
    }
</style>
</head>

<body class="bg-gray-50">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-6 lg:p-8 ml-0 lg:ml-64 transition-all duration-300">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Dashboard Overview</h1>
                    <p class="text-sm text-gray-500 mt-1">Real-time inventory monitoring and activity tracking</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow-sm border border-gray-200">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                        <span class="text-xs font-medium text-gray-600">Live Updates</span>
                    </div>
                    <button onclick="refreshData()" class="p-2 bg-white rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-sync-alt text-gray-600"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Devices -->
            <div class="stats-card bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Devices</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= number_format($totalItems) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Across all categories</p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <i class="fas fa-laptop text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-green-600">↑ 12%</span>
                        <span class="text-xs text-gray-400">from last month</span>
                    </div>
                </div>
            </div>

            <!-- In Use -->
            <div class="stats-card bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Devices In Use</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= number_format($inUseDevices) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Currently active</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg">
                        <i class="fas fa-user-check text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-600"><?= number_format($activeUsers) ?> active users</span>
                    </div>
                </div>
            </div>

            <!-- In Storage -->
            <div class="stats-card bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">In Storage</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= number_format($inStorageDevices) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Available for assignment</p>
                    </div>
                    <div class="p-3 bg-yellow-50 rounded-lg">
                        <i class="fas fa-warehouse text-yellow-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-600"><?= number_format($unassignedDevices) ?> unassigned</span>
                    </div>
                </div>
            </div>

            <!-- Needs Attention -->
            <div class="stats-card bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Needs Attention</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?= number_format($faultyDevices + $repairingDevices) ?></p>
                        <p class="text-xs text-gray-400 mt-1">Faulty or in repair</p>
                    </div>
                    <div class="p-3 bg-red-50 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-red-600"><?= number_format($faultyDevices) ?> faulty</span>
                        <span class="text-xs text-gray-400">•</span>
                        <span class="text-xs font-medium text-orange-600"><?= number_format($repairingDevices) ?> repairing</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-50 rounded-lg">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Users</p>
                        <p class="text-lg font-semibold text-gray-900"><?= number_format($totalUsers) ?></p>
                        <p class="text-xs text-gray-400"><?= number_format($adminUsers) ?> admins</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-orange-50 rounded-lg">
                        <i class="fas fa-tools text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Under Repair</p>
                        <p class="text-lg font-semibold text-gray-900"><?= number_format($repairingDevices) ?></p>
                        <p class="text-xs text-gray-400">Maintenance</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-gray-50 rounded-lg">
                        <i class="fas fa-archive text-gray-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Retired</p>
                        <p class="text-lg font-semibold text-gray-900"><?= number_format($retiredDevices) ?></p>
                        <p class="text-xs text-gray-400">Decommissioned</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-50 rounded-lg">
                        <i class="fas fa-calendar-day text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Today's Changes</p>
                        <p class="text-lg font-semibold text-gray-900"><?= number_format($todayChanges) ?></p>
                        <p class="text-xs text-gray-400">Activities</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
            <!-- Activity Header -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                        <p class="text-sm text-gray-500 mt-1">Latest inventory changes and updates</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 lg:flex-initial">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" id="searchInput" placeholder="Search activities..." 
                                class="search-input w-full lg:w-64 pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        <button onclick="toggleAutoRefresh()" id="autoRefreshBtn" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>Auto Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="divide-y divide-gray-100">
                <?php if (empty($currentPageActivities)): ?>
                    <div class="p-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                            <i class="fas fa-inbox text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500">No recent activities found</p>
                        <p class="text-sm text-gray-400 mt-1">Activities will appear here as changes occur</p>
                    </div>
                <?php else:
                    foreach ($currentPageActivities as $index => $activity):
                        $timeAgo = date('M j, Y g:i A', strtotime($activity['updated_at']));
                        $assignedUser = $activity['assigned_user'];
                        $isNew = $activity['is_new'];
                        
                        // Determine status class for coloring
                        $statusClass = '';
                        if (!empty($activity['status'])) {
                            $statusClass = 'status-' . str_replace('_', '-', $activity['status']);
                        }
                        
                        // Determine condition class
                        $conditionClass = '';
                        if (!empty($activity['condition'])) {
                            $conditionClass = 'condition-' . $activity['condition'];
                        }
                ?>
                    <div class="timeline-item relative p-6 hover:bg-gray-50 transition-colors <?= $isNew ? 'highlight-new' : '' ?>">
                        <div class="flex gap-4">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $activity['color'] ?> flex items-center justify-center">
                                    <i class="fas <?= $activity['icon'] ?> text-white"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap mb-2">
                                            <h3 class="font-semibold text-gray-900"><?= e($activity['title']) ?></h3>
                                            <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                                                <?= e($activity['asset_tag']) ?>
                                            </span>
                                            
                                            <!-- Status Badge with Color -->
                                            <?php if (!empty($activity['status'])): ?>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <i class="fas fa-circle text-[6px] mr-1"></i>
                                                    <?= e($activity['status_label']) ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Condition Badge with Color -->
                                            <?php if (!empty($activity['condition'])): ?>
                                                <span class="condition-badge <?= $conditionClass ?>">
                                                    <i class="fas fa-tag text-[10px] mr-1"></i>
                                                    <?= CONDITION_LABELS[$activity['condition']] ?? ucfirst($activity['condition']) ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($isNew): ?>
                                                <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                                                    <i class="fas fa-bolt mr-1"></i>New
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="text-sm text-gray-600 mb-3"><?= $activity['description'] ?></p>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                            <?php if (!empty($activity['device_type'])): ?>
                                                <div class="flex items-center gap-2 text-xs">
                                                    <i class="fas fa-tag text-gray-400"></i>
                                                    <span class="text-gray-500">Type:</span>
                                                    <span class="text-gray-900 font-medium"><?= e($activity['device_type']) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($activity['model'])): ?>
                                                <div class="flex items-center gap-2 text-xs">
                                                    <i class="fas fa-microchip text-gray-400"></i>
                                                    <span class="text-gray-500">Model:</span>
                                                    <span class="text-gray-900 font-medium"><?= e($activity['model']) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($activity['category_name'])): ?>
                                                <div class="flex items-center gap-2 text-xs">
                                                    <i class="fas fa-folder text-gray-400"></i>
                                                    <span class="text-gray-500">Category:</span>
                                                    <span class="text-gray-900 font-medium"><?= e($activity['category_name']) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($activity['department_name'])): ?>
                                                <div class="flex items-center gap-2 text-xs">
                                                    <i class="fas fa-building text-gray-400"></i>
                                                    <span class="text-gray-500">Department:</span>
                                                    <span class="text-gray-900 font-medium"><?= e($activity['department_name']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Specifications -->
                                        <?php if (!empty($activity['specifications'])): ?>
                                            <div class="text-xs text-gray-500 mb-3">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                <?= e($activity['specifications']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Assignment Info -->
                                        <?php if (!empty($assignedUser['name']) && in_array($activity['type'], ['assigned'])): ?>
                                            <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold">
                                                    <?= strtoupper(substr($assignedUser['name'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-900"><?= e($assignedUser['name']) ?></p>
                                                    <?php if (!empty($assignedUser['email'])): ?>
                                                        <p class="text-xs text-gray-500"><?= e($assignedUser['email']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Timestamp -->
                                    <div class="flex flex-col items-end gap-2">
                                        <span class="text-xs text-gray-400 whitespace-nowrap" title="<?= e($activity['updated_at']) ?>">
                                            <i class="far fa-clock mr-1"></i><?= $timeAgo ?>
                                        </span>
                                        <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full capitalize">
                                            <?= str_replace('_', ' ', $activity['type']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-xs text-gray-500">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalActivities) ?> of <?= number_format($totalActivities) ?> activities
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-chevron-left mr-1"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" class="px-3 py-1.5 text-xs <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?> border border-gray-200 rounded-lg transition-colors">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
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
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm z-50 hidden items-center justify-center">
        <div class="bg-white rounded-xl p-6 shadow-xl">
            <div class="spinner mx-auto"></div>
            <p class="text-sm text-gray-600 mt-3">Refreshing data...</p>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.timeline-item').forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(query) ? '' : 'none';
                });
            }, 300);
        });

        // Auto refresh
        let autoRefreshEnabled = false;
        let autoRefreshInterval;

        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');
            
            if (autoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                autoRefreshEnabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Auto Refresh';
                showToast('Auto refresh disabled', 'info');
            } else {
                autoRefreshEnabled = true;
                autoRefreshInterval = setInterval(refreshData, 30000);
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Auto Refresh ON';
                showToast('Auto refresh enabled (30s)', 'success');
            }
        }

        // Refresh data
        function refreshData() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full ${
                type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
                type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 
                'bg-blue-50 text-blue-800 border border-blue-200'
            }`;
            toast.innerHTML = `
                <div class="flex items-center gap-2">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    <span class="text-sm font-medium">${message}</span>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => toast.style.transform = 'translateX(0)', 10);
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                searchInput.focus();
            }
            if (e.key === 'Escape') {
                searchInput.value = '';
                document.querySelectorAll('.timeline-item').forEach(item => item.style.display = '');
            }
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshData();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($totalActivities > 0): ?>
            setTimeout(() => showToast('Loaded <?= $totalActivities ?> recent activities', 'info'), 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>