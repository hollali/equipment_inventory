<?php
// Start session
session_start();

// Include database connection
require_once "./config/database.php";

// Create database instance and get connection
$database = new Database();
$conn = $database->getConnection();

// Pagination configuration
$logsPerPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
if ($logsPerPage < 10)
    $logsPerPage = 10;
if ($logsPerPage > 100)
    $logsPerPage = 100;

$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($currentPage < 1)
    $currentPage = 1;

// Calculate offset
$offset = ($currentPage - 1) * $logsPerPage;

// Function to get user statistics
function getUserStatistics($conn)
{
    $stats = [
        'totalUsers' => 0,
        'activeUsers' => 0,
        'inactiveUsers' => 0,
        'adminUsers' => 0,
        'staffUsers' => 0,
        'mpUsers' => 0,
        'last7Days' => 0,
        'totalDevices' => 0,
        'devicesInUse' => 0,
        'devicesInStore' => 0,
        'devicesFaulty' => 0,
        'devicesActive' => 0,
        'devicesInStorage' => 0,
        'devicesRepairing' => 0,
        'devicesRetired' => 0
    ];

    try {
        // Check if 'users' table exists
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if (!$tableCheck || mysqli_num_rows($tableCheck) == 0) {
            return $stats;
        }

        // Get user statistics
        $query = "SELECT 
                    COUNT(*) as totalUsers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as activeUsers,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactiveUsers,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as adminUsers,
                    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staffUsers,
                    SUM(CASE WHEN role = 'mp' THEN 1 ELSE 0 END) as mpUsers,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last7Days
                  FROM users";

        $result = mysqli_query($conn, $query);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $stats = array_merge($stats, [
                'totalUsers' => (int) $row['totalUsers'],
                'activeUsers' => (int) $row['activeUsers'],
                'inactiveUsers' => (int) $row['inactiveUsers'],
                'adminUsers' => (int) $row['adminUsers'],
                'staffUsers' => (int) $row['staffUsers'],
                'mpUsers' => (int) $row['mpUsers'],
                'last7Days' => (int) $row['last7Days']
            ]);
        }

        // Get device statistics from inventory_items table
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'inventory_items'");
        if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
            $deviceQuery = "SELECT 
                            COUNT(*) as totalDevices,
                            SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as devicesInUse,
                            SUM(CASE WHEN status = 'in_storage' THEN 1 ELSE 0 END) as devicesInStore,
                            SUM(CASE WHEN status = 'faulty' THEN 1 ELSE 0 END) as devicesFaulty,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as devicesActive,
                            SUM(CASE WHEN status = 'repairing' THEN 1 ELSE 0 END) as devicesRepairing,
                            SUM(CASE WHEN status = 'retired' THEN 1 ELSE 0 END) as devicesRetired,
                            SUM(CASE WHEN condition = 'Faulty' THEN 1 ELSE 0 END) as conditionFaulty
                          FROM inventory_items";
            
            $deviceResult = mysqli_query($conn, $deviceQuery);
            if ($deviceResult && $deviceRow = mysqli_fetch_assoc($deviceResult)) {
                $stats['totalDevices'] = (int) $deviceRow['totalDevices'];
                $stats['devicesInUse'] = (int) $deviceRow['devicesInUse'];
                $stats['devicesInStore'] = (int) $deviceRow['devicesInStore'];
                $stats['devicesFaulty'] = (int) $deviceRow['devicesFaulty'];
                $stats['devicesActive'] = (int) $deviceRow['devicesActive'];
                $stats['devicesRepairing'] = (int) $deviceRow['devicesRepairing'];
                $stats['devicesRetired'] = (int) $deviceRow['devicesRetired'];
                
                // Also count devices with condition = 'Faulty'
                if ($deviceRow['conditionFaulty'] > 0) {
                    $stats['devicesFaulty'] += (int) $deviceRow['conditionFaulty'];
                }
            }
            
            // Also get activity log count
            $logTableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
            if ($logTableCheck && mysqli_num_rows($logTableCheck) > 0) {
                $logQuery = "SELECT COUNT(*) as totalLogs FROM activity_log";
                $logResult = mysqli_query($conn, $logQuery);
                if ($logResult && $logRow = mysqli_fetch_assoc($logResult)) {
                    $stats['totalActivityLogs'] = (int) $logRow['totalLogs'];
                }
            }
        }

    } catch (Exception $e) {
        error_log("Error in getUserStatistics: " . $e->getMessage());
    }

    return $stats;
}

// Function to get recent activity from activity_log table
function getRecentActivity($conn, $limit = 20, $offset = 0)
{
    $logs = [];
    $totalLogs = 0;

    try {
        // First check if activity_log table exists
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'activity_log'");
        if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
            // Get total count from activity_log
            $countQuery = "SELECT COUNT(*) as total FROM activity_log";
            $countResult = mysqli_query($conn, $countQuery);
            if ($countResult && $row = mysqli_fetch_assoc($countResult)) {
                $totalLogs = (int) $row['total'];
            }

            // Get paginated logs with user info
            $query = "SELECT 
                        al.*,
                        u.firstname,
                        u.lastname,
                        u.email,
                        u.role,
                        u.status as user_status
                      FROM activity_log al
                      LEFT JOIN users u ON al.user_id = u.id
                      ORDER BY al.created_at DESC
                      LIMIT $limit OFFSET $offset";

            $result = mysqli_query($conn, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $logs[] = [
                        'id' => $row['id'],
                        'user_id' => $row['user_id'],
                        'user_name' => trim($row['firstname'] . ' ' . $row['lastname']) ?: $row['email'],
                        'user_email' => $row['email'],
                        'user_role' => $row['role'],
                        'user_status' => $row['user_status'],
                        'profile_picture' => '',
                        'action' => $row['action'],
                        'description' => $row['description'],
                        'ip_address' => $row['ip_address'],
                        'created_at' => $row['created_at'],
                        'browser_info' => '',
                        'module' => 'System'
                    ];
                }
            }
        }
        
        // If no activity logs or table doesn't exist, fall back to user activity
        if (empty($logs)) {
            return getRecentUserActivity($conn, $limit, $offset);
        }

    } catch (Exception $e) {
        error_log("Error in getRecentActivity: " . $e->getMessage());
        // Fall back to user activity
        return getRecentUserActivity($conn, $limit, $offset);
    }

    return [
        'logs' => $logs,
        'total' => $totalLogs,
        'limit' => $limit,
        'offset' => $offset
    ];
}

// Function to get recent user activity from users table (fallback)
function getRecentUserActivity($conn, $limit = 20, $offset = 0)
{
    $logs = [];
    $totalLogs = 0;

    try {
        // Check if 'users' table exists
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if (!$tableCheck || mysqli_num_rows($tableCheck) == 0) {
            return ['logs' => [], 'total' => 0];
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM users";
        $countResult = mysqli_query($conn, $countQuery);
        if ($countResult && $row = mysqli_fetch_assoc($countResult)) {
            $totalLogs = (int) $row['total'];
        }

        // Get recent users as activity
        $query = "SELECT 
                    u.*,
                    DATE_FORMAT(u.created_at, '%Y-%m-%d %H:%i:%s') as created_at_formatted,
                    DATE_FORMAT(u.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at_formatted
                  FROM users u
                  ORDER BY GREATEST(u.created_at, COALESCE(u.updated_at, '1970-01-01')) DESC
                  LIMIT $limit OFFSET $offset";

        $result = mysqli_query($conn, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Determine action based on timestamps
                $createdAt = strtotime($row['created_at']);
                $updatedAt = $row['updated_at'] ? strtotime($row['updated_at']) : null;
                $now = time();
                
                // Calculate most recent activity
                $mostRecent = $updatedAt ? max($createdAt, $updatedAt) : $createdAt;
                $hoursSinceActivity = floor(($now - $mostRecent) / 3600);
                
                // Determine action type
                if ($updatedAt && $updatedAt > $createdAt) {
                    $action = 'Profile Updated';
                    $description = 'User profile was updated';
                    $timestamp = $row['updated_at'];
                } else {
                    $action = 'User Created';
                    $description = 'New user account created';
                    $timestamp = $row['created_at'];
                }
                
                // Add activity type based on time
                if ($hoursSinceActivity < 1) {
                    $action = 'Recent ' . $action;
                } elseif ($hoursSinceActivity < 24) {
                    $action = 'Today: ' . $action;
                }

                $logs[] = [
                    'id' => $row['id'],
                    'user_id' => $row['id'],
                    'user_name' => trim($row['firstname'] . ' ' . $row['lastname']) ?: $row['email'],
                    'user_email' => $row['email'],
                    'user_role' => $row['role'],
                    'user_status' => $row['status'],
                    'profile_picture' => '',
                    'action' => $action,
                    'description' => $description . ' - Status: ' . $row['status'],
                    'ip_address' => 'System',
                    'created_at' => $timestamp,
                    'browser_info' => 'System',
                    'module' => 'User Management'
                ];
            }
        }

    } catch (Exception $e) {
        error_log("Error in getRecentUserActivity: " . $e->getMessage());
        return ['logs' => [], 'total' => 0];
    }

    return [
        'logs' => $logs,
        'total' => $totalLogs,
        'limit' => $limit,
        'offset' => $offset
    ];
}

// Function to get pagination links
function getPaginationLinks($totalItems, $itemsPerPage, $currentPage, $maxPages = 5)
{
    $totalPages = ceil($totalItems / $itemsPerPage);
    if ($totalPages < 1)
        $totalPages = 1;
    if ($currentPage > $totalPages)
        $currentPage = $totalPages;

    $pagination = [
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
        'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
        'pages' => []
    ];

    // Calculate page range
    $startPage = max(1, $currentPage - floor($maxPages / 2));
    $endPage = min($totalPages, $startPage + $maxPages - 1);

    if ($endPage - $startPage + 1 < $maxPages) {
        $startPage = max(1, $endPage - $maxPages + 1);
    }

    for ($page = $startPage; $page <= $endPage; $page++) {
        $pagination['pages'][] = [
            'number' => $page,
            'is_current' => $page == $currentPage
        ];
    }

    return $pagination;
}

// Function to get action styling
function getActionStyle($action)
{
    $styles = [
        'Login' => ['class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200', 'icon' => 'fa-sign-in-alt'],
        'Logout' => ['class' => 'bg-gray-50 text-gray-700 border border-gray-200', 'icon' => 'fa-sign-out-alt'],
        'User Created' => ['class' => 'bg-indigo-50 text-indigo-700 border border-indigo-200', 'icon' => 'fa-user-plus'],
        'Recent User Created' => ['class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200', 'icon' => 'fa-user-plus'],
        'Today: User Created' => ['class' => 'bg-blue-50 text-blue-700 border border-blue-200', 'icon' => 'fa-user-plus'],
        'Profile Updated' => ['class' => 'bg-teal-50 text-teal-700 border border-teal-200', 'icon' => 'fa-user-edit'],
        'Recent Profile Updated' => ['class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200', 'icon' => 'fa-user-edit'],
        'Today: Profile Updated' => ['class' => 'bg-blue-50 text-blue-700 border border-blue-200', 'icon' => 'fa-user-edit'],
        'Device Added' => ['class' => 'bg-green-50 text-green-700 border border-green-200', 'icon' => 'fa-laptop-medical'],
        'Device Updated' => ['class' => 'bg-blue-50 text-blue-700 border border-blue-200', 'icon' => 'fa-edit'],
        'Device Assigned' => ['class' => 'bg-purple-50 text-purple-700 border border-purple-200', 'icon' => 'fa-user-check'],
        'Device Returned' => ['class' => 'bg-amber-50 text-amber-700 border border-amber-200', 'icon' => 'fa-undo'],
        'Report Generated' => ['class' => 'bg-purple-50 text-purple-700 border border-purple-200', 'icon' => 'fa-chart-bar'],
        'Settings Updated' => ['class' => 'bg-orange-50 text-orange-700 border border-orange-200', 'icon' => 'fa-cog'],
    ];

    return $styles[$action] ?? ['class' => 'bg-gray-50 text-gray-700 border border-gray-200', 'icon' => 'fa-bolt'];
}

// Function to format date
function formatDateTime($dateString)
{
    $timestamp = strtotime($dateString);
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60)
        return 'Just now';
    if ($diff < 3600)
        return floor($diff / 60) . ' min ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)
        return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $timestamp);
}

// Function to get role badge style
function getRoleStyle($role)
{
    $styles = [
        'admin' => ['class' => 'bg-purple-100 text-purple-700', 'icon' => 'fa-crown'],
        'staff' => ['class' => 'bg-blue-100 text-blue-700', 'icon' => 'fa-briefcase'],
        'mp' => ['class' => 'bg-emerald-100 text-emerald-700', 'icon' => 'fa-landmark'],
    ];
    return $styles[$role] ?? ['class' => 'bg-gray-100 text-gray-700', 'icon' => 'fa-user'];
}

// Function to get status badge style
function getStatusStyle($status)
{
    $styles = [
        'active' => ['class' => 'bg-green-100 text-green-700', 'icon' => 'fa-check-circle'],
        'inactive' => ['class' => 'bg-gray-100 text-gray-700', 'icon' => 'fa-times-circle'],
    ];
    return $styles[$status] ?? ['class' => 'bg-gray-100 text-gray-700', 'icon' => 'fa-question-circle'];
}

// Get statistics and activity data
$userStats = getUserStatistics($conn);
$activityData = getRecentActivity($conn, $logsPerPage, $offset);
$activityLogs = $activityData['logs'];
$totalLogs = $activityData['total'];
$pagination = getPaginationLinks($totalLogs, $logsPerPage, $currentPage);

// Calculate percentages
$activeRate = $userStats['totalUsers'] > 0 ? round(($userStats['activeUsers'] / $userStats['totalUsers']) * 100, 1) : 0;
$adminRate = $userStats['totalUsers'] > 0 ? round(($userStats['adminUsers'] / $userStats['totalUsers']) * 100, 1) : 0;

// Calculate device rates
$deviceInUseRate = $userStats['totalDevices'] > 0 ? round(($userStats['devicesInUse'] / $userStats['totalDevices']) * 100, 1) : 0;
$deviceStoreRate = $userStats['totalDevices'] > 0 ? round(($userStats['devicesInStore'] / $userStats['totalDevices']) * 100, 1) : 0;
$deviceFaultyRate = $userStats['totalDevices'] > 0 ? round(($userStats['devicesFaulty'] / $userStats['totalDevices']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics | Parliament ICT</title>
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

        .stat-card {
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-card-total::before {
            background: #3b82f6;
        }

        .stat-card-active::before {
            background: #10b981;
        }

        .stat-card-admin::before {
            background: #8b5cf6;
        }

        .stat-card-devices::before {
            background: #f59e0b;
        }

        .stat-card-activity::before {
            background: #ec4899;
        }

        .hover-lift {
            transition: all 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-1px);
        }

        .table-row-hover:hover {
            background-color: #f8fafc;
        }

        .border-light {
            border-color: #e2e8f0;
        }

        .bg-light {
            background-color: #f8fafc;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="ml-0 md:ml-64 transition-all duration-300">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 md:px-8 py-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                        <i class="fas fa-chart-line text-blue-600 mr-3"></i>
                        System Analytics
                    </h1>
                    <p class="text-gray-600 mt-1">Monitor user activity and system performance</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i
                            class="fas fa-calendar-alt absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <select
                            class="border border-gray-300 rounded-lg pl-10 pr-8 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                            <option>Last 7 days</option>
                            <option>Last 30 days</option>
                            <option>Last quarter</option>
                            <option>Year to date</option>
                        </select>
                    </div>
                    <button onclick="refreshData()"
                        class="w-10 h-10 rounded-lg bg-white border border-gray-300 flex items-center justify-center hover:bg-gray-50 transition-colors">
                        <i class="fas fa-sync-alt text-gray-600"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="p-6 md:p-8 space-y-8">
            <!-- Statistics Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <!-- Total Users -->
                <div class="stat-card stat-card-total bg-white rounded-lg p-6 animate-fade-in">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 rounded-lg bg-blue-50">
                            <i class="fas fa-users text-xl text-blue-600"></i>
                        </div>
                        <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded">Users</span>
                    </div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Total Users</p>
                    <div class="flex items-end justify-between">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($userStats['totalUsers']) ?></p>
                        <div class="flex items-center text-emerald-600 text-xs font-medium">
                            <i class="fas fa-arrow-up mr-1 text-xs"></i>
                            <span><?= $userStats['last7Days'] ?> new</span>
                        </div>
                    </div>
                </div>

                <!-- Active Users -->
                <div class="stat-card stat-card-active bg-white rounded-lg p-6 animate-fade-in"
                    style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 rounded-lg bg-emerald-50">
                            <i class="fas fa-user-check text-xl text-emerald-600"></i>
                        </div>
                        <div class="flex items-center">
                            <div class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></div>
                            <span class="text-xs font-medium text-emerald-600">Active</span>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Active Users</p>
                    <div class="flex items-end justify-between">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($userStats['activeUsers']) ?></p>
                        <div class="text-right">
                            <div class="text-gray-600 text-xs"><?= $activeRate ?>%</div>
                            <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: <?= $activeRate ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Devices -->
                <div class="stat-card stat-card-devices bg-white rounded-lg p-6 animate-fade-in"
                    style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 rounded-lg bg-amber-50">
                            <i class="fas fa-laptop text-xl text-amber-600"></i>
                        </div>
                        <span class="text-xs font-medium text-amber-600 bg-amber-50 px-2 py-1 rounded">Devices</span>
                    </div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Total Devices</p>
                    <div class="flex items-end justify-between">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($userStats['totalDevices']) ?></p>
                        <div class="text-right">
                            <div class="text-gray-600 text-xs"><?= $deviceInUseRate ?>% in use</div>
                            <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500 rounded-full" style="width: <?= $deviceInUseRate ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Devices In Use -->
                <div class="stat-card stat-card-active bg-white rounded-lg p-6 animate-fade-in"
                    style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 rounded-lg bg-green-50">
                            <i class="fas fa-laptop-code text-xl text-green-600"></i>
                        </div>
                        <div class="flex items-center">
                            <div class="w-2 h-2 rounded-full bg-green-500 mr-2"></div>
                            <span class="text-xs font-medium text-green-600">In Use</span>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Devices In Use</p>
                    <div class="flex items-end justify-between">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($userStats['devicesInUse']) ?></p>
                        <div class="text-gray-600 text-xs"><?= $deviceInUseRate ?>% of total</div>
                    </div>
                </div>

                <!-- Activity Logs -->
                <div class="stat-card stat-card-activity bg-white rounded-lg p-6 animate-fade-in"
                    style="animation-delay: 0.4s;">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 rounded-lg bg-pink-50">
                            <i class="fas fa-history text-xl text-pink-600"></i>
                        </div>
                        <span class="text-xs font-medium text-pink-600 bg-pink-50 px-2 py-1 rounded">Activity</span>
                    </div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Activity Logs</p>
                    <div class="flex items-end justify-between">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($totalLogs) ?></p>
                        <div class="text-gray-600 text-xs">
                            <?= isset($userStats['totalActivityLogs']) ? number_format($userStats['totalActivityLogs']) : '0' ?> total
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Distribution & Activity Logs -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- User Distribution -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Role Distribution -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-users-cog text-blue-600 mr-2"></i>
                                User Roles
                            </h3>
                            <span class="text-sm text-gray-500">Distribution</span>
                        </div>

                        <div class="space-y-4">
                            <!-- Staff -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700">Staff Members</span>
                                    <span class="text-gray-600"><?= $userStats['staffUsers'] ?> users</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <?php $staffPercent = $userStats['totalUsers'] > 0 ? ($userStats['staffUsers'] / $userStats['totalUsers']) * 100 : 0; ?>
                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?= $staffPercent ?>%">
                                    </div>
                                </div>
                            </div>

                            <!-- MP -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700">MP Users</span>
                                    <span class="text-gray-600"><?= $userStats['mpUsers'] ?> users</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <?php $mpPercent = $userStats['totalUsers'] > 0 ? ($userStats['mpUsers'] / $userStats['totalUsers']) * 100 : 0; ?>
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?= $mpPercent ?>%">
                                    </div>
                                </div>
                            </div>

                            <!-- Admin -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700">Administrators</span>
                                    <span class="text-gray-600"><?= $userStats['adminUsers'] ?> users</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: <?= $adminRate ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div class="text-xl font-bold text-gray-900"><?= $activeRate ?>%</div>
                                    <div class="text-xs text-gray-500">Active Rate</div>
                                </div>
                                <div>
                                    <div class="text-xl font-bold text-gray-900"><?= $userStats['last7Days'] ?></div>
                                    <div class="text-xs text-gray-500">New (7d)</div>
                                </div>
                                <div>
                                    <div class="text-xl font-bold text-gray-900"><?= $userStats['inactiveUsers'] ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Inactive</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Device Status -->
                    <?php if ($userStats['totalDevices'] > 0): ?>
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-laptop-medical text-blue-600 mr-2"></i>
                                Device Status
                            </h3>
                            <span class="text-sm text-gray-500"><?= number_format($userStats['totalDevices']) ?> total</span>
                        </div>

                        <div class="space-y-4">
                            <!-- In Use -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700">In Use</span>
                                    <span class="text-gray-600"><?= number_format($userStats['devicesInUse']) ?> devices</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500 rounded-full" style="width: <?= $deviceInUseRate ?>%">
                                    </div>
                                </div>
                            </div>

                            <!-- In Store/Storage -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700">In Storage</span>
                                    <span class="text-gray-600"><?= number_format($userStats['devicesInStore']) ?> devices</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: <?= $deviceStoreRate ?>%">
                                    </div>
                                </div>
                            </div>

                            <!-- Faulty -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700">Faulty</span>
                                    <span class="text-gray-600"><?= number_format($userStats['devicesFaulty']) ?> devices</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-red-500 rounded-full" style="width: <?= $deviceFaultyRate ?>%">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Other Statuses -->
                            <?php if ($userStats['devicesActive'] > 0 || $userStats['devicesRepairing'] > 0 || $userStats['devicesRetired'] > 0): ?>
                            <div class="pt-4 mt-4 border-t border-gray-200">
                                <div class="grid grid-cols-3 gap-2">
                                    <?php if ($userStats['devicesActive'] > 0): ?>
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-700">Active</div>
                                        <div class="text-lg font-bold text-blue-600"><?= $userStats['devicesActive'] ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($userStats['devicesRepairing'] > 0): ?>
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-700">Repairing</div>
                                        <div class="text-lg font-bold text-amber-600"><?= $userStats['devicesRepairing'] ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($userStats['devicesRetired'] > 0): ?>
                                    <div class="text-center">
                                        <div class="text-sm font-medium text-gray-700">Retired</div>
                                        <div class="text-lg font-bold text-gray-600"><?= $userStats['devicesRetired'] ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-laptop-medical text-blue-600 mr-2"></i>
                                Device Status
                            </h3>
                            <span class="text-sm text-gray-500">No devices found</span>
                        </div>
                        <div class="text-center py-4">
                            <i class="fas fa-laptop text-3xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 font-medium">No device data available</p>
                            <p class="text-sm text-gray-400 mt-1">Device statistics will appear here when devices are added to the system</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Logs -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <i class="fas fa-stream text-blue-600 mr-2"></i>
                                        Recent System Activity
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Showing <?= count($activityLogs) ?> of <?= number_format($totalLogs) ?> total
                                        activities
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <i
                                            class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        <input id="searchLogs" type="text" placeholder="Search activities..."
                                            class="border border-gray-300 rounded-lg pl-10 pr-4 py-2 text-sm w-64 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <button id="exportBtn"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-download mr-2"></i>Export
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <?php if (empty($activityLogs)): ?>
                                <div class="py-12 text-center">
                                    <i class="fas fa-inbox text-3xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-500 font-medium">No activity logs found</p>
                                    <p class="text-sm text-gray-400 mt-1">Activity will appear here as users interact with the system</p>
                                </div>
                            <?php else: ?>
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                User & Activity</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Details</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                                Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($activityLogs as $log):
                                            $actionStyle = getActionStyle($log['action']);
                                            $roleStyle = getRoleStyle($log['user_role']);
                                            $statusStyle = getStatusStyle($log['user_status']);
                                            ?>
                                            <tr class="table-row-hover transition-colors animate-fade-in">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600 font-medium">
                                                            <?php if (!empty($log['profile_picture'])): ?>
                                                                <img src="<?= htmlspecialchars($log['profile_picture']) ?>" alt=""
                                                                    class="w-9 h-9 rounded-lg object-cover">
                                                            <?php else: ?>
                                                                <i class="fas fa-user"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-gray-900">
                                                                <?= htmlspecialchars($log['user_name']) ?></div>
                                                            <div class="flex items-center gap-2 mt-1">
                                                                <span
                                                                    class="inline-flex items-center gap-1 <?= $actionStyle['class'] ?> px-2 py-0.5 rounded text-xs">
                                                                    <i class="fas <?= $actionStyle['icon'] ?> text-xs"></i>
                                                                    <?= htmlspecialchars($log['action']) ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="space-y-2">
                                                        <div class="flex items-center gap-2">
                                                            <?php if (!empty($log['user_role'])): ?>
                                                            <span class="text-sm font-medium text-gray-700">Role:</span>
                                                            <span class="text-xs <?= $roleStyle['class'] ?> px-2 py-0.5 rounded">
                                                                <?= htmlspecialchars($log['user_role']) ?>
                                                            </span>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($log['user_status'])): ?>
                                                            <span class="text-sm text-gray-500">•</span>
                                                            <span class="text-sm font-medium text-gray-700">Status:</span>
                                                            <span class="text-xs <?= $statusStyle['class'] ?> px-2 py-0.5 rounded">
                                                                <?= htmlspecialchars($log['user_status']) ?>
                                                            </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-sm text-gray-600">
                                                            <?= htmlspecialchars($log['description']) ?>
                                                        </p>
                                                        <div class="flex items-center gap-3 text-xs text-gray-500">
                                                            <?php if (!empty($log['ip_address']) && $log['ip_address'] != 'System'): ?>
                                                            <span class="font-mono"><?= htmlspecialchars($log['ip_address']) ?></span>
                                                            <span>•</span>
                                                            <?php endif; ?>
                                                            <span><?= htmlspecialchars($log['module']) ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm">
                                                        <div class="text-gray-900 font-medium">
                                                            <?= date('H:i', strtotime($log['created_at'])) ?>
                                                        </div>
                                                        <div class="text-gray-500">
                                                            <?= formatDateTime($log['created_at']) ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                    <div class="text-sm text-gray-600">
                                        Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                                        <span class="mx-2">•</span>
                                        <?= number_format($totalLogs) ?> total activities
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <!-- Items per page -->
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            <span>Show:</span>
                                            <select id="itemsPerPage"
                                                class="border border-gray-300 rounded px-2 py-1 text-sm bg-white">
                                                <option value="10" <?= $logsPerPage == 10 ? 'selected' : '' ?>>10</option>
                                                <option value="20" <?= $logsPerPage == 20 ? 'selected' : '' ?>>20</option>
                                                <option value="50" <?= $logsPerPage == 50 ? 'selected' : '' ?>>50</option>
                                                <option value="100" <?= $logsPerPage == 100 ? 'selected' : '' ?>>100</option>
                                            </select>
                                        </div>

                                        <!-- Page navigation -->
                                        <div class="flex items-center gap-1">
                                            <a href="<?= $pagination['has_previous'] ? '?page=' . $pagination['previous_page'] . '&per_page=' . $logsPerPage : '#' ?>"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors <?= !$pagination['has_previous'] ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                                <i class="fas fa-chevron-left text-xs"></i>
                                            </a>

                                            <?php foreach ($pagination['pages'] as $page): ?>
                                                <?php if ($page['is_current']): ?>
                                                    <span
                                                        class="w-8 h-8 inline-flex items-center justify-center rounded border border-blue-600 bg-blue-600 text-white font-medium text-sm">
                                                        <?= $page['number'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <a href="?page=<?= $page['number'] ?>&per_page=<?= $logsPerPage ?>"
                                                        class="w-8 h-8 inline-flex items-center justify-center rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors text-sm">
                                                        <?= $page['number'] ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>

                                            <a href="<?= $pagination['has_next'] ? '?page=' . $pagination['next_page'] . '&per_page=' . $logsPerPage : '#' ?>"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors <?= !$pagination['has_next'] ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                                <i class="fas fa-chevron-right text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchLogs');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const filter = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }

        // Export functionality
        const exportBtn = document.getElementById('exportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                // Create CSV content
                let csv = 'Name,Email,Role,Status,Action,Description,IP Address,Module,Timestamp\n';

                document.querySelectorAll('tbody tr').forEach(row => {
                    if (row.style.display !== 'none') {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 3) {
                            const name = cells[0].querySelector('.font-medium').textContent.trim();
                            const email = cells[1].querySelector('.text-sm') ? cells[1].querySelector('.text-sm').textContent.trim() : '';
                            const role = cells[1].querySelectorAll('span')[0] ? cells[1].querySelectorAll('span')[0].textContent.trim() : '';
                            const status = cells[1].querySelectorAll('span')[2] ? cells[1].querySelectorAll('span')[2].textContent.trim() : '';
                            const action = cells[0].querySelector('span').textContent.trim();
                            const description = cells[1].querySelector('.text-sm') ? cells[1].querySelector('.text-sm').textContent.trim() : '';
                            const ipAddress = cells[1].querySelector('.font-mono') ? cells[1].querySelector('.font-mono').textContent.trim() : '';
                            const module = cells[1].querySelector('.text-xs:last-child').textContent.trim();
                            const timestamp = cells[2].textContent.trim();

                            csv += `"${name}","${email}","${role}","${status}","${action}","${description}","${ipAddress}","${module}","${timestamp}"\n`;
                        }
                    }
                });

                // Create and download file
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `system_activity_${new Date().toISOString().slice(0, 10)}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            });
        }

        // Items per page selector
        const itemsPerPageSelect = document.getElementById('itemsPerPage');
        if (itemsPerPageSelect) {
            itemsPerPageSelect.addEventListener('change', function () {
                const itemsPerPage = this.value;
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', itemsPerPage);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            });
        }

        // Refresh data
        function refreshData() {
            const url = new URL(window.location.href);
            url.searchParams.set('refresh', Date.now());
            window.location.href = url.toString();
        }

        // Add hover effects
        document.addEventListener('DOMContentLoaded', function () {
            // Add hover effects to cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.classList.add('hover-lift');
                });
                card.addEventListener('mouseleave', () => {
                    card.classList.remove('hover-lift');
                });
            });
        });
    </script>
</body>

</html>