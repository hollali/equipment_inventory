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

/* ================== AUTO ASSET TAG (DISPLAY ONLY) ================== */
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
$allowedStatuses = ['active','in_storage','in_use','repairing','faulty','retired'];

/* ================== ADD INVENTORY ================== */
if (isset($_POST['save'])) {
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
    $asset_tag = "AST-$year-" . str_pad($next, 4, "0", STR_PAD_LEFT);

    // Collect data
    $device_type = $_POST['device_type'] ?? '';
    $brand_id = !empty($_POST['brand_id']) ? $_POST['brand_id'] : null;
    $model = $_POST['model'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $assigned_user = $_POST['assigned_user'] ?? '';
    $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
    $condition = $_POST['condition'] ?? 'Good';
    $status = $_POST['status'] ?? 'active';
    $remarks = $_POST['remarks'] ?? '';
    $category_id = $_POST['category_id'] ?? null;

    // Validate status
    if (!in_array($status, $allowedStatuses)) $status = 'active';

    // Insert
    $stmt = $conn->prepare("
        INSERT INTO inventory_items
        (asset_tag, device_type, brand_id, model, serial_number, specifications,
         department_id, assigned_user, location_id, `condition`, status, remarks, category_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "ssissisissssi",
        $asset_tag,
        $device_type,
        $brand_id,
        $model,
        $serial_number,
        $specifications,
        $department_id,
        $assigned_user,
        $location_id,
        $condition,
        $status,
        $remarks,
        $category_id
    );

    $stmt->execute();
    header("Location: inventory.php?success=added");
    exit;
}

/* ================== EDIT INVENTORY ================== */
if ($editMode && isset($_POST['update'])) {
    $edit_id = $_GET['edit'];

    $device_type = $_POST['device_type'] ?? '';
    $brand_id = !empty($_POST['brand_id']) ? $_POST['brand_id'] : null;
    $model = $_POST['model'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $assigned_user = $_POST['assigned_user'] ?? '';
    $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
    $condition = $_POST['condition'] ?? 'Good';
    $status = $_POST['status'] ?? 'active';
    $remarks = $_POST['remarks'] ?? '';
    $category_id = $_POST['category_id'] ?? null;

    // Validate status
    if (!in_array($status, $allowedStatuses)) $status = 'active';

    $stmt = $conn->prepare("
        UPDATE inventory_items SET
            device_type=?, brand_id=?, model=?, serial_number=?, specifications=?,
            department_id=?, assigned_user=?, location_id=?, `condition`=?, status=?, remarks=?, category_id=?,
            updated_at = NOW()
        WHERE id=?
    ");

    $stmt->bind_param(
        "sisssisisssii",
        $device_type,
        $brand_id,
        $model,
        $serial_number,
        $specifications,
        $department_id,
        $assigned_user,
        $location_id,
        $condition,
        $status,
        $remarks,
        $category_id,
        $edit_id
    );

    $stmt->execute();
    header("Location: inventory.php?success=updated");
    exit;
}

/* ================== DELETE INVENTORY ================== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();

    header("Location: inventory.php?success=deleted");
    exit;
}

/* ================== FETCH ITEM FOR EDIT ================== */
$item = [];
if ($editMode) {
    $stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id=?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
}

/* ================== DROPDOWNS ================== */
$categoriesArr = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$brandsArr = $conn->query("SELECT id, brand_name FROM brands ORDER BY brand_name")->fetch_all(MYSQLI_ASSOC);
$departmentsArr = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);
$locationsArr = $conn->query("SELECT id, location_name FROM locations ORDER BY location_name")->fetch_all(MYSQLI_ASSOC);

/* ================== LIST INVENTORY ================== */
$list = $conn->query("
    SELECT i.*, c.category_name, b.brand_name, d.department_name, l.location_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    ORDER BY i.id DESC
");
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

            <!-- ================= SEARCH ================= -->
            <!--<div class="mb-4">
                <input id="searchInput" onkeyup="searchTable()" placeholder="Search inventory..."
                    class="w-full md:w-1/3 px-4 py-2 border rounded-lg focus:ring focus:ring-blue-200">
            </div>-->
<!-- ================= SEARCH & SORT CARD ================= -->
           <div class="bg-white rounded-xl shadow-sm p-4 mb-6 grid md:grid-cols-3 gap-4 items-end">

    <!-- Search Box -->
    <div class="relative w-full">
        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
        <input id="searchInput" onkeyup="searchTable()" placeholder="Search inventory..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition shadow-sm">
    </div>

    <!-- Sort Box -->
    <div class="relative w-full">
        <i class="fas fa-sort absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
        <select id="sortSelect" onchange="sortTable('asset_tag')" 
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none bg-white transition cursor-pointer shadow-sm">
            <option value="">Sort by Asset</option>
            <option value="asc">A → Z</option>
            <option value="desc">Z → A</option>
        </select>
        <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
    </div>

    <!-- Status Filter -->
    <?php
    // Fetch unique statuses from the database
    $statusQuery = $conn->query("SELECT DISTINCT status FROM inventory_items ORDER BY status ASC");
    $statuses = [];
    while ($row = $statusQuery->fetch_assoc()) {
        $statuses[] = $row['status'];
    }

    // Map DB values to human-readable labels
    $statusLabels = [
        'active'      => 'Active',
        'retired'     => 'Retired',
        'in_storage'  => 'In Storage',
        'repairing'   => 'Under Repair',
        'in_use'      => 'In Use',
        'faulty'      => 'Faulty'
    ];
    ?>
    <div class="relative w-full md:w-64">
        <i class="fas fa-filter absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
        <select id="statusFilter" onchange="filterStatus(this.value)"
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none bg-white transition cursor-pointer shadow-sm">
            <option value="">All Status</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>">
                    <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
    </div>
</div>

            <!-- ================= TABLE ================= -->
            <div id="inventoryTable" class="bg-white rounded-xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-gray-600">
                            <th class="p-3 text-left font-semibold">Asset</th>
                            <th class="p-3 text-left font-semibold">Type</th>
                            <th class="p-3 text-left font-semibold">Brand</th>
                            <th class="p-3 text-left font-semibold">Model</th>
                            <th class="p-3 text-left font-semibold">User</th>
                            <th class="p-3 text-left font-semibold">Location</th>
                            <th class="p-3 text-left font-semibold">Status</th>
                            <th class="p-3 text-left font-semibold">Category</th>
                            <th class="p-3 text-center font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while ($row = $list->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="p-3">
                                    <span class="font-semibold text-blue-600" data-key="asset_tag"><?= htmlspecialchars($row['asset_tag']) ?></span>
                                </td>
                                <td class="p-3 text-gray-700" data-key="device_type"><?= htmlspecialchars($row['device_type']) ?></td>
                                <td class="p-3 text-gray-700" data-key="brand_name"><?= htmlspecialchars($row['brand_name'] ?? 'N/A') ?></td>
                                <td class="p-3 text-gray-700" data-key="model"><?= htmlspecialchars($row['model']) ?></td>
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                                            <?= strtoupper(substr($row['assigned_user'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <span class="text-gray-700" data-key="assigned_user"><?= htmlspecialchars($row['assigned_user']) ?></span>
                                    </div>
                                </td>
                                <td class="p-3 text-gray-600">
                                    <i class="fas fa-map-marker-alt text-gray-400 mr-1 text-xs"></i>
                                    <?= htmlspecialchars($row['location_name'] ?? 'N/A') ?>
                                </td>
                                <td class="p-3">
                                    <?php
                              // Map DB status values to colors
                        $statusColors = [
                                    'active' => 'bg-green-100 text-green-700 border-green-200',
                                    'retired' => 'bg-red-100 text-red-700 border-red-200',
                                    'in_storage' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                    'repairing' => 'bg-gray-100 text-gray-700 border-gray-200',
                                    'in_use' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                    'faulty' => 'bg-pink-100 text-pink-700 border-pink-200'
                            ];
                            // Get the class for the current row
                            $statusClass = $statusColors[$row['status']] ?? 'bg-gray-100 text-gray-700 border-gray-200';

                            $statusLabels = [
                                'active'      => 'Active',
                                'retired'     => 'Retired',
                                'in_storage'  => 'In Storage',
                                'repairing'   => 'Repairing',
                                'in_use'      => 'In Use',
                                'faulty'      => 'Faulty'
                            ];?>
                                    <span class="px-2 py-1 text-xs rounded-full font-medium border <?= $statusClass ?>">
                                        <?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-gray-600"><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>
                                <td class="p-3">
                                    <div class="flex gap-2 justify-center">
                                        <!-- View -->
                                        <button onclick='openViewModal(<?= htmlspecialchars(
                                            json_encode([
                                                "asset_tag" => $row['asset_tag'],
                                                "device_type" => $row['device_type'],
                                                "brand" => $row['brand_name'] ?? "N/A",
                                                "model" => $row['model'],
                                                "serial_number" => $row['serial_number'],
                                                "specifications" => $row['specifications'],
                                                "department" => $row['department_name'] ?? "N/A",
                                                "assigned_user" => $row['assigned_user'],
                                                "location" => $row['location_name'] ?? "N/A",
                                                "condition" => $row['condition'],
                                                "status" => $row['status'],
                                                "category_name" => $row['category_name'] ?? "N/A",
                                                "remarks" => $row['remarks']
                                            ],
                                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                        ), ENT_QUOTES, 'UTF-8') ?>)'
                                        class="text-green-600 hover:bg-green-50 p-2 rounded-lg transition-all" title="View">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <!-- Edit -->
                                        <button onclick="openModal('editModal<?= $row['id'] ?>')"
                                            class="text-blue-600 hover:bg-blue-50 p-2 rounded-lg transition-all" title="Edit">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <!-- Delete -->
                                        <a href="inventory.php?delete=<?= $row['id'] ?>"
                                            onclick="return confirm('Delete this item? This action cannot be undone.')"
                                            class="text-red-600 hover:bg-red-50 p-2 rounded-lg transition-all" title="Delete">
                                            <i class="fa fa-trash"></i>
                                        </a>
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
                                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
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
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Asset
                                                            Tag</label>
                                                        <input readonly name="asset_tag"
                                                            value="<?= htmlspecialchars($row['asset_tag'] ?? '') ?>"
                                                            class="w-full border border-gray-300 p-3 rounded-lg bg-gray-100 text-gray-600">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Device
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
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Serial
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
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Assigned
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
                                                            <option value="Excellent" <?= $row['condition'] == 'Excellent' ? 'selected' : '' ?>>Excellent</option>
                                                            <option value="Good" <?= $row['condition'] == 'Good' ? 'selected' : '' ?>>Good</option>
                                                            <option value="Fair" <?= $row['condition'] == 'Fair' ? 'selected' : '' ?>>Fair</option>
                                                            <option value="Poor" <?= $row['condition'] == 'Poor' ? 'selected' : '' ?>>Poor</option>
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
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Category
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
        </main>
    </div>

    <!-- ================= ADD MODAL ================= -->
<div id="addModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
    onclick="closeModalOnBackdrop(event, 'addModal')">
    <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
        onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex items-center justify-between">
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
                            <input readonly name="asset_tag" value="<?= htmlspecialchars($asset_tag_preview) ?>"
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                            <input name="serial_number" value="<?= $item['serial_number'] ?? '' ?>"
                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="e.g., SN123456789">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Specifications</label>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assigned User</label>
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

    <script>
        function searchTable() {
            const q = document.getElementById("searchInput").value.toLowerCase();
            document.querySelectorAll("tbody tr").forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? "" : "none";
            });
        }

        function sortTable(colIndex) {
    const table = document.getElementById("inventoryTable");
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    const select = event.target;
    const direction = select.value;

    if (!direction) return;

    rows.sort((a, b) => {
        const aText = a.cells[colIndex].textContent.trim().toLowerCase();
        const bText = b.cells[colIndex].textContent.trim().toLowerCase();

        return direction === "asc"
            ? aText.localeCompare(bText)
            : bText.localeCompare(aText);
    });

    rows.forEach(row => tbody.appendChild(row));
}

function filterStatus(status) {
    const rows = document.querySelectorAll("#inventoryTable tbody tr");

    rows.forEach(row => {
        const cellText = row.cells[6].textContent.trim();

        if (status === "" || cellText === status) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function closeModalOnBackdrop(event, id) {
            if (event.target === event.currentTarget) {
                closeModal(id);
            }
        }

        function openViewModal(data) {
            document.getElementById('view_asset_tag').innerText = data.asset_tag || '';
            document.getElementById('view_device_type').innerText = data.device_type || '';
            document.getElementById('view_brand').innerText = data.brand || '';
            document.getElementById('view_model').innerText = data.model || '';
            document.getElementById('view_serial_number').innerText = data.serial_number || '';
            document.getElementById('view_specifications').innerText = data.specifications || 'N/A';
            document.getElementById('view_department').innerText = data.department || '';
            document.getElementById('view_assigned_user').innerText = data.assigned_user || '';
            document.getElementById('view_location').innerText = data.location || '';
            document.getElementById('view_condition').innerText = data.condition || 'N/A';
            document.getElementById('view_status').innerText = data.status || 'N/A';
            document.getElementById('view_category').innerText = data.category_name || 'N/A';
            document.getElementById('view_remarks').innerText = data.remarks || 'N/A';
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }
        // reattach sorted rows
        let currentSort = { column: null, order: null };
        let currentFilter = '';

        function sortTable(columnIndex, order) {
            const table = document.getElementById("inventoryTable");
            const tbody = table.tBodies[0];
            const rows = Array.from(tbody.rows);

            currentSort = { column: columnIndex, order: order }; // save current sort

            rows.sort((a, b) => {
                const cellA = a.cells[columnIndex].textContent.trim().toLowerCase();
                const cellB = b.cells[columnIndex].textContent.trim().toLowerCase();

                if (cellA < cellB) return order === "asc" ? -1 : 1;
                if (cellA > cellB) return order === "asc" ? 1 : -1;
                return 0;
            });

            // Apply filter after sorting
            rows.forEach(row => {
                const status = row.cells[1].textContent.trim(); // Status column
                if (!currentFilter || status === currentFilter) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
                tbody.appendChild(row);
            });
        }

        function filterStatus(status) {
            currentFilter = status; // save current filter
            const table = document.getElementById("inventoryTable");
            const rows = table.tBodies[0].rows;

            for (let row of rows) {
                const rowStatus = row.cells[1].textContent.trim(); // Status column
                if (!status || rowStatus === status) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }

            // Reapply sorting if already sorted
            if (currentSort.column !== null) {
                sortTable(currentSort.column, currentSort.order);
            }
        }

        function viewItem(status) {
    const statusLabels = {
        active: 'Active',
        retired: 'Retired',
        in_storage: 'In Storage',
        repairing: 'Under Repair',
        in_use: 'In Use',
        faulty: 'Faulty'
    };

    const statusColors = {
        active: 'text-green-600',
        retired: 'text-red-600',
        in_storage: 'text-yellow-600',
        repairing: 'text-blue-600',
        in_use: 'text-indigo-600',
        faulty: 'text-pink-600'
    };

    const statusEl = document.getElementById('view_status');

    statusEl.textContent = statusLabels[status] ?? status;
    statusEl.className = `font-semibold ${statusColors[status] ?? 'text-gray-600'}`;
}

    </script>

</body>

</html>