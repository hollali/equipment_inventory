<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/config/database.php";

// Check if user is logged in and has admin privileges
/*if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}*/

$db = new Database();
$conn = $db->getConnection();


/* Fetch Departments, Locations, and Categories for Filters */
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
        $locationsArr[] = ['id' => $row['id'], 'location_name' => $row['location_name']];
    }
}

// Get categories from categories table
$categoriesArr = [];
$catResult = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categoriesArr[] = $row;
    }
}

/* Fetch all users for assignment dropdown */
$usersArr = [];
$userResult = $conn->query("SELECT id, firstname, lastname, email FROM users WHERE status='active' ORDER BY firstname, lastname");
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $usersArr[] = $row;
    }
}

/* Get unassigned devices count */
$unassignedCountQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE (assigned_user IS NULL OR assigned_user = '') AND status != 'retired'";
$unassignedCountResult = $conn->query($unassignedCountQuery);
$unassignedCount = $unassignedCountResult->fetch_assoc()['count'];

/* Get unassigned devices with pagination */
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build WHERE clause for filters
$whereConditions = ["(i.assigned_user IS NULL OR i.assigned_user = '')", "i.status != 'retired'"];
$params = [];
$paramTypes = "";

if (!empty($_GET['status'])) {
    $whereConditions[] = "i.status = ?";
    $params[] = $_GET['status'];
    $paramTypes .= "s";
}

if (!empty($_GET['department'])) {
    $whereConditions[] = "i.department = ?";
    $params[] = $_GET['department'];
    $paramTypes .= "s";
}

if (!empty($_GET['location'])) {
    $whereConditions[] = "i.location = ?";
    $params[] = $_GET['location'];
    $paramTypes .= "s";
}

if (!empty($_GET['brand'])) {
    $whereConditions[] = "i.brand_id = ?";
    $params[] = intval($_GET['brand']);
    $paramTypes .= "i";
}

if (!empty($_GET['category'])) {
    $whereConditions[] = "i.category_id = ?";
    $params[] = intval($_GET['category']);
    $paramTypes .= "i";
}

if (!empty($_GET['condition'])) {
    $whereConditions[] = "i.condition = ?";
    $params[] = $_GET['condition'];
    $paramTypes .= "s";
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
$totalUnassigned = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalUnassigned / $perPage);

/* Get unassigned devices */
$unassignedDevices = [];
$query = " 
    SELECT 
        i.*,
        b.brand_name AS brand_name,
        d.department_name AS department_name,
        l.location_name AS location_name,
        c.category_name AS category_name
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    LEFT JOIN categories c ON i.category_id = c.id
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
    $unassignedDevices[] = $row;
}

/* ================== FILTER INPUTS ================== */
$filterStatus = $_GET['status'] ?? '';
$filterDepartment = $_GET['department'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterBrand = $_GET['brand'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$filterCondition = $_GET['condition'] ?? '';

// Get brands for filter
$brandsArr = [];
$brandResult = $conn->query("SELECT id, brand_name FROM brands ORDER BY brand_name");
if ($brandResult) {
    while ($row = $brandResult->fetch_assoc()) {
        $brandsArr[] = $row;
    }
}

// Status and condition arrays
$statusColors = [
    'active' => 'bg-green-100 text-green-700 border-green-200',
    'in_use' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    'in_storage' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'repairing' => 'bg-gray-100 text-gray-700 border-gray-200',
    'faulty' => 'bg-pink-100 text-pink-700 border-pink-200',
    'retired' => 'bg-red-100 text-red-700 border-red-200'
];

$statusLabels = [
    'active' => 'Active',
    'in_use' => 'In Use',
    'in_storage' => 'Store',
    'repairing' => 'Repairing',
    'faulty' => 'Faulty',
    'retired' => 'Retired'
];

$conditionColors = [
    'new' => 'bg-blue-100 text-blue-700 border-blue-200',
    'good' => 'bg-green-100 text-green-700 border-green-200',
    'fair' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'poor' => 'bg-orange-100 text-orange-700 border-orange-200',
    'faulty' => 'bg-red-100 text-red-700 border-red-200'
];

$conditionLabels = [
    'new' => 'New',
    'good' => 'Good',
    'fair' => 'Fair',
    'poor' => 'Poor',
    'faulty' => 'Faulty'
];

// Filter options for dropdowns
$statusOptions = [
    'in_use' => 'In Use',
    'in_storage' => 'Store',
    'faulty' => 'Faulty'
];

$conditionOptions = [
    'new' => 'New',
    'good' => 'Good',
    'fair' => 'Fair',
    'poor' => 'Poor',
    'faulty' => 'Faulty'
];

// Function to get status display
function getStatusDisplay($status, $statusColors, $statusLabels)
{
    $status = strtolower($status);
    $status = str_replace(' ', '_', $status);

    $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
    $label = $statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status));

    return ['color' => $colorClass, 'label' => $label];
}

// Function to get condition display
function getConditionDisplay($condition, $conditionColors, $conditionLabels)
{
    $condition = strtolower($condition);

    $colorClass = $conditionColors[$condition] ?? 'bg-gray-100 text-gray-700 border-gray-200';
    $label = $conditionLabels[$condition] ?? ucwords($condition);

    return ['color' => $colorClass, 'label' => $label];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Unassigned Devices - Equipment Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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

        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid;
        }

        .condition-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            border: 1px solid;
        }

        .select2-container--default .select2-selection--single {
            height: 44px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 8px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
        }

        .device-card {
            transition: all 0.3s ease;
        }

        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* Table styling */
        .table-header {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-row-hover:hover {
            background-color: #f8fafc;
            transform: scale(1.002);
            transition: all 0.2s ease;
        }

        .table-cell {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .asset-tag {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .serial-number {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 11px;
            color: #64748b;
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
                        Unassigned Devices
                    </h1>
                    <p class="text-gray-600 text-sm mt-2 flex items-center gap-2">
                        <i class="fas fa-boxes-stacked text-blue-500"></i>
                        Manage devices that are not assigned to any user
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-tools"></i>
                        <span class="font-semibold text-sm">INVENTORY</span>
                    </div>
                    <a href="dashboard.php"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-laptop text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Unassigned</p>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($unassignedCount) ?></p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">In Store</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php
                            $storeQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE (assigned_user IS NULL OR assigned_user = '') AND status = 'in_storage'";
                            $storeResult = $conn->query($storeQuery);
                            echo number_format($storeResult->fetch_assoc()['count']);
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-yellow-500 to-yellow-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-star text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">New Condition</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php
                            $newQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE (assigned_user IS NULL OR assigned_user = '') AND `condition` = 'new'";
                            $newResult = $conn->query($newQuery);
                            echo number_format($newResult->fetch_assoc()['count']);
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Faulty Devices</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php
                            $faultyQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE (assigned_user IS NULL OR assigned_user = '') AND status = 'faulty'";
                            $faultyResult = $conn->query($faultyQuery);
                            echo number_format($faultyResult->fetch_assoc()['count']);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <form method="GET" id="filterForm">
            <div class="glass-effect rounded-2xl shadow-lg p-6 mb-6 border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-filter text-blue-500"></i>
                        Filter Devices
                    </h3>
                    <button type="button" onclick="clearFilters()" class="text-sm text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times-circle mr-1"></i>Clear Filters
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Status</label>
                        <select id="filterStatus" name="status" class="filter-select w-full">
                            <option value="">All Status</option>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($filterStatus === $value) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Department -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Department</label>
                        <select id="filterDepartment" name="department" class="filter-select w-full">
                            <option value="">All Departments</option>
                            <?php foreach ($departmentsArr as $d): ?>
                                <option value="<?= htmlspecialchars($d['department_name']) ?>"
                                    <?= ($filterDepartment === $d['department_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Location</label>
                        <select id="filterLocation" name="location" class="filter-select w-full">
                            <option value="">All Locations</option>
                            <?php foreach ($locationsArr as $l): ?>
                                <option value="<?= htmlspecialchars($l['location_name']) ?>"
                                    <?= ($filterLocation === $l['location_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['location_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Brand -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Brand</label>
                        <select id="filterBrand" name="brand" class="filter-select w-full">
                            <option value="">All Brands</option>
                            <?php foreach ($brandsArr as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ($filterBrand == $b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['brand_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Category</label>
                        <select id="filterCategory" name="category" class="filter-select w-full">
                            <option value="">All Categories</option>
                            <?php foreach ($categoriesArr as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($filterCategory == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Condition -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Condition</label>
                        <select id="filterCondition" name="condition" class="filter-select w-full">
                            <option value="">All Conditions</option>
                            <?php foreach ($conditionOptions as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($filterCondition === $value) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Apply Button -->
                <div class="mt-6 flex justify-end gap-2">
                    <button type="submit"
                        class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition flex items-center gap-2">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" onclick="exportUnassigned()"
                        class="bg-green-600 text-white px-5 py-2.5 rounded-xl hover:bg-green-700 transition flex items-center gap-2">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>
        </form>

        <!-- View Toggle Buttons -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Unassigned Devices List</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Showing <?= number_format(min($offset + 1, $totalUnassigned)) ?> -
                    <?= number_format(min($offset + $perPage, $totalUnassigned)) ?> of
                    <?= number_format($totalUnassigned) ?> devices
                </p>
            </div>
            <div class="flex gap-2">
                <button id="tableViewBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                    <i class="fas fa-table"></i> Table View
                </button>
                <button id="cardViewBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg">
                    <i class="fas fa-th-large"></i> Card View
                </button>
            </div>
        </div>

        <!-- Table View -->
        <div id="tableView" class="glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100 mb-8">
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Devices in Table View</h3>
                    </div>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchTable" placeholder="Search devices..."
                            class="pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full md:w-64">
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="table-header">
                            <th
                                class="py-4 px-6 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-laptop text-gray-500"></i>
                                    Device Info
                                </div>
                            </th>
                            <th
                                class="py-4 px-6 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-tag text-gray-500"></i>
                                    Asset Tag
                                </div>
                            </th>
                            <th
                                class="py-4 px-6 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-building text-gray-500"></i>
                                    Location
                                </div>
                            </th>
                            <th
                                class="py-4 px-6 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-battery-half text-gray-500"></i>
                                    Status
                                </div>
                            </th>
                            <th
                                class="py-4 px-6 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-calendar-alt text-gray-500"></i>
                                    Last Updated
                                </div>
                            </th>
                            <th
                                class="py-4 px-6 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-cogs text-gray-500"></i>
                                    Actions
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($unassignedDevices)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-check-circle text-4xl text-green-400"></i>
                                        </div>
                                        <p class="text-gray-400 font-medium">All devices are assigned!</p>
                                        <p class="text-xs text-gray-400">No unassigned devices found with current filters
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($unassignedDevices as $device): ?>
                                <?php
                                // Get status and condition display
                                $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                                $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);
                                ?>
                                <tr class="table-row-hover transition-all duration-200">
                                    <!-- Device Info -->
                                    <td class="table-cell">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                                                <?php
                                                $deviceType = strtolower($device['device_type'] ?? 'laptop');
                                                if (strpos($deviceType, 'phone') !== false || strpos($deviceType, 'mobile') !== false): ?>
                                                    <i class="fas fa-mobile-alt text-blue-600"></i>
                                                <?php elseif (strpos($deviceType, 'tablet') !== false): ?>
                                                    <i class="fas fa-tablet-alt text-blue-600"></i>
                                                <?php elseif (strpos($deviceType, 'desktop') !== false || strpos($deviceType, 'pc') !== false): ?>
                                                    <i class="fas fa-desktop text-blue-600"></i>
                                                <?php elseif (strpos($deviceType, 'printer') !== false): ?>
                                                    <i class="fas fa-print text-blue-600"></i>
                                                <?php elseif (strpos($deviceType, 'server') !== false): ?>
                                                    <i class="fas fa-server text-blue-600"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-laptop text-blue-600"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900">
                                                    <?= htmlspecialchars($device['brand_name'] ?? 'Unknown') ?>
                                                    <?= !empty($device['model']) ? ' ' . htmlspecialchars($device['model']) : '' ?>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <?= htmlspecialchars($device['device_type'] ?? 'Device') ?>
                                                    <?php if (!empty($device['category_name'])): ?>
                                                        • <?= htmlspecialchars($device['category_name']) ?>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if (!empty($device['serial_number'])): ?>
                                                    <p class="serial-number mt-1">SN:
                                                        <?= htmlspecialchars($device['serial_number']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Asset Tag -->
                                    <td class="table-cell">
                                        <span
                                            class="asset-tag text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                            <?= htmlspecialchars($device['asset_tag'] ?? 'N/A') ?>
                                        </span>
                                    </td>

                                    <!-- Location -->
                                    <td class="table-cell">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-building text-gray-400 text-sm"></i>
                                                <span
                                                    class="text-gray-700 font-medium"><?= htmlspecialchars($device['department'] ?? 'N/A') ?></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-location-dot text-gray-400 text-sm"></i>
                                                <span
                                                    class="text-gray-600 text-sm"><?= htmlspecialchars($device['location'] ?? 'N/A') ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="table-cell">
                                        <div class="space-y-2">
                                            <span class="condition-badge <?= $conditionDisplay['color'] ?>">
                                                <?= htmlspecialchars($conditionDisplay['label']) ?>
                                            </span>
                                            <br>
                                            <span class="status-badge <?= $statusDisplay['color'] ?>">
                                                <?= htmlspecialchars($statusDisplay['label']) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Last Updated -->
                                    <td class="table-cell">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-gray-700">
                                                <?= date('M j, Y', strtotime($device['updated_at'] ?? 'now')) ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <?= date('g:i A', strtotime($device['updated_at'] ?? 'now')) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="table-cell">
                                        <div class="flex gap-2">
                                            <button
                                                onclick="openAssignModal(<?= htmlspecialchars(json_encode($device)) ?>, <?= htmlspecialchars(json_encode($usersArr)) ?>)"
                                                class="action-btn bg-blue-500 text-white hover:bg-blue-600"
                                                title="Assign Device">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                            <button onclick="viewDeviceDetails(<?= htmlspecialchars(json_encode($device)) ?>)"
                                                class="action-btn bg-purple-500 text-white hover:bg-purple-600"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-2">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++):
                                $activeClass = $i === $page
                                    ? 'bg-blue-500 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-50';
                                ?>
                                <a href="?page=<?= $i ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-all <?= $activeClass ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-2">
                                    Next <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Card View -->
        <div id="cardView" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php if (empty($unassignedDevices)): ?>
                <div class="col-span-3">
                    <div class="glass-effect rounded-2xl shadow-lg p-12 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                <i class="fas fa-check-circle text-4xl text-green-400"></i>
                            </div>
                            <p class="text-gray-400 font-medium">All devices are assigned!</p>
                            <p class="text-xs text-gray-400">No unassigned devices found with current filters</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($unassignedDevices as $device): ?>
                    <?php
                    // Get status and condition display
                    $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                    $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);
                    ?>
                    <div class="device-card glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                        <!-- Card Header -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                                        <?php
                                        $deviceType = strtolower($device['device_type'] ?? 'laptop');
                                        if (strpos($deviceType, 'phone') !== false || strpos($deviceType, 'mobile') !== false): ?>
                                            <i class="fas fa-mobile-alt text-blue-600 text-lg"></i>
                                        <?php elseif (strpos($deviceType, 'tablet') !== false): ?>
                                            <i class="fas fa-tablet-alt text-blue-600 text-lg"></i>
                                        <?php elseif (strpos($deviceType, 'desktop') !== false || strpos($deviceType, 'pc') !== false): ?>
                                            <i class="fas fa-desktop text-blue-600 text-lg"></i>
                                        <?php elseif (strpos($deviceType, 'printer') !== false): ?>
                                            <i class="fas fa-print text-blue-600 text-lg"></i>
                                        <?php elseif (strpos($deviceType, 'server') !== false): ?>
                                            <i class="fas fa-server text-blue-600 text-lg"></i>
                                        <?php else: ?>
                                            <i class="fas fa-laptop text-blue-600 text-lg"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900">
                                            <?= htmlspecialchars($device['brand_name'] ?? 'Unknown') ?>
                                        </h3>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($device['model'] ?? 'N/A') ?></p>
                                        <p class="text-xs text-gray-400">
                                            <?= htmlspecialchars($device['device_type'] ?? 'N/A') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6">
                            <!-- Asset Tag -->
                            <div class="mb-4">
                                <span
                                    class="font-semibold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                    <?= htmlspecialchars($device['asset_tag'] ?? 'N/A') ?>
                                </span>
                            </div>

                            <!-- Condition & Status -->
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="condition-badge <?= $conditionDisplay['color'] ?>">
                                    <?= htmlspecialchars($conditionDisplay['label']) ?>
                                </span>
                                <span class="status-badge <?= $statusDisplay['color'] ?>">
                                    <?= htmlspecialchars($statusDisplay['label']) ?>
                                </span>
                            </div>

                            <!-- Serial Number -->
                            <?php if (!empty($device['serial_number'])): ?>
                                <div class="mb-4">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium">Serial Number:</span>
                                        <?= htmlspecialchars($device['serial_number']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Location Info -->
                            <div class="space-y-2 mb-6">
                                <div class="flex items-center gap-2 text-sm">
                                    <i class="fas fa-building text-gray-400"></i>
                                    <span class="text-gray-700"><?= htmlspecialchars($device['department'] ?? 'N/A') ?></span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <i class="fas fa-location-dot text-gray-400"></i>
                                    <span class="text-gray-700"><?= htmlspecialchars($device['location'] ?? 'N/A') ?></span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <i class="fas fa-tag text-gray-400"></i>
                                    <span
                                        class="text-gray-700"><?= htmlspecialchars($device['category_name'] ?? 'N/A') ?></span>
                                </div>
                            </div>

                            <!-- Last Updated -->
                            <div class="text-xs text-gray-500 mb-6">
                                <i class="fas fa-clock mr-1"></i>
                                Last updated: <?= date('M j, Y', strtotime($device['updated_at'] ?? 'now')) ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <button
                                    onclick="openAssignModal(<?= htmlspecialchars(json_encode($device)) ?>, <?= htmlspecialchars(json_encode($usersArr)) ?>)"
                                    class="flex-1 px-4 py-2.5 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-all flex items-center justify-center gap-2"
                                    title="Assign Device">
                                    <i class="fas fa-user-plus"></i> Assign
                                </button>
                                <button onclick="viewDeviceDetails(<?= htmlspecialchars(json_encode($device)) ?>)"
                                    class="flex-1 px-4 py-2.5 bg-purple-500 text-white rounded-lg text-sm font-medium hover:bg-purple-600 transition-all flex items-center justify-center gap-2"
                                    title="View Details">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- Assign Device Modal -->
    <div id="assignModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden animate-fade-in-up">
            <div class="sticky top-0 bg-white border-b border-gray-200 p-6">
                <button onclick="closeAssignModal()"
                    class="absolute top-6 right-6 text-gray-400 hover:text-gray-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-user-plus text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Assign Device</h2>
                        <p class="text-gray-500 text-sm mt-1">Assign device to a user</p>
                    </div>
                </div>
            </div>

            <form id="assignForm" method="POST" action="process_assign.php" class="p-6">
                <input type="hidden" id="deviceId" name="device_id">
                <input type="hidden" id="assetTag" name="asset_tag">

                <!-- Device Info -->
                <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-2">Device Information</h3>
                    <p class="text-sm text-gray-600" id="deviceInfo"></p>
                </div>

                <!-- User Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select User *</label>
                    <select id="userId" name="user_id" required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Choose a user...</option>
                        <?php foreach ($usersArr as $user): ?>
                            <option value="<?= $user['id'] ?>">
                                <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['email'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Update -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Update Device Status *</label>
                    <select id="newStatus" name="new_status" required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <?php if ($value != 'retired' && $value != 'active'): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Condition Update -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Update Device Condition</label>
                    <select id="newCondition" name="new_condition"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <?php foreach ($conditionLabels as $value => $label): ?>
                            <option value="<?= $value ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Notes -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Remarks (Optional)</label>
                    <textarea id="assignRemarks" name="assign_remarks" rows="3"
                        placeholder="Add any remarks about this assignment..."
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button type="button" onclick="closeAssignModal()"
                        class="flex-1 px-4 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 px-4 py-3 bg-blue-500 text-white rounded-xl font-semibold hover:bg-blue-600 transition-all">
                        Assign Device
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Device Details Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden animate-fade-in-up">
            <div class="sticky top-0 bg-white border-b border-gray-200 p-6">
                <button onclick="closeViewModal()"
                    class="absolute top-6 right-6 text-gray-400 hover:text-gray-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Device Details</h2>
                        <p class="text-gray-500 text-sm mt-1" id="deviceTitle"></p>
                    </div>
                </div>
            </div>
            <div id="deviceDetails" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 120px);">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Initialize Select2
        $(document).ready(function () {
            $('.filter-select').select2({
                placeholder: "Select option...",
                allowClear: true
            });

            $('#userId').select2({
                placeholder: "Select a user...",
                dropdownParent: $('#assignModal')
            });

            // View toggle functionality
            $('#tableViewBtn').click(function () {
                $('#tableView').removeClass('hidden');
                $('#cardView').addClass('hidden');
                $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-blue-600 text-white');
                $('#cardViewBtn').removeClass('bg-blue-600 text-white').addClass('bg-gray-200 text-gray-700');
            });

            $('#cardViewBtn').click(function () {
                $('#cardView').removeClass('hidden');
                $('#tableView').addClass('hidden');
                $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-blue-600 text-white');
                $('#tableViewBtn').removeClass('bg-blue-600 text-white').addClass('bg-gray-200 text-gray-700');
            });
        });

        // Table search functionality
        document.getElementById('searchTable').addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#tableView tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Card view search functionality
        document.getElementById('searchTable').addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('#cardView .device-card');

            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        function clearFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDepartment').value = '';
            document.getElementById('filterLocation').value = '';
            document.getElementById('filterBrand').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterCondition').value = '';

            // Reset Select2
            $('.filter-select').val(null).trigger('change');

            // Submit form
            document.getElementById('filterForm').submit();
        }

        function exportUnassigned() {
            // Get current filters
            const status = document.getElementById('filterStatus').value;
            const department = document.getElementById('filterDepartment').value;
            const location = document.getElementById('filterLocation').value;
            const brand = document.getElementById('filterBrand').value;
            const category = document.getElementById('filterCategory').value;
            const condition = document.getElementById('filterCondition').value;

            // Build query string
            const params = new URLSearchParams();
            if (status) params.append('status', status);
            if (department) params.append('department', department);
            if (location) params.append('location', location);
            if (brand) params.append('brand', brand);
            if (category) params.append('category', category);
            if (condition) params.append('condition', condition);

            // Redirect to export script
            window.location.href = `export_unassigned.php?${params.toString()}`;
        }

        // Assign Modal Functions
        function openAssignModal(device, users) {
            // Populate device info
            document.getElementById('deviceId').value = device.id;
            document.getElementById('assetTag').value = device.asset_tag;

            const deviceInfo = `${device.brand_name} ${device.model || ''} | ${device.asset_tag}`;
            document.getElementById('deviceInfo').textContent = deviceInfo;

            // Set current condition as default
            if (device.condition) {
                document.getElementById('newCondition').value = device.condition;
            }

            // Set current status as default
            if (device.status) {
                document.getElementById('newStatus').value = device.status;
            }

            // Open modal
            document.getElementById('assignModal').classList.remove('hidden');

            // Initialize Select2 for user dropdown
            $('#userId').select2({
                placeholder: "Select a user...",
                dropdownParent: $('#assignModal'),
                data: users.map(user => ({
                    id: user.id,
                    text: `${user.firstname} ${user.lastname} (${user.email})`
                }))
            });
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.add('hidden');
            document.getElementById('assignForm').reset();
            $('#userId').val(null).trigger('change');
        }

        // View Modal Functions
        function viewDeviceDetails(device) {
            // Set title
            document.getElementById('deviceTitle').textContent = `${device.brand_name} ${device.model || ''}`;

            // Build details HTML
            const detailsHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-2">Device Information</h3>
                        <div class="space-y-2">
                            <p><span class="font-medium">Asset Tag:</span> <span class="font-mono bg-blue-50 px-2 py-1 rounded">${escapeHtml(device.asset_tag)}</span></p>
                            <p><span class="font-medium">Device Type:</span> ${escapeHtml(device.device_type || 'N/A')}</p>
                            <p><span class="font-medium">Category:</span> ${escapeHtml(device.category_name || 'N/A')}</p>
                            <p><span class="font-medium">Serial Number:</span> <span class="font-mono">${escapeHtml(device.serial_number || 'N/A')}</span></p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-2">Device Status</h3>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">Condition:</span>
                                <span class="condition-badge ${escapeHtml(device.condition_color || 'bg-gray-100 text-gray-700 border-gray-200')}">${escapeHtml(device.condition_label || device.condition || 'N/A')}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">Status:</span>
                                <span class="status-badge ${escapeHtml(device.status_color || 'bg-gray-100 text-gray-700 border-gray-200')}">${escapeHtml(device.status_label || device.status || 'N/A')}</span>
                            </div>
                            <p><span class="font-medium">Created:</span> ${escapeHtml(device.created_at ? new Date(device.created_at).toLocaleDateString() : 'N/A')}</p>
                            <p><span class="font-medium">Last Updated:</span> ${escapeHtml(device.updated_at ? new Date(device.updated_at).toLocaleDateString() : 'N/A')}</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 md:col-span-2">
                        <h3 class="font-semibold text-gray-800 mb-2">Specifications</h3>
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <p class="text-gray-700 whitespace-pre-line">${escapeHtml(device.specifications || 'No specifications provided.')}</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-2">Location Information</h3>
                        <div class="space-y-2">
                            <p><span class="font-medium">Brand:</span> ${escapeHtml(device.brand_name || 'N/A')}</p>
                            <p><span class="font-medium">Model:</span> ${escapeHtml(device.model || 'N/A')}</p>
                            <p><span class="font-medium">Department:</span> ${escapeHtml(device.department || 'N/A')}</p>
                            <p><span class="font-medium">Location:</span> ${escapeHtml(device.location || 'N/A')}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-2">Remarks</h3>
                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                        <p class="text-gray-700 whitespace-pre-line">${escapeHtml(device.remarks || 'No remarks available.')}</p>
                    </div>
                </div>
                
                <div class="mt-6 flex gap-3">
                    <button onclick="openAssignModal(${JSON.stringify(device)}, ${JSON.stringify(<?= json_encode($usersArr) ?>)})" 
                            class="flex-1 px-4 py-3 bg-blue-500 text-white rounded-xl font-semibold hover:bg-blue-600 transition-all">
                        <i class="fas fa-user-plus mr-2"></i> Assign This Device
                    </button>
                </div>
            `;

            document.getElementById('deviceDetails').innerHTML = detailsHTML;
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // Utility function for escaping HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Handle form submission with AJAX
        document.getElementById('assignForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('process_assign.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Device assigned successfully!');
                        closeAssignModal();
                        // Reload page to reflect changes
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        });
    </script>

</body>

</html>