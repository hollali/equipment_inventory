<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . '/vendor/autoload.php';

$db = new Database();
$conn = $db->getConnection();

/* Fetch Departments and Locations for Filters */
$departmentsArr = [];
$deptResult = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name");
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departmentsArr[] = $row;
    }
}

$locationsArr = [];
$locResult = $conn->query("SELECT id, location_name FROM locations ORDER BY location_name");
if ($locResult) {
    while ($row = $locResult->fetch_assoc()) {
        $locationsArr[] = $row;
    }
}

/* Fetch Users for table */
$usersArr = [];
$userResult = $conn->query("SELECT id, firstname, lastname, email, role, status FROM users ORDER BY firstname, lastname");
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $usersArr[$row['id']] = $row;
    }
}

/* Stats - Optimized Single Query */
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM inventory_items) as total_items,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM inventory_items WHERE status='in_storage') as in_storage,
        (SELECT COUNT(*) FROM inventory_items WHERE status='retired') as retired_devices,
        (SELECT COUNT(*) FROM users WHERE status='active') as active_users,
        (SELECT COUNT(*) FROM users WHERE role='admin') as admin_users,
        (SELECT COUNT(*) FROM inventory_items WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_changes_count
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult ? $statsResult->fetch_assoc() : [
    'total_items' => 0,
    'total_users' => 0,
    'in_storage' => 0,
    'retired_devices' => 0,
    'active_users' => 0,
    'admin_users' => 0,
    'recent_changes_count' => 0
];

$totalItems = $stats['total_items'];
$totalUsers = $stats['total_users'];
$inStorage = $stats['in_storage'];
$retiredDevices = $stats['retired_devices'];
$activeUsers = $stats['active_users'];
$adminUsers = $stats['admin_users'];
$recentChangesCount = $stats['recent_changes_count'];

/* Recent Activities (Changes in Inventory) with Pagination */
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

const STATUS_LABELS = [
    'active' => 'Active',
    'retired' => 'Retired',
    'in_storage' => 'Store',
    'repairing' => 'Repairing',
    'in_use' => 'In Use',
    'faulty' => 'Faulty'
];

// Build WHERE clause for filters
$whereConditions = ["1=1"];
$params = [];
$paramTypes = "";

if (!empty($_GET['status'])) {
    $whereConditions[] = "i.status = ?";
    $params[] = $_GET['status'];
    $paramTypes .= "s";
}

if (!empty($_GET['department'])) {
    $whereConditions[] = "i.department_id = ?";
    $params[] = intval($_GET['department']);
    $paramTypes .= "i";
}

if (!empty($_GET['location'])) {
    $whereConditions[] = "i.location_id = ?";
    $params[] = intval($_GET['location']);
    $paramTypes .= "i";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

/* Get total count with filters */
$countQuery = "SELECT COUNT(*) as total FROM inventory_items i $whereClause";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($paramTypes, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalChanges = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalChanges / $perPage);

/* Get recent changes in inventory */
$recentChangesData = []; // Renamed from $recentChanges to avoid conflict
$query = " 
    SELECT 
        i.*,
        i.updated_at as change_date,
        b.brand_name AS brand_name,
        d.department_name AS department_name,
        l.location_name AS location_name,
        u.id as assigned_user_id,
        u.firstname as assigned_firstname,
        u.lastname as assigned_lastname,
        u.email as assigned_email,
        u.role as assigned_role,
        u.status as assigned_user_status
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    LEFT JOIN users u ON i.assigned_user = u.id
    $whereClause
    ORDER BY i.updated_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;
$paramTypes .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentChangesData[] = $row;
}

/* ================== FILTER INPUTS ================== */
$filterStatus = $_GET['status'] ?? '';
$filterDepartment = $_GET['department'] ?? '';
$filterLocation = $_GET['location'] ?? '';

// Function to determine change type based on timestamp and current values
function getChangeType($item)
{
    // Determine change type based on how recently it was updated and current state
    $updateTime = strtotime($item['updated_at']);
    $now = time();
    $hoursSinceUpdate = ($now - $updateTime) / 3600;

    // If updated very recently (last 24 hours)
    if ($hoursSinceUpdate < 24) {
        // Check current state to guess what might have changed
        if (!empty($item['assigned_user'])) {
            // If recently updated and has an assigned user, likely an assignment
            return 'assigned';
        } elseif (empty($item['assigned_user']) && $item['status'] === 'in_storage') {
            // If recently updated, no assigned user, and in storage
            return 'unassigned';
        } elseif ($item['status'] === 'retired') {
            // If recently updated and status is retired
            return 'retired';
        } elseif ($item['status'] === 'repairing') {
            // If recently updated and status is repairing
            return 'repair';
        }
    }

    // Default to updated if we can't determine
    return 'updated';
}

// Function to get change icon
function getChangeIcon($changeType)
{
    $icons = [
        'assigned' => 'fa-user-check',
        'unassigned' => 'fa-user-times',
        'status_changed' => 'fa-sync',
        'department_changed' => 'fa-building',
        'location_changed' => 'fa-location-dot',
        'checkout' => 'fa-sign-out-alt',
        'return' => 'fa-sign-in-alt',
        'created' => 'fa-plus-circle',
        'updated' => 'fa-edit',
        'repair' => 'fa-tools',
        'retired' => 'fa-archive',
        'transfer' => 'fa-exchange-alt'
    ];

    return $icons[$changeType] ?? 'fa-history';
}

// Function to get change color
function getChangeColor($changeType)
{
    $colors = [
        'assigned' => 'from-blue-500 to-blue-600',
        'unassigned' => 'from-gray-500 to-gray-600',
        'status_changed' => 'from-purple-500 to-purple-600',
        'department_changed' => 'from-indigo-500 to-indigo-600',
        'location_changed' => 'from-teal-500 to-teal-600',
        'checkout' => 'from-green-500 to-green-600',
        'return' => 'from-yellow-500 to-yellow-600',
        'created' => 'from-emerald-500 to-emerald-600',
        'updated' => 'from-amber-500 to-amber-600',
        'repair' => 'from-orange-500 to-orange-600',
        'retired' => 'from-red-500 to-red-600',
        'transfer' => 'from-pink-500 to-pink-600'
    ];

    return $colors[$changeType] ?? 'from-gray-500 to-gray-600';
}

// Function to get change description
function getChangeDescription($item, $changeType)
{
    $descriptions = [
        'assigned' => 'Device assigned to user',
        'unassigned' => 'Device unassigned from user',
        'status_changed' => 'Status updated',
        'department_changed' => 'Department updated',
        'location_changed' => 'Location updated',
        'checkout' => 'Device checked out',
        'return' => 'Device returned',
        'created' => 'New device added',
        'updated' => 'Device information updated',
        'repair' => 'Device sent for repair',
        'retired' => 'Device retired',
        'transfer' => 'Device transferred'
    ];

    $desc = $descriptions[$changeType] ?? 'Device updated';

    // Add specific details
    if ($changeType === 'assigned' && !empty($item['assigned_firstname'])) {
        $desc .= " to " . $item['assigned_firstname'] . " " . $item['assigned_lastname'];
    } elseif ($changeType === 'retired') {
        $desc = "Device retired from inventory";
    } elseif ($changeType === 'repair') {
        $desc = "Device sent for repair";
    }

    return $desc;
}

// Function to format time ago
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y g:i A', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .role-admin {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .role-user {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
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
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-4 md:p-8 ml-0 md:ml-64">

        <!-- Header -->
        <div class="mb-8 animate-fade-in-up">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1
                        class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Dashboard Overview
                    </h1>
                    <p class="text-gray-600 text-sm mt-2 flex items-center gap-2">
                        <i class="fas fa-calendar-day text-blue-500"></i>
                        <?= date('l, F j, Y') ?> • Welcome back!
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-shield-alt"></i>
                        <span class="font-semibold text-sm">ADMIN</span>
                    </div>
                    <button
                        class="w-10 h-10 rounded-xl bg-white shadow-md flex items-center justify-center hover:shadow-lg transition-shadow">
                        <i class="fas fa-bell text-gray-600"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <?php
            $statsData = [
                [
                    'title' => 'Total Devices',
                    'value' => $totalItems,
                    'icon' => 'fa-boxes-stacked',
                    'gradient' => 'from-blue-500 to-blue-600',
                    'change' => 12,
                ],
                [
                    'title' => 'Total Users',
                    'value' => $totalUsers,
                    'icon' => 'fa-users',
                    'gradient' => 'from-green-500 to-green-600',
                    'change' => 8,
                ],
                [
                    'title' => 'Active Users',
                    'value' => $activeUsers,
                    'icon' => 'fa-user-check',
                    'gradient' => 'from-emerald-500 to-emerald-600',
                    'change' => 5,
                ],
                [
                    'title' => 'Recent Changes',
                    'value' => $recentChangesCount, // Changed from $recentChanges to $recentChangesCount
                    'icon' => 'fa-history',
                    'gradient' => 'from-purple-500 to-purple-600',
                    'change' => 15,
                ],
            ];
            ?>

            <?php foreach ($statsData as $index => $stat):
                $isPositive = $stat['change'] > 0;
                $isNegative = $stat['change'] < 0;

                $trendColor = $isPositive
                    ? 'text-green-600'
                    : ($isNegative ? 'text-red-600' : 'text-gray-400');

                $trendIcon = $isPositive
                    ? 'fa-arrow-up'
                    : ($isNegative ? 'fa-arrow-down' : 'fa-minus');
                ?>
                <div class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up"
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
                                <?= $stat['title'] ?>
                            </p>

                            <p class="text-3xl font-bold text-gray-800">
                                <?= number_format($stat['value']) ?>
                            </p>
                            <?php if ($stat['change'] !== 0): ?>
                                <div class="mt-3 flex items-center gap-1">
                                    <span class="text-xs font-semibold flex items-center gap-1 <?= $trendColor ?>">
                                        <i class="fas <?= $trendIcon ?>"></i>
                                        <?= abs($stat['change']) ?>%
                                    </span>
                                    <span class="text-xs text-gray-400">vs last month</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Store and Retired Devices Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div
                class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-yellow-500 to-yellow-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-warehouse text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-medium mb-1">Devices in Store</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($inStorage) ?></p>
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-xs font-semibold text-green-600 flex items-center gap-1">
                                <i class="fas fa-arrow-up"></i>3%
                            </span>
                            <span class="text-xs text-gray-400">vs last month</span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-archive text-white text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-500 font-medium mb-1">Retired Devices</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($retiredDevices) ?></p>
                        <div class="mt-2 flex items-center gap-1">
                            <span class="text-xs font-semibold text-gray-400 flex items-center gap-1">
                                <i class="fas fa-minus"></i>0%
                            </span>
                            <span class="text-xs text-gray-400">vs last month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filter Panel (Hidden by default) -->
        <form method="GET">
            <div id="filterPanel" class="hidden glass-effect rounded-2xl shadow-lg p-6 mb-6 border border-gray-100">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-filter text-blue-500"></i>
                        Advanced Filters
                    </h3>

                    <button type="button" onclick="clearFilters()" class="text-sm text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times-circle mr-1"></i>Clear All
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Status</label>
                        <select id="filterStatus" name="status"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                            <option value="">All Status</option>

                            <?php foreach (STATUS_LABELS as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($filterStatus === $value) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Department -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Department</label>
                        <select id="filterDepartment" name="department"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Departments</option>
                            <?php foreach ($departmentsArr as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($filterDepartment == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Location</label>
                        <select id="filterLocation" name="location"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Locations</option>
                            <?php foreach ($locationsArr as $l): ?>
                                <option value="<?= $l['id'] ?>" <?= ($filterLocation == $l['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['location_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <!-- Apply Button -->
                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition">
                        Apply Filters
                    </button>
                </div>

            </div>
        </form>


        <!-- Search and Actions Card -->
        <div class="glass-effect rounded-2xl shadow-lg p-6 mb-6 border border-gray-100">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-history text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Recent Inventory Changes</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <span class="font-semibold text-blue-600"><?= number_format($totalChanges) ?></span>
                            changes tracked • Showing latest updates
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div class="relative flex-1 lg:flex-initial">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input id="searchInput" type="text" placeholder="Search changes, devices, users..."
                            autocomplete="off"
                            class="w-full lg:w-80 pl-11 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <button onclick="toggleFilters()"
                        class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl text-sm font-medium hover:shadow-lg transition-all flex items-center gap-2">
                        <i class="fas fa-sliders"></i> Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Changes Timeline -->
        <div class="glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100 mb-8">
            <div class="p-6">
                <div class="space-y-6">
                    <?php if (empty($recentChangesData)): ?>
                        <div class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="fas fa-inbox text-4xl text-gray-300"></i>
                                </div>
                                <p class="text-gray-400 font-medium">No recent changes found</p>
                                <p class="text-xs text-gray-400">Changes will appear here when devices are updated</p>
                            </div>
                        </div>
                    <?php else:
                        foreach ($recentChangesData as $index => $change):
                            $changeType = getChangeType($change);
                            $changeIcon = getChangeIcon($changeType);
                            $changeColor = getChangeColor($changeType);
                            $changeDescription = getChangeDescription($change, $changeType);
                            $timeAgo = timeAgo($change['updated_at']);

                            // Get assigned user information
                            $assignedUserName = '';
                            $assignedUserInitials = '';
                            if (!empty($change['assigned_firstname'])) {
                                $assignedUserName = trim($change['assigned_firstname'] . ' ' . $change['assigned_lastname']);
                                $assignedUserInitials = substr($change['assigned_firstname'], 0, 1) . substr($change['assigned_lastname'], 0, 1);
                            } elseif (!empty($change['assigned_user'])) {
                                $assignedUserName = $change['assigned_user'];
                                $assignedUserInitials = substr($change['assigned_user'], 0, 2);
                            }
                            ?>
                            <div class="activity-item flex gap-4">
                                <!-- Change Icon -->
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-12 h-12 rounded-full bg-gradient-to-br <?= $changeColor ?> flex items-center justify-center shadow-lg">
                                        <i class="fas <?= $changeIcon ?> text-white text-lg"></i>
                                    </div>
                                </div>

                                <!-- Change Content -->
                                <div
                                    class="flex-1 bg-gray-50 rounded-xl p-4 border border-gray-200 hover:bg-gray-100 transition-colors">
                                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                                        <div class="flex-1">
                                            <!-- Device Info -->
                                            <div class="flex items-center gap-2 mb-2">
                                                <h3 class="font-bold text-lg text-gray-900">
                                                    <?= htmlspecialchars($change['brand_name'] ?? 'Unknown Device') ?>
                                                    <?= htmlspecialchars($change['model'] ?? '') ?>
                                                </h3>
                                                <span
                                                    class="text-sm px-3 py-1 rounded-lg bg-blue-100 text-blue-700 font-semibold">
                                                    <?= htmlspecialchars($change['asset_tag'] ?? 'N/A') ?>
                                                </span>
                                            </div>

                                            <!-- Change Description -->
                                            <p class="text-gray-700 mb-3">
                                                <i class="fas <?= $changeIcon ?> text-gray-400 mr-2"></i>
                                                <?= $changeDescription ?>
                                            </p>

                                            <!-- Device Details -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                                                <?php if (!empty($change['department_name'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-building text-gray-400"></i>
                                                        <span class="font-medium">Dept:</span>
                                                        <span
                                                            class="text-gray-700"><?= htmlspecialchars($change['department_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($change['location_name'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-location-dot text-gray-400"></i>
                                                        <span class="font-medium">Location:</span>
                                                        <span
                                                            class="text-gray-700"><?= htmlspecialchars($change['location_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($change['status'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-circle-info text-gray-400"></i>
                                                        <span class="font-medium">Status:</span>
                                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?=
                                                            $change['status'] === 'active' ? 'bg-green-100 text-green-700' :
                                                            ($change['status'] === 'in_storage' ? 'bg-yellow-100 text-yellow-700' :
                                                                ($change['status'] === 'retired' ? 'bg-red-100 text-red-700' :
                                                                    ($change['status'] === 'repairing' ? 'bg-orange-100 text-orange-700' :
                                                                        'bg-gray-100 text-gray-700'))) ?>">
                                                            <?= STATUS_LABELS[$change['status']] ?? ucfirst($change['status']) ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($change['serial_number'])): ?>
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <i class="fas fa-hashtag text-gray-400"></i>
                                                        <span class="font-medium">Serial:</span>
                                                        <span
                                                            class="text-gray-700"><?= htmlspecialchars($change['serial_number']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Assigned User Info -->
                                            <?php if (!empty($assignedUserName)): ?>
                                                <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200">
                                                    <div
                                                        class="w-10 h-10 rounded-full <?= $change['assigned_user_status'] === 'active' ? 'user-status-active' : 'user-status-inactive' ?> flex items-center justify-center text-white text-sm font-bold shadow-sm">
                                                        <?= strtoupper($assignedUserInitials) ?>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-800">Assigned to:
                                                            <?= htmlspecialchars($assignedUserName) ?></p>
                                                        <?php if (!empty($change['assigned_email'])): ?>
                                                            <p class="text-xs text-gray-500">
                                                                <?= htmlspecialchars($change['assigned_email']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php elseif ($changeType === 'unassigned'): ?>
                                                <div
                                                    class="flex items-center gap-3 p-3 bg-gray-100 rounded-lg border border-gray-200">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-700 text-sm font-bold shadow-sm">
                                                        <i class="fas fa-user-slash"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-800">Device is currently unassigned</p>
                                                        <p class="text-xs text-gray-500">Available for assignment</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Timestamp -->
                                        <div class="flex flex-col items-end gap-2">
                                            <span class="text-xs text-gray-500 whitespace-nowrap"
                                                title="<?= htmlspecialchars($change['updated_at']) ?>">
                                                <i class="fas fa-clock mr-1"></i><?= $timeAgo ?>
                                            </span>
                                            <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-700">
                                                Updated
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
                            <span class="font-semibold text-blue-600"><?= min($offset + $perPage, $totalChanges) ?></span>
                            of
                            <span class="font-semibold text-blue-600"><?= number_format($totalChanges) ?></span> changes
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>"
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
                                <a href="?page=<?= $i ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-all font-medium <?= $activeClass ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>"
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

    <!-- JS -->
    <script>
        // Live search
        const searchInput = document.getElementById('searchInput');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            const activities = document.querySelectorAll('.activity-item');

            activities.forEach(activity => {
                const text = activity.textContent.toLowerCase();
                activity.style.display = text.includes(query) ? '' : 'none';
            });
        });

        function toggleFilters() {
            const panel = document.getElementById('filterPanel');
            panel.classList.toggle('hidden');
        }

        function clearFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDepartment').value = '';
            document.getElementById('filterLocation').value = '';
        }
    </script>

</body>

</html>