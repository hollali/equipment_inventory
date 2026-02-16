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
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    * {
        font-family: 'Inter', sans-serif;
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #94a3b8, #64748b);
        border-radius: 10px;
        border: 2px solid #f1f5f9;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #64748b, #475569);
    }

    /* Card styles with glassmorphism */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
    }

    /* Enhanced stats card */
    .stats-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .stats-card:hover::before {
        opacity: 1;
    }

    .stats-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px -10px rgba(31, 38, 135, 0.25);
    }

    /* Gradient text */
    .gradient-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Status badges with enhanced colors */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.025em;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .status-in_use {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    
    .status-in_storage {
        background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
        color: #854d0e;
        border: 1px solid #fef08a;
    }
    
    .status-repairing {
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        color: #9a3412;
        border: 1px solid #fed7aa;
    }
    
    .status-faulty {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    
    .status-retired {
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        color: #374151;
        border: 1px solid #e5e7eb;
    }
    
    .status-active {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }

    /* Condition badges with enhanced colors */
    .condition-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.025em;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .condition-New {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }

    .condition-Good {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .condition-Fair {
        background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
        color: #854d0e;
        border: 1px solid #fef08a;
    }

    .condition-Poor {
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        color: #9a3412;
        border: 1px solid #fed7aa;
    }

    .condition-Faulty {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    /* Enhanced timeline */
    .timeline-item {
        position: relative;
        transition: all 0.3s ease;
    }

    .timeline-item:hover {
        background: linear-gradient(135deg, rgba(255,255,255,0.8) 0%, rgba(249,250,251,0.8) 100%);
    }

    .timeline-item:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 2.5rem;
        top: 4.5rem;
        bottom: -1rem;
        width: 2px;
        background: linear-gradient(to bottom, #e2e8f0, transparent);
    }

    /* Icon container with shadow */
    .icon-container {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .timeline-item:hover .icon-container {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
    }

    /* New item highlight with shimmer */
    @keyframes shimmer {
        0% {
            background-position: -1000px 0;
        }
        100% {
            background-position: 1000px 0;
        }
    }

    .highlight-new {
        position: relative;
        overflow: hidden;
    }

    .highlight-new::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            90deg,
            rgba(59, 130, 246, 0) 0%,
            rgba(59, 130, 246, 0.1) 50%,
            rgba(59, 130, 246, 0) 100%
        );
        background-size: 1000px 100%;
        animation: shimmer 3s infinite;
    }

    /* Live indicator with pulse */
    @keyframes pulse-ring {
        0% {
            transform: scale(0.95);
            opacity: 1;
        }
        50% {
            transform: scale(1.05);
            opacity: 0.7;
        }
        100% {
            transform: scale(0.95);
            opacity: 1;
        }
    }

    .live-indicator {
        animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Enhanced search input */
    .search-input {
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        transform: translateY(-2px);
    }

    /* Button enhancements */
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        padding: 0.625rem 1.25rem;
        border-radius: 0.75rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }
    
    .btn-primary:active {
        transform: translateY(0);
    }

    .btn-outline {
        border: 2px solid #e5e7eb;
        background: white;
        color: #374151;
        padding: 0.625rem 1.25rem;
        border-radius: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .btn-outline:hover {
        background: #f9fafb;
        border-color: #d1d5db;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Loading spinner */
    .spinner {
        border: 4px solid #f3f4f6;
        border-top: 4px solid #3b82f6;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Stat icon gradient backgrounds */
    .stat-icon-blue {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .stat-icon-green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .stat-icon-yellow {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .stat-icon-red {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .stat-icon-purple {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }

    .stat-icon-orange {
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    }

    .stat-icon-gray {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    }

    .stat-icon-indigo {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }

    /* Page header gradient */
    .page-header {
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
    }

    /* Pagination */
    .pagination-btn {
        transition: all 0.2s ease;
    }

    .pagination-btn:hover:not(.active) {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .pagination-btn.active {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    /* Toast notification */
    .toast {
        animation: slideInRight 0.3s ease-out, slideOutRight 0.3s ease-in 2.7s;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
        }
        to {
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
        }
        to {
            transform: translateX(100%);
        }
    }

    /* Metric badge */
    .metric-badge {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
</style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-4 lg:p-8 ml-0 lg:ml-64 transition-all duration-300">
        
        <!-- Page Header -->
        <div class="glass-card page-header rounded-2xl shadow-xl p-6 lg:p-8 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="flex-1">
                    <h1 class="text-3xl lg:text-4xl font-bold  mb-2">Dashboard Overview</h1>
                    <p class="text-gray-600 text-sm lg:text-base flex items-center gap-2">
                        <i class="fas fa-chart-line text-blue-500"></i>
                        Real-time inventory monitoring and activity tracking
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-white rounded-xl shadow-md border border-green-100">
                        <span class="relative flex h-3 w-3">
                            <span class="live-indicator absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-sm font-semibold text-gray-700">Live</span>
                    </div>
                    <button onclick="refreshData()" class="p-3 bg-white rounded-xl shadow-md border border-gray-100 hover:bg-gray-50 transition-all hover:scale-105">
                        <i class="fas fa-sync-alt text-gray-600"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Primary Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Devices -->
            <div class="stats-card glass-card rounded-2xl shadow-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Total Devices</p>
                        <p class="text-4xl font-bold text-gray-900"><?= number_format($totalItems) ?></p>
                    </div>
                    <div class="p-4 stat-icon-blue rounded-xl shadow-lg icon-container">
                        <i class="fas fa-laptop text-white text-2xl"></i>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Across all categories</span>
                        <span class="metric-badge px-3 py-1 rounded-full text-xs font-bold text-green-700">
                            <i class="fas fa-arrow-up mr-1"></i>12%
                        </span>
                    </div>
                </div>
            </div>

            <!-- In Use -->
            <div class="stats-card glass-card rounded-2xl shadow-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Devices In Use</p>
                        <p class="text-4xl font-bold text-gray-900"><?= number_format($inUseDevices) ?></p>
                    </div>
                    <div class="p-4 stat-icon-green rounded-xl shadow-lg icon-container">
                        <i class="fas fa-user-check text-white text-2xl"></i>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Currently active</span>
                        <span class="px-3 py-1 bg-green-50 border border-green-200 rounded-full text-xs font-bold text-green-700">
                            <?= number_format($activeUsers) ?> users
                        </span>
                    </div>
                </div>
            </div>

            <!-- In Storage -->
            <div class="stats-card glass-card rounded-2xl shadow-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">In Storage</p>
                        <p class="text-4xl font-bold text-gray-900"><?= number_format($inStorageDevices) ?></p>
                    </div>
                    <div class="p-4 stat-icon-yellow rounded-xl shadow-lg icon-container">
                        <i class="fas fa-warehouse text-white text-2xl"></i>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Available for assignment</span>
                        <span class="px-3 py-1 bg-yellow-50 border border-yellow-200 rounded-full text-xs font-bold text-yellow-700">
                            <?= number_format($unassignedDevices) ?> free
                        </span>
                    </div>
                </div>
            </div>

            <!-- Needs Attention -->
            <div class="stats-card glass-card rounded-2xl shadow-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">Needs Attention</p>
                        <p class="text-4xl font-bold text-gray-900"><?= number_format($faultyDevices + $repairingDevices) ?></p>
                    </div>
                    <div class="p-4 stat-icon-red rounded-xl shadow-lg icon-container">
                        <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="px-2 py-1 bg-red-50 border border-red-200 rounded-full font-bold text-red-700">
                            <?= number_format($faultyDevices) ?> faulty
                        </span>
                        <span class="px-2 py-1 bg-orange-50 border border-orange-200 rounded-full font-bold text-orange-700">
                            <?= number_format($repairingDevices) ?> repair
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="p-3 stat-icon-purple rounded-lg shadow-md">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalUsers) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= number_format($adminUsers) ?> administrators</p>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="p-3 stat-icon-orange rounded-lg shadow-md">
                        <i class="fas fa-tools text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Under Repair</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($repairingDevices) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">In maintenance</p>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="p-3 stat-icon-gray rounded-lg shadow-md">
                        <i class="fas fa-archive text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Retired</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($retiredDevices) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Decommissioned</p>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="p-3 stat-icon-indigo rounded-lg shadow-md">
                        <i class="fas fa-calendar-day text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Today's Changes</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($todayChanges) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5">Activities logged</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Section -->
        <div class="glass-card rounded-2xl shadow-xl overflow-hidden mb-8">
            <!-- Activity Header -->
            <div class="p-6 lg:p-8 border-b border-gray-200 bg-gradient-to-r from-white to-gray-50">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-1">Recent Activity</h2>
                        <p class="text-sm text-gray-600 flex items-center gap-2">
                            <i class="fas fa-history text-blue-500"></i>
                            Latest inventory changes and updates
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 lg:flex-initial">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="searchInput" placeholder="Search activities..." 
                                class="search-input w-full lg:w-72 pl-11 pr-4 py-3 text-sm bg-white border-2 border-gray-200 rounded-xl">
                        </div>
                        <button onclick="toggleAutoRefresh()" id="autoRefreshBtn" 
                            class="btn-outline flex items-center gap-2 whitespace-nowrap">
                            <i class="fas fa-sync-alt"></i>
                            <span class="hidden sm:inline">Auto Refresh</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="divide-y divide-gray-100">
                <?php if (empty($currentPageActivities)): ?>
                    <div class="p-16 text-center">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full mb-4 shadow-inner">
                            <i class="fas fa-inbox text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-lg font-semibold text-gray-600 mb-1">No recent activities found</p>
                        <p class="text-sm text-gray-400">Activities will appear here as changes occur</p>
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
                    <div class="timeline-item relative p-6 lg:p-8 <?= $isNew ? 'highlight-new' : '' ?>">
                        <div class="flex gap-5">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br <?= $activity['color'] ?> flex items-center justify-center shadow-lg icon-container">
                                    <i class="fas <?= $activity['icon'] ?> text-white text-xl"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                    <div class="flex-1">
                                        <!-- Title and Badges -->
                                        <div class="flex items-center gap-2 flex-wrap mb-3">
                                            <h3 class="font-bold text-lg text-gray-900"><?= e($activity['title']) ?></h3>
                                            <span class="px-3 py-1 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-full text-xs font-bold shadow-sm">
                                                <?= e($activity['asset_tag']) ?>
                                            </span>
                                            
                                            <?php if ($isNew): ?>
                                                <span class="px-3 py-1 bg-gradient-to-r from-green-100 to-emerald-100 text-green-700 text-xs font-bold rounded-full shadow-sm border border-green-200">
                                                    <i class="fas fa-bolt mr-1"></i>NEW
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="text-sm text-gray-700 mb-4 leading-relaxed"><?= $activity['description'] ?></p>

                                        <!-- Status and Condition Badges -->
                                        <div class="flex flex-wrap gap-2 mb-4">
                                            <?php if (!empty($activity['status'])): ?>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <i class="fas fa-circle text-[6px] mr-1.5"></i>
                                                    <?= e($activity['status_label']) ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($activity['condition'])): ?>
                                                <span class="condition-badge <?= $conditionClass ?>">
                                                    <i class="fas fa-tag text-[10px] mr-1.5"></i>
                                                    <?= CONDITION_LABELS[$activity['condition']] ?? ucfirst($activity['condition']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                                            <?php if (!empty($activity['device_type'])): ?>
                                                <div class="flex items-center gap-2 text-xs bg-gray-50 px-3 py-2 rounded-lg">
                                                    <i class="fas fa-tag text-blue-500"></i>
                                                    <span class="text-gray-500 font-medium">Type:</span>
                                                    <span class="text-gray-900 font-semibold"><?= e($activity['device_type']) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($activity['model'])): ?>
                                                <div class="flex items-center gap-2 text-xs bg-gray-50 px-3 py-2 rounded-lg">
                                                    <i class="fas fa-microchip text-purple-500"></i>
                                                    <span class="text-gray-500 font-medium">Model:</span>
                                                    <span class="text-gray-900 font-semibold"><?= e($activity['model']) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($activity['category_name'])): ?>
                                                <div class="flex items-center gap-2 text-xs bg-gray-50 px-3 py-2 rounded-lg">
                                                    <i class="fas fa-folder text-orange-500"></i>
                                                    <span class="text-gray-500 font-medium">Category:</span>
                                                    <span class="text-gray-900 font-semibold"><?= e($activity['category_name']) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($activity['department_name'])): ?>
                                                <div class="flex items-center gap-2 text-xs bg-gray-50 px-3 py-2 rounded-lg">
                                                    <i class="fas fa-building text-indigo-500"></i>
                                                    <span class="text-gray-500 font-medium">Department:</span>
                                                    <span class="text-gray-900 font-semibold"><?= e($activity['department_name']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Specifications -->
                                        <?php if (!empty($activity['specifications'])): ?>
                                            <div class="flex items-start gap-2 text-xs text-gray-600 mb-4 bg-blue-50 px-4 py-3 rounded-lg border border-blue-100">
                                                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                                                <span class="flex-1"><?= e($activity['specifications']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Assignment Info -->
                                        <?php if (!empty($assignedUser['name']) && in_array($activity['type'], ['assigned'])): ?>
                                            <div class="flex items-center gap-3 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-100 shadow-sm">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-md">
                                                    <?= strtoupper(substr($assignedUser['name'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-gray-900"><?= e($assignedUser['name']) ?></p>
                                                    <?php if (!empty($assignedUser['email'])): ?>
                                                        <p class="text-xs text-gray-600"><?= e($assignedUser['email']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Timestamp and Type -->
                                    <div class="flex flex-col items-end gap-2">
                                        <span class="text-xs text-gray-400 whitespace-nowrap flex items-center gap-1.5" title="<?= e($activity['updated_at']) ?>">
                                            <i class="far fa-clock"></i>
                                            <?= $timeAgo ?>
                                        </span>
                                        <span class="px-3 py-1.5 bg-gradient-to-r from-indigo-50 to-purple-50 text-indigo-700 rounded-lg text-xs font-bold capitalize shadow-sm border border-indigo-100">
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
            <div class="px-6 lg:px-8 py-5 bg-gradient-to-r from-gray-50 to-white border-t border-gray-200">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-xs font-medium text-gray-600">
                        Showing <span class="font-bold text-gray-900"><?= $offset + 1 ?></span> to 
                        <span class="font-bold text-gray-900"><?= min($offset + $perPage, $totalActivities) ?></span> of 
                        <span class="font-bold text-gray-900"><?= number_format($totalActivities) ?></span> activities
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="pagination-btn px-4 py-2 text-xs font-semibold bg-white border-2 border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 shadow-sm">
                                <i class="fas fa-chevron-left mr-1"></i> Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" class="pagination-btn px-4 py-2 text-xs font-semibold <?= $i === $page ? 'active text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?> border-2 border-gray-200 rounded-lg shadow-sm">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="pagination-btn px-4 py-2 text-xs font-semibold bg-white border-2 border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 shadow-sm">
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
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-40 backdrop-blur-md z-50 hidden items-center justify-center">
        <div class="glass-card rounded-2xl p-8 shadow-2xl">
            <div class="spinner mx-auto mb-4"></div>
            <p class="text-sm text-gray-700 font-semibold">Refreshing data...</p>
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
                btn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i><span class="hidden sm:inline">Auto Refresh</span>';
                btn.classList.remove('bg-blue-50', 'border-blue-300', 'text-blue-700');
                showToast('Auto refresh disabled', 'info');
            } else {
                autoRefreshEnabled = true;
                autoRefreshInterval = setInterval(refreshData, 30000);
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i><span class="hidden sm:inline">Auto Refresh ON</span>';
                btn.classList.add('bg-blue-50', 'border-blue-300', 'text-blue-700');
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
            const colors = {
                success: 'bg-gradient-to-r from-green-50 to-emerald-50 text-green-800 border-green-200',
                error: 'bg-gradient-to-r from-red-50 to-rose-50 text-red-800 border-red-200',
                info: 'bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-800 border-blue-200'
            };
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
            
            toast.className = `toast fixed top-6 right-6 px-5 py-4 rounded-xl shadow-2xl z-50 border-2 ${colors[type]}`;
            toast.innerHTML = `
                <div class="flex items-center gap-3">
                    <i class="fas ${icons[type]} text-lg"></i>
                    <span class="text-sm font-semibold">${message}</span>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => toast.remove(), 3000);
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