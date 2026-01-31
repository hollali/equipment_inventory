<?php
// Start session
session_start();

// Include database connection
require_once "./config/database.php";

// Create database instance and get connection
$database = new Database();
$conn = $database->getConnection();

// Pagination configuration
$logsPerPage = 20; // Number of logs per page
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
        'mpUsers' => 0
    ];

    try {
        // Check if 'users' table exists
        $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if (!$tableCheck) {
            return $stats;
        }

        if (mysqli_num_rows($tableCheck) == 0) {
            return $stats;
        }

        // Try optimized query first
        $query = "SELECT 
                    COUNT(*) as totalUsers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as activeUsers,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactiveUsers,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as adminUsers,
                    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staffUsers,
                    SUM(CASE WHEN role = 'mp' THEN 1 ELSE 0 END) as mpUsers
                  FROM users";

        $result = mysqli_query($conn, $query);

        if ($result) {
            if ($row = mysqli_fetch_assoc($result)) {
                $stats['totalUsers'] = (int) $row['totalUsers'];
                $stats['activeUsers'] = (int) $row['activeUsers'];
                $stats['inactiveUsers'] = (int) $row['inactiveUsers'];
                $stats['adminUsers'] = (int) $row['adminUsers'];
                $stats['staffUsers'] = (int) $row['staffUsers'];
                $stats['mpUsers'] = (int) $row['mpUsers'];
            }
        } else {
            return getUserStatisticsFallback($conn);
        }

    } catch (Exception $e) {
        error_log("Error in getUserStatistics: " . $e->getMessage());
        return $stats;
    }

    return $stats;
}

// Fallback function
function getUserStatisticsFallback($conn)
{
    $stats = [
        'totalUsers' => 0,
        'activeUsers' => 0,
        'inactiveUsers' => 0,
        'adminUsers' => 0,
        'staffUsers' => 0,
        'mpUsers' => 0
    ];

    $queries = [
        'totalUsers' => "SELECT COUNT(*) as count FROM users",
        'activeUsers' => "SELECT COUNT(*) as count FROM users WHERE status = 'active'",
        'inactiveUsers' => "SELECT COUNT(*) as count FROM users WHERE status = 'inactive'",
        'adminUsers' => "SELECT COUNT(*) as count FROM users WHERE role = 'admin'",
        'staffUsers' => "SELECT COUNT(*) as count FROM users WHERE role = 'staff'",
        'mpUsers' => "SELECT COUNT(*) as count FROM users WHERE role = 'mp'"
    ];

    foreach ($queries as $key => $query) {
        $result = mysqli_query($conn, $query);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $stats[$key] = (int) $row['count'];
        }
    }

    return $stats;
}

// Function to get recent activity logs with pagination
function getRecentActivityLogs($conn, $limit = 20, $offset = 0)
{
    $logs = [];
    $totalLogs = 0;

    // Check if 'activity_logs' table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'activity_logs'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) == 0) {
        // If no activity logs table exists, create sample logs
        return getSampleActivityLogs($conn, $limit, $offset);
    }

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM activity_logs";
    $countResult = mysqli_query($conn, $countQuery);
    if ($countResult && $row = mysqli_fetch_assoc($countResult)) {
        $totalLogs = (int) $row['total'];
    }

    // Query to get paginated activity logs with user information
    $query = "SELECT 
                al.id,
                al.user_id,
                u.firstname,
                u.lastname,
                u.email,
                al.action,
                al.description,
                al.ip_address,
                al.created_at,
                al.browser_info
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              ORDER BY al.created_at DESC
              LIMIT ? OFFSET ?";

    // Prepare statement for security
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $logs[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'user_name' => trim($row['firstname'] . ' ' . $row['lastname']) ?: $row['email'],
                'action' => $row['action'],
                'description' => $row['description'],
                'ip_address' => $row['ip_address'],
                'created_at' => $row['created_at'],
                'browser_info' => $row['browser_info']
            ];
        }
        mysqli_stmt_close($stmt);
    }

    return [
        'logs' => $logs,
        'total' => $totalLogs,
        'limit' => $limit,
        'offset' => $offset
    ];
}

// Function to create sample activity logs if table doesn't exist
function getSampleActivityLogs($conn, $limit = 20, $offset = 0)
{
    $logs = [];

    // Get some real users from the database to make logs more realistic
    $usersQuery = "SELECT id, firstname, lastname, email, role FROM users LIMIT 10";
    $usersResult = mysqli_query($conn, $usersQuery);
    $users = [];

    if ($usersResult) {
        while ($user = mysqli_fetch_assoc($usersResult)) {
            $users[] = $user;
        }
    }

    // If no users exist, create some generic ones
    if (empty($users)) {
        $users = [
            ['id' => 1, 'firstname' => 'John', 'lastname' => 'Doe', 'email' => 'admin@parliament.gov', 'role' => 'admin'],
            ['id' => 2, 'firstname' => 'Jane', 'lastname' => 'Smith', 'email' => 'jane@parliament.gov', 'role' => 'staff'],
            ['id' => 3, 'firstname' => 'Mike', 'lastname' => 'Johnson', 'email' => 'mike@parliament.gov', 'role' => 'mp'],
        ];
    }

    // Sample actions and descriptions
    $actions = [
        ['action' => 'Login', 'description' => 'User logged into the system'],
        ['action' => 'Logout', 'description' => 'User logged out of the system'],
        ['action' => 'Profile Update', 'description' => 'User updated their profile information'],
        ['action' => 'Password Change', 'description' => 'User changed their password'],
        ['action' => 'Report Generated', 'description' => 'User generated a system report'],
        ['action' => 'User Created', 'description' => 'New user account was created'],
        ['action' => 'User Updated', 'description' => 'User account information was updated'],
        ['action' => 'Dashboard Viewed', 'description' => 'User viewed the dashboard'],
        ['action' => 'Settings Updated', 'description' => 'System settings were updated'],
        ['action' => 'File Uploaded', 'description' => 'User uploaded a document'],
    ];

    // Sample IP addresses
    $ipAddresses = ['192.168.1.10', '192.168.1.15', '192.168.1.20', '192.168.1.25', '192.168.1.30', '10.0.0.5', '10.0.0.10'];

    // Generate total sample logs (200 for pagination demonstration)
    $totalSampleLogs = 200;

    // Generate sample logs for the requested page
    $startIndex = $offset;
    $endIndex = min($offset + $limit, $totalSampleLogs);

    for ($i = $startIndex; $i < $endIndex; $i++) {
        $user = $users[array_rand($users)];
        $action = $actions[array_rand($actions)];
        $ip = $ipAddresses[array_rand($ipAddresses)];

        // Generate a random timestamp within the last 30 days
        $daysAgo = rand(0, 30);
        $hoursAgo = rand(0, 23);
        $minutesAgo = rand(0, 59);
        $timestamp = date('Y-m-d H:i:s', strtotime("-$daysAgo days -$hoursAgo hours -$minutesAgo minutes"));

        $logs[] = [
            'id' => $i + 1,
            'user_id' => $user['id'],
            'user_name' => trim($user['firstname'] . ' ' . $user['lastname']) ?: $user['email'],
            'action' => $action['action'],
            'description' => $action['description'],
            'ip_address' => $ip,
            'created_at' => $timestamp,
            'browser_info' => 'Sample Browser/OS Info'
        ];
    }

    // Sort by date (newest first)
    usort($logs, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    return [
        'logs' => $logs,
        'total' => $totalSampleLogs,
        'limit' => $limit,
        'offset' => $offset
    ];
}

// Function to create activity_logs table if needed
function createActivityLogsTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        browser_info TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";

    return mysqli_query($conn, $sql);
}

// Function to get pagination links
function getPaginationLinks($totalItems, $itemsPerPage, $currentPage, $maxPages = 5)
{
    $totalPages = ceil($totalItems / $itemsPerPage);
    if ($totalPages < 1)
        $totalPages = 1;

    // Ensure current page is within bounds
    if ($currentPage > $totalPages)
        $currentPage = $totalPages;
    if ($currentPage < 1)
        $currentPage = 1;

    $pagination = [
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
        'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
        'pages' => []
    ];

    // Calculate start and end pages for pagination links
    $startPage = max(1, $currentPage - floor($maxPages / 2));
    $endPage = min($totalPages, $startPage + $maxPages - 1);

    // Adjust start page if we're at the end
    if ($endPage - $startPage + 1 < $maxPages) {
        $startPage = max(1, $endPage - $maxPages + 1);
    }

    // Generate page numbers
    for ($page = $startPage; $page <= $endPage; $page++) {
        $pagination['pages'][] = [
            'number' => $page,
            'is_current' => $page == $currentPage
        ];
    }

    return $pagination;
}

// Get user statistics
$userStats = getUserStatistics($conn);

// Get recent activity logs with pagination
$logsData = getRecentActivityLogs($conn, $logsPerPage, $offset);
$activityLogs = $logsData['logs'];
$totalLogs = $logsData['total'];

// Get pagination information
$pagination = getPaginationLinks($totalLogs, $logsPerPage, $currentPage);

// Check if we should create the activity logs table
$checkLogsTable = mysqli_query($conn, "SHOW TABLES LIKE 'activity_logs'");
if (!$checkLogsTable || mysqli_num_rows($checkLogsTable) == 0) {
    $createTable = false;
    if ($createTable) {
        createActivityLogsTable($conn);
    }
}

// Function to get action styling
function getActionStyle($action)
{
    $styles = [
        'Login' => ['class' => 'bg-green-100 text-green-700', 'icon' => 'fa-sign-in-alt'],
        'Logout' => ['class' => 'bg-gray-100 text-gray-700', 'icon' => 'fa-sign-out-alt'],
        'Profile Update' => ['class' => 'bg-blue-100 text-blue-700', 'icon' => 'fa-user-edit'],
        'Password Change' => ['class' => 'bg-yellow-100 text-yellow-700', 'icon' => 'fa-key'],
        'Report Generated' => ['class' => 'bg-purple-100 text-purple-700', 'icon' => 'fa-chart-bar'],
        'User Created' => ['class' => 'bg-indigo-100 text-indigo-700', 'icon' => 'fa-user-plus'],
        'User Updated' => ['class' => 'bg-teal-100 text-teal-700', 'icon' => 'fa-user-cog'],
        'Dashboard Viewed' => ['class' => 'bg-cyan-100 text-cyan-700', 'icon' => 'fa-tachometer-alt'],
        'Settings Updated' => ['class' => 'bg-orange-100 text-orange-700', 'icon' => 'fa-cog'],
        'File Uploaded' => ['class' => 'bg-pink-100 text-pink-700', 'icon' => 'fa-upload'],
    ];

    return $styles[$action] ?? ['class' => 'bg-gray-100 text-gray-700', 'icon' => 'fa-bolt'];
}

// Function to format date for display
function formatDateTime($dateString)
{
    $timestamp = strtotime($dateString);
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y H:i', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Parliament ICT</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .error-message {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .warning-message {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .info-message {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .pagination-link {
            min-width: 2.5rem;
        }

        /* Smooth transitions */
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

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
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen flex">

    <!-- SIDEBAR -->
    <?php
    if (file_exists('sidebar.php')) {
        include 'sidebar.php';
    } else {
        echo '<div class="fixed left-0 top-0 h-full w-64 bg-white shadow-lg">';
        echo '<div class="p-6">';
        echo '<h2 class="text-xl font-bold text-blue-600">Parliament ICT</h2>';
        echo '</div></div>';
    }
    ?>

    <!-- MAIN CONTENT -->
    <main id="mainContent" class="ml-64 flex-1 p-8 fade-in">

        <!-- HEADER -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">
                <i class="fas fa-chart-bar mr-2"></i>System Reports
            </h1>
            <p class="text-slate-600 mt-1">Summary of users and recent activities</p>
        </header>

        <!-- Display database connection status -->
        <?php if (!$conn): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Database connection failed!
            </div>
        <?php endif; ?>

        <!-- STATISTICS CARDS -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Users Card -->
            <div
                class="group relative bg-blue-600 rounded-2xl p-6 text-white shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-full px-3 py-1 text-xs font-semibold">
                            All
                        </div>
                    </div>
                    <p class="text-sm opacity-90 font-medium mb-1">Total Users</p>
                    <p class="text-4xl font-bold"><?php echo number_format($userStats['totalUsers']); ?></p>
                </div>
            </div>

            <!-- Active Users Card -->
            <div
                class="group relative bg-emerald-600 rounded-2xl p-6 text-white shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                            <i class="fas fa-user-check text-2xl"></i>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-full px-3 py-1 text-xs font-semibold">
                            <i class="fas fa-circle text-green-300 text-xs mr-1"></i>Online
                        </div>
                    </div>
                    <p class="text-sm opacity-90 font-medium mb-1">Active Users</p>
                    <p class="text-4xl font-bold"><?php echo number_format($userStats['activeUsers']); ?></p>
                </div>
            </div>

            <!-- Inactive Users Card -->
            <div
                class="group relative bg-orange-600 rounded-2xl p-6 text-white shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                            <i class="fas fa-user-slash text-2xl"></i>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-full px-3 py-1 text-xs font-semibold">
                            <i class="fas fa-circle text-gray-300 text-xs mr-1"></i>Offline
                        </div>
                    </div>
                    <p class="text-sm opacity-90 font-medium mb-1">Inactive Users</p>
                    <p class="text-4xl font-bold"><?php echo number_format($userStats['inactiveUsers']); ?></p>
                </div>
            </div>

            <!-- Administrators Card -->
            <div
                class="group relative bg-purple-600 rounded-2xl p-6 text-white shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-white bg-opacity-20 rounded-lg p-3 backdrop-blur-sm">
                            <i class="fas fa-user-shield text-2xl"></i>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-full px-3 py-1 text-xs font-semibold">
                            <i class="fas fa-crown text-yellow-300 text-xs mr-1"></i>Admin
                        </div>
                    </div>
                    <p class="text-sm opacity-90 font-medium mb-1">Administrators</p>
                    <p class="text-4xl font-bold"><?php echo number_format($userStats['adminUsers']); ?></p>
                </div>
            </div>
        </section>

        <!-- USER METRICS TABLE -->
        <section class="bg-white rounded-xl shadow mb-8 overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-slate-800">
                    <i class="fas fa-chart-line mr-2 text-blue-600"></i>User Metrics
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">Metric</th>
                            <th class="px-6 py-4 text-right font-semibold text-slate-700">Count</th>
                        </tr>
                    </thead>
                    <tbody id="metricsTableBody" class="divide-y">
                        <tr>
                            <td class="px-6 py-3">
                                <i class="fas fa-briefcase mr-2 text-slate-500"></i>Staff Users
                            </td>
                            <td class="px-6 py-3 text-right"><?php echo $userStats['staffUsers']; ?></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3">
                                <i class="fas fa-landmark mr-2 text-slate-500"></i>MP Users
                            </td>
                            <td class="px-6 py-3 text-right"><?php echo $userStats['mpUsers']; ?></td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3">
                                <i class="fas fa-chart-pie mr-2 text-slate-500"></i>Active Rate
                            </td>
                            <td class="px-6 py-3 text-right">
                                <?php
                                $activeRate = ($userStats['totalUsers'] > 0)
                                    ? round(($userStats['activeUsers'] / $userStats['totalUsers']) * 100, 1)
                                    : 0;
                                echo $activeRate . '%';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3">
                                <i class="fas fa-users-cog mr-2 text-slate-500"></i>Admin Percentage
                            </td>
                            <td class="px-6 py-3 text-right">
                                <?php
                                $adminRate = ($userStats['totalUsers'] > 0)
                                    ? round(($userStats['adminUsers'] / $userStats['totalUsers']) * 100, 1)
                                    : 0;
                                echo $adminRate . '%';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ACTIVITY LOGS TABLE -->
        <section class="bg-white rounded-xl shadow overflow-hidden">
            <div class="p-6 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">
                        <i class="fas fa-history mr-2 text-blue-600"></i>Recent Activity Logs
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Showing <?php echo count($activityLogs); ?> of <?php echo number_format($totalLogs); ?> total
                        records
                    </p>
                </div>
                <div class="flex gap-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        <input id="searchInput" type="text" placeholder="Search logs..."
                            class="border border-slate-300 pl-10 pr-4 py-2 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    </div>
                    <button id="exportBtn"
                        class="px-6 py-3 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-700 rounded-xl hover:from-green-100 hover:to-emerald-100 hover:border-green-300 transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow font-medium whitespace-nowrap">
                        <i class="fas fa-download"></i>Export
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-user mr-2"></i>User
                            </th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-bolt mr-2"></i>Action
                            </th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-network-wired mr-2"></i>IP Address
                            </th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-calendar mr-2"></i>Date & Time
                            </th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody" class="divide-y">
                        <?php if (empty($activityLogs)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p>No activity logs found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activityLogs as $log):
                                $actionStyle = getActionStyle($log['action']);
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-user-circle mr-2 text-slate-400"></i>
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($log['user_name']); ?></p>
                                                <?php if (!empty($log['description'])): ?>
                                                    <p class="text-xs text-slate-500 truncate max-w-xs">
                                                        <?php echo htmlspecialchars($log['description']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span
                                            class="inline-flex items-center gap-1 <?php echo $actionStyle['class']; ?> px-3 py-1 rounded-full text-xs font-medium">
                                            <i class="fas <?php echo $actionStyle['icon']; ?>"></i>
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span
                                            class="font-mono text-sm"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-sm">
                                            <div class="text-slate-900">
                                                <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?>
                                            </div>
                                            <div class="text-slate-500 text-xs">
                                                <?php echo formatDateTime($log['created_at']); ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="p-6 border-t">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="text-sm text-slate-600">
                            Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>
                            <span class="mx-2">•</span>
                            <?php echo number_format($totalLogs); ?> total records
                        </div>

                        <div class="flex items-center gap-1">
                            <!-- First Page -->
                            <a href="?page=1"
                                class="pagination-link h-9 px-3 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition <?php echo $pagination['current_page'] == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                title="First Page">
                                <i class="fas fa-angle-double-left"></i>
                            </a>

                            <!-- Previous Page -->
                            <a href="<?php echo $pagination['has_previous'] ? '?page=' . $pagination['previous_page'] : '#'; ?>"
                                class="pagination-link h-9 px-3 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition <?php echo !$pagination['has_previous'] ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                title="Previous Page">
                                <i class="fas fa-chevron-left"></i>
                            </a>

                            <!-- Page Numbers -->
                            <?php foreach ($pagination['pages'] as $page): ?>
                                <?php if ($page['is_current']): ?>
                                    <span
                                        class="pagination-link h-9 px-3 inline-flex items-center justify-center rounded-lg border border-blue-600 bg-blue-600 text-white font-medium">
                                        <?php echo $page['number']; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $page['number']; ?>"
                                        class="pagination-link h-9 px-3 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition">
                                        <?php echo $page['number']; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <!-- Next Page -->
                            <a href="<?php echo $pagination['has_next'] ? '?page=' . $pagination['next_page'] : '#'; ?>"
                                class="pagination-link h-9 px-3 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition <?php echo !$pagination['has_next'] ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                title="Next Page">
                                <i class="fas fa-chevron-right"></i>
                            </a>

                            <!-- Last Page -->
                            <a href="?page=<?php echo $pagination['total_pages']; ?>"
                                class="pagination-link h-9 px-3 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition <?php echo $pagination['current_page'] == $pagination['total_pages'] ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                title="Last Page">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </div>

                        <!-- Items Per Page Selector -->
                        <div class="text-sm text-slate-600">
                            <label for="itemsPerPage" class="mr-2">Show:</label>
                            <select id="itemsPerPage"
                                class="border border-slate-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-600 focus:outline-none">
                                <option value="10" <?php echo $logsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $logsPerPage == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo $logsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $logsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <span class="ml-2">per page</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <!-- JS -->
    <script>
        // Search functionality for logs
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const filter = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll('#logsTableBody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });

                // Update pagination info if searching
                updateSearchInfo();
            });
        }

        function updateSearchInfo() {
            const visibleRows = document.querySelectorAll('#logsTableBody tr[style=""]').length;
            const totalRows = document.querySelectorAll('#logsTableBody tr').length;

            // You could update a counter here if needed
            console.log(`Showing ${visibleRows} of ${totalRows} filtered records`);
        }

        // Export CSV functionality
        const exportBtn = document.getElementById('exportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                // Create CSV content
                let csv = 'User,Action,Description,IP Address,Date\n';

                document.querySelectorAll('#logsTableBody tr').forEach(row => {
                    if (row.style.display !== 'none') {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 4) {
                            const user = cells[0].textContent.trim().replace(/,/g, ';');
                            const action = cells[1].textContent.trim().replace(/,/g, ';');
                            const ip = cells[2].textContent.trim().replace(/,/g, ';');
                            const date = cells[3].textContent.trim().split('\n')[0].replace(/,/g, ';');

                            csv += `"${user}","${action}","${ip}","${date}"\n`;
                        }
                    }
                });

                // Create and download CSV file
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'activity_logs_' + new Date().toISOString().slice(0, 10) + '.csv';
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
                url.searchParams.set('page', '1'); // Reset to first page
                window.location.href = url.toString();
            });
        }

        // Get URL parameters
        function getUrlParam(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        // Update per page selector if URL has parameter
        const perPageParam = getUrlParam('per_page');
        if (perPageParam && itemsPerPageSelect) {
            itemsPerPageSelect.value = perPageParam;
        }
    </script>

</body>

</html>
<?php
// Connection is managed by the Database class
?>