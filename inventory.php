<?php
session_start();
require_once "./config/database.php";

/* ================== ERROR REPORTING ================== */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ================== DB ================== */
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("Database connection failed");

/* ================== MODES ================== */
$editMode = isset($_GET['edit']) && is_numeric($_GET['edit']);

/* ================== AUTO ASSET TAG ================== */
$year = date("Y");
$q = $conn->query("
    SELECT asset_tag 
    FROM inventory_items 
    WHERE asset_tag LIKE 'AST-$year-%' 
    ORDER BY id DESC 
    LIMIT 1
");
$next = 1;
if ($q && $q->num_rows) {
    $last = $q->fetch_assoc()['asset_tag'];
    $next = (int) substr($last, -4) + 1;
}
$asset_tag_preview = "AST-$year-" . str_pad($next, 4, "0", STR_PAD_LEFT);

/* ================== ALLOWED STATUSES ================== */
$allowedStatuses = ['active', 'in_storage', 'in_use', 'repairing', 'faulty', 'retired'];
$statusLabels = [
    'active' => 'Active',
    'retired' => 'Retired',
    'in_storage' => 'In Storage',
    'repairing' => 'Repairing',
    'in_use' => 'In Use',
    'faulty' => 'Faulty'
];

/* ================== DROPDOWNS ================== */
$categoriesArr = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$brandsArr = $conn->query("SELECT id, brand_name FROM brands ORDER BY brand_name")->fetch_all(MYSQLI_ASSOC);
$departmentsArr = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);
$locationsArr = $conn->query("SELECT id, location_name FROM locations ORDER BY location_name")->fetch_all(MYSQLI_ASSOC);

/* ================== FETCH DISTINCT STATUSES ================== */
$statuses = [];
$statusQuery = $conn->query("SELECT DISTINCT status FROM inventory_items ORDER BY status ASC");
if ($statusQuery && $statusQuery->num_rows > 0) {
    while ($row = $statusQuery->fetch_assoc()) {
        if (in_array($row['status'], $allowedStatuses)) {
            $statuses[] = $row['status'];
        }
    }
}
// If table empty, fallback to allowed statuses
if (empty($statuses)) $statuses = $allowedStatuses;

/* ================== LIST INVENTORY WITH SEARCH, FILTER, SORT & PAGINATION ================== */
$where = [];
$params = [];
$paramTypes = '';

if (!empty($_GET['search'])) {
    $where[] = "(i.asset_tag LIKE ? 
                OR i.device_type LIKE ? 
                OR b.brand_name LIKE ? 
                OR i.model LIKE ? 
                OR i.assigned_user LIKE ? 
                OR d.department_name LIKE ? 
                OR l.location_name LIKE ?)";
    
    $searchTerm = '%' . $_GET['search'] . '%';
    
    // Add 7 times for the 7 placeholders
    $params = array_merge($params, [
        $searchTerm, 
        $searchTerm, 
        $searchTerm, 
        $searchTerm, 
        $searchTerm, 
        $searchTerm, 
        $searchTerm
    ]);

    $paramTypes .= str_repeat('s', 7);
}


if (!empty($_GET['status'])) {
    $where[] = "i.status=?";
    $params[] = $_GET['status'];
    $paramTypes .= 's';
}

if (!empty($_GET['category'])) {
    $where[] = "i.category_id=?";
    $params[] = (int)$_GET['category'];
    $paramTypes .= 'i';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderBy = 'i.id';
$orderDir = (($_GET['sort'] ?? '') === 'asc') ? 'ASC' : 'DESC';

/* ================== PAGINATION ================== */
$perPage = 10; // items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// 1️ Get total records for pagination
$countQuery = $conn->prepare("
    SELECT COUNT(*) as total
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id=c.id
    LEFT JOIN brands b ON i.brand_id=b.id
    $whereSql
");
if ($params) $countQuery->bind_param($paramTypes, ...$params);
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);

// Total records for pagination
$countSql = "SELECT COUNT(*) as total FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    $whereSql";

// Pagination
$limit = 10; // number of items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1); // make sure page is at least 1
$offset = ($page - 1) * $limit;


// Pagination
$limit = 10; // number of items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1); // ensure page is at least 1
$offset = ($page - 1) * $limit;


// 2️ Fetch paginated inventory
$listQuery = $conn->prepare("
    SELECT i.*, c.category_name, b.brand_name, d.department_name, l.location_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    $whereSql
    ORDER BY $orderBy $orderDir
    LIMIT $limit OFFSET $offset
");
if ($params) {
    $listQuery->bind_param($paramTypes, ...$params);
}
$listQuery->execute();
$list = $listQuery->get_result();



/* ================== ACTIVE FILTER TAGS ================== */
$activeFilters = [];
if (!empty($_GET['search'])) $activeFilters[] = ['label'=>'Search: ' . htmlspecialchars($_GET['search']), 'param'=>'search'];
if (!empty($_GET['status'])) $activeFilters[] = ['label'=>'Status: ' . htmlspecialchars($statusLabels[$_GET['status']] ?? $_GET['status']), 'param'=>'status'];
if (!empty($_GET['category'])) {
    $catName = '';
    foreach ($categoriesArr as $c) if ($c['id'] == $_GET['category']) $catName = $c['category_name'];
    $activeFilters[] = ['label'=>'Category: ' . htmlspecialchars($catName), 'param'=>'category'];
}
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-down {
            animation: slideDown 0.3s ease-out;
        }

        .filter-tag {
            transition: all 0.2s ease;
        }

        .filter-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table-row:hover {
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
        }

        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .search-glow:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
    </style>
</head>

<body class="bg-gray-100">

    <div class="flex">
        <?php include "sidebar.php"; ?>
        <main id="mainContent" class="w-full p-6">
            <!-- ================= HEADER ================= -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Device Inventory</h1>
                    <p class="text-gray-500">Manage all inventory items</p>
                </div>
                <button onclick="openModal('addModal')"
                    class="bg-blue-600 text-white px-4 py-2 text-sm rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus text-xs mr-1"></i> Add Item
                </button>
            </div>
            <!-- Filters and Search -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-4 items-start md:items-center">
        <!-- Search -->
        <div class="flex-1">
            <div class="relative">
                <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input id="searchInput" onkeyup="searchTable()" type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                    placeholder="Search by asset, type, brand, model, or user..."
                    class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            </div>
        </div>

        <!-- Sort by Asset -->
        <div class="w-full md:w-48">
            <select name="sort" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                <option value="">Sort by Asset</option>
                <option value="asc" <?= ($_GET['sort'] ?? '') === 'asc' ? 'selected' : '' ?>>A → Z</option>
                <option value="desc" <?= ($_GET['sort'] ?? '') === 'desc' ? 'selected' : '' ?>>Z → A</option>
            </select>
        </div>

        <!-- Status Filter -->
        <div class="w-full md:w-48">
        <select name="status" id="statusFilter" class="w-full px-4 py-3 border rounded-xl">
    <option value="">All Status</option>
    <?php foreach ($statuses as $status): ?>
        <option value="<?= htmlspecialchars($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>>
            <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?>
        </option>
    <?php endforeach; ?>
</select>

        </div>

        <!-- Buttons -->
        <div class="flex gap-2">
            <button type="submit"
                class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors inline-flex items-center">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
            <a href="<?= $_SERVER['PHP_SELF'] ?>"
                class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors inline-flex items-center">
                <i class="fas fa-redo mr-2"></i>Reset
            </a>
        </div>
    </form>
</div>


            <!-- ================= TABLE ================= -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">

                    <table id="inventoryTable" class="w-full">
                        <!-- ================= TABLE HEADER ================= -->
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
                            <tr class="text-gray-700">
                                <th class="p-4 text-left text-sm font-semibold uppercase">Asset</th>
                                <th class="p-4 text-left text-sm font-semibold uppercase">Type</th>
                                <th class="p-4 text-left text-sm font-semibold uppercase">Brand</th>
                                <th class="p-4 text-left text-sm font-semibold uppercase">Model</th>
                                <th class="p-4 text-left text-sm font-semibold uppercase">User</th>
                                <th class="p-4 text-left text-sm font-semibold uppercase">Location</th>
                                <th class="p-4 text-left text-sm font-semibold uppercase">Status</th>
                                <th class="p-4 text-left text-sm font-semibold uppercase">Category</th>
                                <th class="p-4 text-center text-sm font-semibold uppercase">Actions</th>
                            </tr>
                        </thead>

                        <!-- ================= TABLE BODY ================= -->
                        <tbody id="inventoryTableBody" class="divide-y divide-gray-100">

                            <?php
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
                                'in_storage' => 'In Storage',
                                'repairing' => 'Repairing',
                                'faulty' => 'Faulty',
                                'retired' => 'Retired'
                            ];
                            ?>

                            <?php while ($row = $list->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">

                                    <!-- ASSET TAG -->
                                    <td class="p-4" data-key="">
                                        <span class="font-bold text-blue-600 text-sm">
                                            <?= htmlspecialchars($row['asset_tag']) ?>
                                        </span>
                                    </td>

                                    <!-- TYPE -->
                                    <td class="p-4 text-gray-700 text-sm" data-key="device_type">
                                        <?= htmlspecialchars($row['device_type']) ?>
                                    </td>

                                    <!-- BRAND -->
                                    <td class="p-4 text-gray-700 text-sm" data-key="brand_name">
                                        <?= htmlspecialchars($row['brand_name'] ?? 'N/A') ?>
                                    </td>

                                    <!-- MODEL -->
                                    <td class="p-4 text-gray-700 text-sm" data-key="model">
                                        <?= htmlspecialchars($row['model']) ?>
                                    </td>

                                    <!-- USER -->
                                    <td class="p-4" data-key="assigned_user">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-purple-700
                                flex items-center justify-center text-white text-xs font-bold">
                                                <?= strtoupper(substr($row['assigned_user'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <span class="text-gray-700 text-sm">
                                                <?= htmlspecialchars($row['assigned_user'] ?? 'Unassigned') ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- LOCATION -->
                                    <td class="p-4 text-gray-600 text-sm" data-key="location_name">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-1 text-xs"></i>
                                        <?= htmlspecialchars($row['location_name'] ?? 'N/A') ?>
                                    </td>

                                    <!-- STATUS -->
                                    <td class="p-4">
                                        <?php
                                        $statusClass = $statusColors[$row['status']] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                        ?>
                                        <span
                                            class="px-3 py-1 text-xs font-semibold rounded-full border <?= $statusClass ?>">
                                            <?= htmlspecialchars($statusLabels[$row['status']] ?? ucfirst($row['status'])) ?>
                                        </span>
                                    </td>

                                    <!-- CATEGORY -->
                                    <td class="p-4 text-gray-600 text-sm" data-key="category_name">
                                        <?= htmlspecialchars($row['category_name'] ?? 'N/A') ?>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td class="p-4">
                                        <div class="flex justify-center gap-2">

                                            <!-- VIEW -->
                                            <button
                                                onclick='openViewModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'
                                                class="w-9 h-9 flex items-center justify-center text-green-600 hover:bg-green-50 rounded-lg"
                                                title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- EDIT -->
                                            <button onclick="openModal('editModal<?= $row['id'] ?>')"
                                                class="w-9 h-9 flex items-center justify-center text-blue-600 hover:bg-blue-50 rounded-lg"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- RETIRE -->
                                            <button onclick="openRetireModal(<?= (int) $row['id'] ?>)"
                                                class="w-9 h-9 flex items-center justify-center text-orange-600 hover:bg-orange-50 rounded-lg"
                                                title="Retire">
                                                <i class="fas fa-archive"></i>
                                            </button>

                                            <!-- DELETE -->
                                            <button onclick="openDeleteModal(<?= (int) $row['id'] ?>)"
                                                class="w-9 h-9 flex items-center justify-center text-red-600 hover:bg-red-50 rounded-lg"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- ================= EDIT MODAL ================= -->
                                <div id="editModal<?= $row['id'] ?>"
                                    class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
                                    onclick="closeModalOnBackdrop(event, 'editModal<?= $row['id'] ?>')">
                                    <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
                                        onclick="event.stopPropagation()">
                                        <!-- Modal Header -->
                                        <div
                                            class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-edit text-white"></i>
                                                </div>
                                                <div>
                                                    <h2 class="text-xl font-bold text-white">Edit Inventory Item</h2>
                                                    <p class="text-blue-100 text-sm">
                                                        <?= htmlspecialchars($row['asset_tag'] ?? '') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <button type="button" onclick="closeModal('editModal<?= $row['id'] ?>')"
                                                class="text-white/80 hover:text-white transition">
                                                <i class="fas fa-times text-xl"></i>
                                            </button>
                                        </div>
                                        <!-- Modal Body -->
                                        <div class="p-6 overflow-y-auto" style="max-height: calc(95vh - 140px);">
                                            <form method="POST" action="inventory.php?edit=<?= $row['id'] ?>"
                                                id="editForm<?= $row['id'] ?>">
                                                <!-- Basic Information -->
                                                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                                    <h3
                                                        class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                                        <i class="fas fa-info-circle text-blue-600"></i>
                                                        Basic Information
                                                    </h3>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div class="md:col-span-2">
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Asset
                                                                Tag</label>
                                                            <input readonly name="asset_tag"
                                                                value="<?= htmlspecialchars($row['asset_tag'] ?? '') ?>"
                                                                class="w-full border border-gray-300 p-3 rounded-lg bg-gray-100 text-gray-600">
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Device
                                                                Type <span class="text-red-500">*</span></label>
                                                            <input name="device_type" required
                                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                                value="<?= htmlspecialchars($row['device_type'] ?? '') ?>">
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Brand</label>
                                                            <select name="brand_id" required
                                                                class="w-full border border-gray-300 p-3 rounded-lg">
                                                                <option value="">Select Brand</option>
                                                                <?php foreach ($brandsArr as $b): ?>
                                                                    <option value="<?= $b['id'] ?>"
                                                                        <?= ($row['brand_id'] == $b['id']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($b['brand_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                                                            <input name="model"
                                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                                value="<?= htmlspecialchars($row['model'] ?? '') ?>">
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Serial
                                                                Number</label>
                                                            <input name="serial_number"
                                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                                value="<?= htmlspecialchars($row['serial_number'] ?? '') ?>">
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Specifications</label>
                                                            <input name="specifications"
                                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                                value="<?= htmlspecialchars($row['specifications'] ?? '') ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Assignment Details -->
                                                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                                    <h3
                                                        class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                                        <i class="fas fa-user-tag text-blue-600"></i>
                                                        Assignment Details
                                                    </h3>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                                            <select name="department_id" required
                                                                class="w-full border border-gray-300 p-3 rounded-lg">
                                                                <option value="">Select Department</option>
                                                                <?php foreach ($departmentsArr as $d): ?>
                                                                    <option value="<?= $d['id'] ?>"
                                                                        <?= ($row['department_id'] == $d['id']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($d['department_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Assigned
                                                                User</label>
                                                            <input name="assigned_user"
                                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                                value="<?= htmlspecialchars($row['assigned_user'] ?? '') ?>">
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                                                            <select name="location_id" required
                                                                class="w-full border border-gray-300 p-3 rounded-lg">
                                                                <option value="">Select Location</option>
                                                                <?php foreach ($locationsArr as $l): ?>
                                                                    <option value="<?= $l['id'] ?>"
                                                                        <?= ($row['location_id'] == $l['id']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($l['location_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Status & Category -->
                                                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                                    <h3
                                                        class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                                        <i class="fas fa-cog text-blue-600"></i>
                                                        Status & Category
                                                    </h3>
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Condition</label>
                                                            <select name="condition"
                                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                                <option value="Excellent" <?= $row['condition'] == 'Excellent' ? 'selected' : '' ?>>
                                                                    Excellent</option>
                                                                <option value="Good" <?= $row['condition'] == 'Good' ? 'selected' : '' ?>>Good
                                                                </option>
                                                                <option value="Fair" <?= $row['condition'] == 'Fair' ? 'selected' : '' ?>>Fair
                                                                </option>
                                                                <option value="Poor" <?= $row['condition'] == 'Poor' ? 'selected' : '' ?>>Poor
                                                                </option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                                            <select name="status" id="status"
                                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                                <?php
                                                                $allowedStatuses = [
                                                                    'active' => 'Active',
                                                                    'in_storage' => 'In Storage',
                                                                    'in_use' => 'In Use',
                                                                    'repairing' => 'Repairing',
                                                                    'faulty' => 'Faulty',
                                                                    'retired' => 'Retired'
                                                                ];
                                                                foreach ($allowedStatuses as $value => $label): ?>
                                                                    <option value="<?= $value ?>" <?= ($item['status'] ?? 'active') == $value ? 'selected' : '' ?>>
                                                                        <?= $label ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-2">Category
                                                                <span class="text-red-500">*</span></label>
                                                            <select name="category_id" required
                                                                class="w-full border border-gray-300 p-3 rounded-lg">
                                                                <option value="">Select Category</option>
                                                                <?php foreach ($categoriesArr as $c): ?>
                                                                    <option value="<?= $c['id'] ?>"
                                                                        <?= ($row['category_id'] == $c['id']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($c['category_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <!-- Additional Notes -->
                                                    <div class="bg-gray-50 rounded-xl p-4">
                                                        <h3
                                                            class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                                            <i class="fas fa-sticky-note text-blue-600"></i>
                                                            Additional Notes
                                                        </h3>
                                                        <textarea name="remarks" rows="4"
                                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                            placeholder="Add any additional notes or remarks..."><?= htmlspecialchars($row['remarks'] ?? '') ?></textarea>
                                                    </div>
                                            </form>
                                        </div>
                                        <!-- Modal Footer -->
                                        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                                            <button type="button" onclick="closeModal('editModal<?= $row['id'] ?>')"
                                                class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 transition font-medium">
                                                <i class="fas fa-times mr-2"></i>Cancel
                                            </button>
                                            <button type="submit" form="editForm<?= $row['id'] ?>" name="update"
                                                class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                                                <i class="fas fa-save mr-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>

                        </tbody>
                    </table>

                </div>
            </div>


            <!-- ================= ADD MODAL ================= -->
            <div id="addModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
                onclick="closeModalOnBackdrop(event, 'addModal')">
                <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
                    onclick="event.stopPropagation()">
                    <!-- Modal Header -->
                    <div
                        class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-white">Add New Inventory Item</h2>
                                <p class="text-green-100 text-sm">Fill in the details below</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeModal('addModal')"
                            class="text-white/80 hover:text-white transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 overflow-y-auto" style="max-height: calc(95vh - 140px);">
                        <form method="POST" id="addForm" autocomplete="off">
                            <!-- Basic Information -->
                            <div class="bg-gray-50 rounded-xl p-5 mb-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-info-circle text-green-600"></i>
                                    Basic Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Asset Tag</label>
                                        <input readonly name="asset_tag"
                                            value="<?= htmlspecialchars($asset_tag_preview) ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg bg-gray-100 text-gray-600">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Device Type <span
                                                class="text-red-500">*</span></label>
                                        <input name="device_type" value="<?= $item['device_type'] ?? '' ?>" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., Laptop, Desktop, Monitor">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Brand <span
                                                class="text-red-500">*</span></label>
                                        <select name="brand_id" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Brand</option>
                                            <?php foreach ($brandsArr as $b): ?>
                                                <option value="<?= $b['id'] ?>" <?= isset($item['brand_id']) && $item['brand_id'] == $b['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($b['brand_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                                        <input name="model" value="<?= $item['model'] ?? '' ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., XPS 15, ThinkPad X1">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Serial
                                            Number</label>
                                        <input name="serial_number" value="<?= $item['serial_number'] ?? '' ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., SN123456789">
                                    </div>

                                    <div class="md:col-span-2">
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">Specifications</label>
                                        <input name="specifications" value="<?= $item['specifications'] ?? '' ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., Intel i7, 16GB RAM, 512GB SSD">
                                    </div>
                                </div>
                            </div>

                            <!-- Assignment Details -->
                            <div class="bg-gray-50 rounded-xl p-5 mb-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-user-tag text-green-600"></i>
                                    Assignment Details
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Department <span
                                                class="text-red-500">*</span></label>
                                        <select name="department_id" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departmentsArr as $d): ?>
                                                <option value="<?= $d['id'] ?>" <?= isset($item['department_id']) && $item['department_id'] == $d['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($d['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Assigned
                                            User</label>
                                        <input name="assigned_user" value="<?= $item['assigned_user'] ?? '' ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., John Doe">
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Location <span
                                                class="text-red-500">*</span></label>
                                        <select name="location_id" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Location</option>
                                            <?php foreach ($locationsArr as $l): ?>
                                                <option value="<?= $l['id'] ?>" <?= isset($item['location_id']) && $item['location_id'] == $l['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($l['location_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Status & Category -->
                            <div class="bg-gray-50 rounded-xl p-5 mb-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-cog text-green-600"></i>
                                    Status & Category
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Condition <span
                                                class="text-red-500">*</span></label>
                                        <select name="condition" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="Excellent" <?= (isset($item['condition']) && $item['condition'] == 'Excellent') ? 'selected' : '' ?>>Excellent</option>
                                            <option value="Good" <?= (isset($item['condition']) && $item['condition'] == 'Good') ? 'selected' : '' ?>>Good</option>
                                            <option value="Fair" <?= (isset($item['condition']) && $item['condition'] == 'Fair') ? 'selected' : '' ?>>Fair</option>
                                            <option value="Poor" <?= (isset($item['condition']) && $item['condition'] == 'Poor') ? 'selected' : '' ?>>Poor</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Status <span
                                                class="text-red-500">*</span></label>
                                        <select name="status" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="active" <?= (isset($item['status']) && $item['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                                            <option value="in_storage" <?= (isset($item['status']) && $item['status'] == 'in_storage') ? 'selected' : '' ?>>In Storage</option>
                                            <option value="in_use" <?= (isset($item['status']) && $item['status'] == 'in_use') ? 'selected' : '' ?>>In Use</option>
                                            <option value="repairing" <?= (isset($item['status']) && $item['status'] == 'repairing') ? 'selected' : '' ?>>Repairing</option>
                                            <option value="faulty" <?= (isset($item['status']) && $item['status'] == 'faulty') ? 'selected' : '' ?>>Faulty</option>
                                            <option value="retired" <?= (isset($item['status']) && $item['status'] == 'retired') ? 'selected' : '' ?>>Retired</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Category <span
                                                class="text-red-500">*</span></label>
                                        <select name="category_id" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categoriesArr as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= isset($item['category_id']) && $item['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['category_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Notes -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-sticky-note text-green-600"></i>
                                    Description
                                </h3>
                                <textarea name="remarks" rows="4"
                                    class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
                                    placeholder="Add any additional notes or remarks..."><?= $item['remarks'] ?? '' ?></textarea>
                            </div>
                        </form>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                        <button type="button" onclick="closeModal('addModal')"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 transition font-medium">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" form="addForm" name="save"
                            class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                            <i class="fas fa-plus mr-2"></i>Add Item
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= VIEW MODAL ================= -->
    <div id="viewModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
        onclick="closeModalOnBackdrop(event, 'viewModal')">
        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-eye text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Inventory Details</h2>
                        <p class="text-purple-100 text-sm">View complete item information</p>
                    </div>
                </div>
                <button onclick="closeViewModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto" style="max-height: calc(95vh - 140px);">
                <!-- Basic Information -->
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl p-5 mb-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-purple-600"></i>
                        Basic Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Asset Tag</p>
                            <p class="font-semibold text-gray-800" id="view_asset_tag"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Device Type</p>
                            <p class="font-semibold text-gray-800" id="view_device_type"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Brand</p>
                            <p class="font-semibold text-gray-800" id="view_brand"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Model</p>
                            <p class="font-semibold text-gray-800" id="view_model"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Serial Number</p>
                            <p class="font-semibold text-gray-800" id="view_serial_number"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Category</p>
                            <p class="font-semibold text-gray-800" id="view_category"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3 md:col-span-2">
                            <p class="text-xs text-gray-500 mb-1">Specifications</p>
                            <p class="font-semibold text-gray-800" id="view_specifications"></p>
                        </div>
                    </div>
                </div>

                <!-- Assignment Details -->
                <div class="bg-gradient-to-br from-green-50 to-teal-50 rounded-xl p-5 mb-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-user-tag text-green-600"></i>
                        Assignment Details
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Department</p>
                            <p class="font-semibold text-gray-800" id="view_department"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Assigned User</p>
                            <p class="font-semibold text-gray-800" id="view_assigned_user"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3 md:col-span-2">
                            <p class="text-xs text-gray-500 mb-1">Location</p>
                            <p class="font-semibold text-gray-800" id="view_location"></p>
                        </div>
                    </div>
                </div>

                <!-- Status & Condition -->
                <div class="bg-gradient-to-br from-orange-50 to-yellow-50 rounded-xl p-5 mb-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-cog text-orange-600"></i>
                        Status & Condition
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Condition</p>
                            <p class="font-semibold text-gray-800" id="view_condition"></p>
                        </div>
                        <div class="bg-white rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Status</p>
                            <p class="font-semibold text-gray-800" id="view_status"></p>
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div class="bg-gradient-to-br from-gray-50 to-slate-50 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fas fa-sticky-note text-gray-600"></i>
                        Additional Notes
                    </h3>
                    <div class="bg-white rounded-lg p-3">
                        <p class="text-gray-700" id="view_remarks"></p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex justify-end border-t">
                <button onclick="closeViewModal()"
                    class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                    <i class="fas fa-check mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>

<?php if ($totalPages > 1): ?>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
        <?php
        // Preserve all GET parameters except 'page'
        $queryParams = $_GET;
        ?>
        
        <?php if ($page > 1): ?>
            <?php $queryParams['page'] = $page - 1; ?>
            <a href="?<?= http_build_query($queryParams) ?>"
               class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
               <i class="fas fa-chevron-left"></i>
            </a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                <?php $queryParams['page'] = $i; ?>
                <a href="?<?= http_build_query($queryParams) ?>" 
                   class="px-4 py-2 rounded-lg transition-colors font-medium <?= $i == $page
                        ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow-lg'
                        : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                    <?= $i ?>
                </a>
            <?php elseif (abs($i - $page) == 3): ?>
                <span class="px-2 text-gray-400">...</span>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <?php $queryParams['page'] = $page + 1; ?>
            <a href="?<?= http_build_query($queryParams) ?>"
               class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
               <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <p class="text-center text-sm text-gray-500 mt-4">
        Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total inventory items)
    </p>
<?php endif; ?>

        </main>
    </div>


    <!-- ================= RETIRE MODAL ================= -->
    <div id="retireModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">

        <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
            <!-- Header -->
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    Retire Device
                </h3>
                <button onclick="closeRetireModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 text-gray-700">
                <p class="mb-2 font-medium">
                    Are you sure you want to retire this device?
                </p>
                <p class="text-sm text-gray-500">
                    The device will no longer be assignable but will remain in records.
                </p>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t flex justify-end gap-3">
                <button onclick="closeRetireModal()"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                    Cancel
                </button>

                <form method="POST" class="inline">
                    <input type="hidden" name="retire_id" id="retireId">
                    <button type="submit" name="retire"
                        class="px-4 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700">
                        Yes, Retire
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= DELETE MODAL ================= -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">

        <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
            <!-- Header -->
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">
                    Confirm Delete
                </h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 text-gray-700">
                <p class="mb-2 font-medium">Are you sure you want to delete this item?</p>
                <p class="text-sm text-gray-500">
                    This action <span class="text-red-600 font-semibold">cannot be undone</span>.
                </p>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t flex justify-end gap-3">
                <button onclick="closeDeleteModal()"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                    Cancel
                </button>

                <!-- This triggers your existing PHP delete -->
                <a id="confirmDeleteBtn" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                    Yes, Delete
                </a>
            </div>
        </div>
    </div>
    </div>

    <script>
        // ==================== MODAL FUNCTIONS ====================
        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) {
                console.error('Modal not found:', id);
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex'); // ensure flex layout
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function closeModalOnBackdrop(event, id) {
            if (event.target === event.currentTarget) {
                closeModal(id);
            }
        }

        // ==================== VIEW MODAL ====================
    function openViewModal(item) {
    document.getElementById('view_asset_tag').textContent = item.asset_tag || '';
    document.getElementById('view_device_type').textContent = item.device_type || '';
    document.getElementById('view_brand').textContent = item.brand_name || '';
    document.getElementById('view_model').textContent = item.model || '';
    document.getElementById('view_serial_number').textContent = item.serial_number || '';
    document.getElementById('view_category').textContent = item.category_name || '';
    document.getElementById('view_specifications').textContent = item.specifications || '';
    document.getElementById('view_department').textContent = item.department_name || '';
    document.getElementById('view_assigned_user').textContent = item.assigned_user || '';
    document.getElementById('view_location').textContent = item.location_name || '';
    document.getElementById('view_condition').textContent = item.condition || '';
    document.getElementById('view_status').textContent = item.status || '';
    document.getElementById('view_remarks').textContent = item.remarks || '';

    document.getElementById('viewModal').classList.remove('hidden');
}


    function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}


        // ==================== RETIRE MODAL ====================
        function openRetireModal(id) {
            document.getElementById('retireId').value = id;
            openModal('retireModal');
        }

        function closeRetireModal() {
            closeModal('retireModal');
        }

        // ==================== DELETE MODAL ====================
        function openDeleteModal(id) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.href = `inventory.php?delete=${id}`;
            openModal('deleteModal');
        }

        function closeDeleteModal() {
            closeModal('deleteModal');
        }

        // ==================== SEARCH FUNCTION ====================
    function searchTable() {
    const searchTerm = document.getElementById("searchInput").value.toLowerCase().trim();
    const rows = document.querySelectorAll("#inventoryTable tbody tr");

    rows.forEach(row => {
        const assetTag     = row.querySelector('[data-key="asset_tag"]')?.textContent.toLowerCase() || '';
        const deviceType   = row.querySelector('[data-key="device_type"]')?.textContent.toLowerCase() || '';
        const brandName    = row.querySelector('[data-key="brand_name"]')?.textContent.toLowerCase() || '';
        const model        = row.querySelector('[data-key="model"]')?.textContent.toLowerCase() || '';
        const assignedUser = row.querySelector('[data-key="assigned_user"]')?.textContent.toLowerCase() || '';

        const searchableText = `${assetTag} ${deviceType} ${brandName} ${model} ${assignedUser}`;

        // Show row if it matches, hide otherwise
        row.style.display = searchableText.includes(searchTerm) ? "" : "none";
    });

    // Optional: if you have filter tags UI
    if (typeof updateActiveFilters === "function") {
        updateActiveFilters();
    }
}


        // ==================== SORT FUNCTION ====================
        let currentSort = { column: null, order: null };
        function sortTable(columnIndex, order) {
            const table = document.getElementById("inventoryTable");
            const tbody = table.tBodies[0];
            const rows = Array.from(tbody.rows);

            currentSort = { column: columnIndex, order: order };

            rows.sort((a, b) => {
                const cellA = a.cells[columnIndex].textContent.trim().toLowerCase();
                const cellB = b.cells[columnIndex].textContent.trim().toLowerCase();
                return order === "asc" ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        // ==================== FILTER FUNCTIONS ====================
        let currentFilters = { status: '', category: '', brand: '' };

        function filterByStatus() {
            const value = document.getElementById("statusFilter").value.toLowerCase();
            currentFilters.status = value;
            applyFilters();
        }

        function filterByCategory() {
            const value = document.getElementById("categoryFilter").value;
            currentFilters.category = value;
            applyFilters();
        }

        function filterByBrand() {
            const value = document.getElementById("brandFilter").value;
            currentFilters.brand = value;
            applyFilters();
        }

        function applyFilters() {
            const rows = document.querySelectorAll("#inventoryTable tbody tr");

            rows.forEach(row => {
                let show = true;

                // Status filter
                if (currentFilters.status) {
                    const statusText = row.cells[6].querySelector('span')?.textContent.trim().toLowerCase() || '';
                    if (!statusText.includes(currentFilters.status)) show = false;
                }

                // Category filter
                if (currentFilters.category) {
                    const categoryText = row.cells[7]?.textContent.trim() || '';
                    const selectedCategoryName = document.querySelector(`#categoryFilter option[value="${currentFilters.category}"]`)?.textContent.trim() || '';
                    if (categoryText !== selectedCategoryName) show = false;
                }

                // Brand filter
                if (currentFilters.brand) {
                    const brandText = row.querySelector('[data-key="brand_name"]')?.textContent.trim() || '';
                    const selectedBrandName = document.querySelector(`#brandFilter option[value="${currentFilters.brand}"]`)?.textContent.trim() || '';
                    if (brandText !== selectedBrandName) show = false;
                }

                row.style.display = show ? '' : 'none';
            });

            updateActiveFilters();
        }

        // ==================== CLEAR ALL FILTERS ====================
        function clearAllFilters() {
            document.getElementById("searchInput").value = "";
            document.getElementById("statusFilter").value = "";
            document.getElementById("categoryFilter").value = "";
            document.getElementById("brandFilter").value = "";

            currentFilters = { status: '', category: '', brand: '' };

            const rows = document.querySelectorAll("#inventoryTable tbody tr");
            rows.forEach(row => row.style.display = "");

            updateActiveFilters();
        }

        // ==================== ACTIVE FILTERS DISPLAY ====================
        function updateActiveFilters() {
            const container = document.getElementById("activeFilters");
            const tags = document.getElementById("activeFilterTags");
            tags.innerHTML = "";

            let hasFilters = false;

            // Search
            const searchVal = document.getElementById("searchInput").value.trim();
            if (searchVal) {
                hasFilters = true;
                addFilterTag("Search", searchVal, () => {
                    document.getElementById("searchInput").value = "";
                    searchTable();
                });
            }

            // Status
            if (currentFilters.status) {
                hasFilters = true;
                const statusText = document.querySelector(`#statusFilter option[value="${currentFilters.status}"]`)?.textContent || '';
                addFilterTag("Status", statusText, () => {
                    document.getElementById("statusFilter").value = "";
                    filterByStatus();
                });
            }

            // Category
            if (currentFilters.category) {
                hasFilters = true;
                const categoryText = document.querySelector(`#categoryFilter option[value="${currentFilters.category}"]`)?.textContent || '';
                addFilterTag("Category", categoryText, () => {
                    document.getElementById("categoryFilter").value = "";
                    filterByCategory();
                });
            }

            // Brand
            if (currentFilters.brand) {
                hasFilters = true;
                const brandText = document.querySelector(`#brandFilter option[value="${currentFilters.brand}"]`)?.textContent || '';
                addFilterTag("Brand", brandText, () => {
                    document.getElementById("brandFilter").value = "";
                    filterByBrand();
                });
            }

            container.classList.toggle('hidden', !hasFilters);
        }

        function addFilterTag(label, value, removeCallback) {
            const activeFilterTags = document.getElementById("activeFilterTags");

            const tag = document.createElement("span");
            tag.className = "inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium";
            tag.innerHTML = `
        <span>${label}: ${value}</span>
        <button onclick="this.parentElement.remove(); (${removeCallback.toString()})();" 
                class="hover:text-blue-900 transition">
            <i class="fas fa-times text-xs"></i>
        </button>
    `;

            activeFilterTags.appendChild(tag);
        }
    </script>
</body>

</html>