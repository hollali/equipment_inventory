<?php
session_start();
require_once "./config/database.php";
require_once __DIR__ . '/vendor/autoload.php';

/* ================== ERROR REPORTING ================== */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ================== DB ================== */
$db = new Database();
$conn = $db->getConnection();
if (!$conn)
    die("Database connection failed");

/* ================== ADD INVENTORY ================== */
if (isset($_POST['save'])) {
    $stmt = $conn->prepare("
        INSERT INTO inventory_items (
            asset_tag,
            device_type,
            brand_id,
            model,
            serial_number,
            specifications,
            department_id,
            assigned_user,
            location_id,
            `condition`,
            status,
            category_id,
            remarks,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "ssisssissssss",
        $_POST['asset_tag'],
        $_POST['device_type'],
        $_POST['brand_id'],
        $_POST['model'],
        $_POST['serial_number'],
        $_POST['specifications'],
        $_POST['department_id'],
        $_POST['assigned_user'],
        $_POST['location_id'],
        $_POST['condition'],
        $_POST['status'],
        $_POST['category_id'],
        $_POST['remarks']
    );

    if ($stmt->execute()) {
        header("Location: inventory.php?success=added");
        exit;
    } else {
        die("Add failed: " . $stmt->error);
    }
}

/* ================== EDIT MODES ================== */
$editMode = isset($_GET['edit']) && is_numeric($_GET['edit']);

/* ================== FETCH ITEM FOR EDIT ================== */
$item = null;

if ($editMode) {
    $stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
}

/* ================== UPDATE INVENTORY ================== */
if (isset($_POST['update_inventory']) && is_numeric($_POST['id'])) {
    $stmt = $conn->prepare("
        UPDATE inventory_items SET
            device_type=?,
            brand_id=?,
            model=?,
            serial_number=?,
            specifications=?,
            department_id=?,
            assigned_user=?,
            location_id=?,
            `condition`=?,
            status=?,
            category_id=?,
            remarks=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "sisssissssssi",
        $_POST['device_type'],
        $_POST['brand_id'],
        $_POST['model'],
        $_POST['serial_number'],
        $_POST['specifications'],
        $_POST['department_id'],
        $_POST['assigned_user'],
        $_POST['location_id'],
        $_POST['condition'],
        $_POST['status'],
        $_POST['category_id'],
        $_POST['remarks'],
        $_POST['id']
    );

    if ($stmt->execute()) {
        header("Location: inventory.php?success=updated");
        exit;
    } else {
        die($stmt->error);
    }
}

/* ================== RETIRE INVENTORY ================== */
if (isset($_POST['retire'], $_POST['retire_id'])) {
    $id = (int) $_POST['retire_id'];

    $stmt = $conn->prepare("
        UPDATE inventory_items 
        SET status = 'retired'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: inventory.php?msg=retired");
    exit;
}

/* ================== DELETE INVENTORY ================== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: inventory.php?msg=deleted");
    exit;
}

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
if (empty($statuses))
    $statuses = $allowedStatuses;

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

if (!empty($_GET['department'])) {
    $where[] = "i.department_id = ?";
    $params[] = (int) $_GET['department'];
    $paramTypes .= 'i';
}

if (!empty($_GET['category'])) {
    $where[] = "i.category_id=?";
    $params[] = (int) $_GET['category'];
    $paramTypes .= 'i';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderBy = 'i.id';
$orderDir = (($_GET['sort'] ?? '') === 'asc') ? 'ASC' : 'DESC';

/* ================== PAGINATION ================== */
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], [10, 25, 50, 100]) 
    ? (int)$_GET['limit'] 
    : 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

/* ================== COUNT TOTAL RECORDS ================== */
$countQuery = $conn->prepare("
    SELECT COUNT(*) as total
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    $whereSql
");

if ($params) {
    $countQuery->bind_param($paramTypes, ...$params);
}
$countQuery->execute();
$countResult = $countQuery->get_result();
$totalRecords = $countResult->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

/* ================== FETCH PAGINATED INVENTORY ================== */
$sql = "
    SELECT i.*, c.category_name, b.brand_name, d.department_name, l.location_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    $whereSql
    ORDER BY $orderBy $orderDir
    LIMIT ? OFFSET ?
";

// Add limit and offset to params
$params[] = $limit;
$params[] = $offset;
$paramTypes .= "ii";

$listQuery = $conn->prepare($sql);
if ($params) {
    $listQuery->bind_param($paramTypes, ...$params);
}
$listQuery->execute();
$list = $listQuery->get_result();

/* ================== ACTIVE FILTER TAGS ================== */
$activeFilters = [];
if (!empty($_GET['search'])) {
    $activeFilters[] = ['label' => 'Search: ' . htmlspecialchars($_GET['search']), 'param' => 'search'];
}
if (!empty($_GET['status'])) {
    $activeFilters[] = ['label' => 'Status: ' . htmlspecialchars($statusLabels[$_GET['status']] ?? $_GET['status']), 'param' => 'status'];
}
if (!empty($_GET['category'])) {
    $catName = '';
    foreach ($categoriesArr as $c) {
        if ($c['id'] == $_GET['category']) {
            $catName = $c['category_name'];
        }
    }
    $activeFilters[] = ['label' => 'Category: ' . htmlspecialchars($catName), 'param' => 'category'];
}

if (!empty($_GET['department'])) {
    $deptName = '';
    foreach ($departmentsArr as $d) {
        if ($d['id'] == $_GET['department']) {
            $deptName = $d['department_name'];
            break;
        }
    }

    $activeFilters[] = [
        'label' => 'Department: ' . htmlspecialchars($deptName),
        'param' => 'department'
    ];
}

if (!empty($_GET['location'])) {
    $locName = '';
    foreach ($locationsArr as $l) {
        if ($l['id'] == $_GET['location']) {
            $locName = $l['location_name'];
            break;
        }
    }

    $activeFilters[] = [
        'label' => 'Location: ' . htmlspecialchars($locName),
        'param' => 'location'
    ];
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

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">
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
    <form method="GET" class="w-full">
        <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-end">
            <!-- Search Bar -->
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Search</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input id="searchInput" onkeyup="searchTable()" type="text" name="search"
                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                        placeholder="Search by asset, type, brand, model, or user..." autocomplete="off"
                        class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-gray-50 focus:bg-white">
                </div>
            </div>

            <!-- Location Filter -->
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Location</label>
                <div class="relative">
                    <i class="fas fa-map-marker-alt absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                    <select name="location"
                        class="w-full pl-11 pr-10 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all appearance-none bg-white cursor-pointer hover:border-gray-300">
                        <option value="">All Locations</option>
                        <?php foreach ($locationsArr as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= ($_GET['location'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['location_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
            </div>

<!-- Status Filter -->
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Status</label>
                <div class="relative">
                    <i class="fas fa-flag absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                    <select name="status" id="statusFilter"
                        class="w-full pl-11 pr-10 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all appearance-none bg-white cursor-pointer hover:border-gray-300">
                        <option value="">All Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>>
                                <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
                <button type="submit"
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-200 inline-flex items-center font-medium shadow-sm hover:shadow-md whitespace-nowrap">
                    <i class="fas fa-filter mr-2"></i>
                    <span>Apply</span>
                </button>
                <a href="<?= $_SERVER['PHP_SELF'] ?>"
                    class="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 inline-flex items-center font-medium shadow-sm hover:shadow whitespace-nowrap">
                    <i class="fas fa-redo mr-2"></i>
                    <span>Reset</span>
                </a>
                <!-- Export Button (inline) -->
                <button type="button" onclick="window.location.href='export_assignments.php'"
                    class="px-6 py-3 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-700 rounded-xl hover:from-green-100 hover:to-emerald-100 hover:border-green-300 transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow font-medium whitespace-nowrap">
                    <i class="fas fa-download"></i>
                    <span>Export</span>
                </button>
            </div>
        </div>
    </form>
</div>

            <!-- ================= TABLE ================= -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="inventoryTable" class="w-full">
                        <!-- Table Header -->
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

                        <!-- Table Body -->
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
                                            <!--<div class="w-8 h-8 rounded-full bg-blue-700 flex items-center justify-center text-white text-xs font-bold">
                                                <?/*= strtoupper(substr($row['assigned_user'] ?? 'U', 0, 1)) */?>
                                            </div>--->
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
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full border <?= $statusClass ?>">
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
                                            <button onclick='openViewModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'
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
                                                class="w-9 h-9 flex items-center justify-center text-gray-600 hover:bg-gray-50 rounded-lg"
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
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================= PAGINATION ================= -->
            <?php if ($totalPages > 1): ?>
                <?php
                // Build query string with all current filters
                $queryParams = $_GET;
                unset($queryParams['page']); // Remove page from params to rebuild
                
                // Build the base URL with all parameters
                $baseUrl = '?' . (!empty($queryParams) ? http_build_query($queryParams) . '&' : '');
                ?>
                
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        <!-- Results Count -->
                        <div class="text-sm text-gray-600">
                            Showing <span class="font-medium"><?= min($limit, $totalRecords - (($page - 1) * $limit)) ?></span> of 
                            <span class="font-medium"><?= $totalRecords ?></span> inventory items
                        </div>
                        
                        <!-- Pagination Controls -->
                        <div class="flex flex-col items-center gap-4">
                            <!-- Page Numbers -->
                            <div class="flex flex-wrap items-center justify-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"
                                        class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm hover:shadow">
                                        <i class="fas fa-chevron-left text-sm"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                // Smart pagination: Show first page, last page, and pages around current
                                $showDotsStart = false;
                                $showDotsEnd = false;

                                for ($i = 1; $i <= $totalPages; $i++):
                                    // Show first page, last page, and pages around current (within 2 pages)
                                    $shouldShow = false;

                                    if ($i == 1 || $i == $totalPages) {
                                        $shouldShow = true;
                                    } elseif ($i >= $page - 2 && $i <= $page + 2) {
                                        $shouldShow = true;
                                    }

                                    if ($shouldShow):
                                        if ($i == 1 && $page > 4):
                                            $showDotsStart = true;
                                            ?>
                                            <a href="<?= $baseUrl ?>page=1"
                                                class="px-3 py-2 rounded-lg transition-colors font-medium text-sm bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                1
                                            </a>
                                            <?php if ($showDotsStart): ?>
                                                <span class="px-2 text-gray-400">...</span>
                                            <?php endif; ?>
                                        <?php elseif ($i == $totalPages && $page < $totalPages - 3):
                                            $showDotsEnd = true;
                                            if ($showDotsEnd): ?>
                                                <span class="px-2 text-gray-400">...</span>
                                            <?php endif; ?>
                                            <a href="<?= $baseUrl ?>page=<?= $totalPages ?>"
                                                class="px-3 py-2 rounded-lg transition-colors font-medium text-sm bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                <?= $totalPages ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                                class="px-3 py-2 rounded-lg transition-colors font-medium text-sm <?= $i == $page
                                                    ? 'bg-gradient-to-r from-blue-600 to-blue-600 text-white shadow-lg'
                                                    : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm hover:shadow' ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"
                                        class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm hover:shadow">
                                        <i class="fas fa-chevron-right text-sm"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Page Info -->
                            <p class="text-center text-sm text-gray-500">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </p>
                        </div>
                        
                        <!-- Items per page selector -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Show:</span>
                            <select onchange="changeItemsPerPage(this)" 
                                    class="text-sm bg-white border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
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

            <!-- ================= EDIT MODALS ================= -->
            <?php 
            // Reset pointer to beginning of results
            $list->data_seek(0);
            while ($row = $list->fetch_assoc()): 
            ?>
                <div id="editModal<?= $row['id'] ?>"
                    class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
                    onclick="closeModalOnBackdrop(event, 'editModal<?= $row['id'] ?>')">

                    <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
                        onclick="event.stopPropagation()">

                        <!-- Modal Header -->
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
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
                            <form method="POST" action="inventory.php" id="editForm<?= $row['id'] ?>">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                
                                <!-- Basic Information -->
                                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <i class="fas fa-info-circle text-blue-600"></i>
                                        Basic Information
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Asset Tag</label>
                                            <input readonly name="asset_tag" value="<?= htmlspecialchars($row['asset_tag']) ?>"
                                                class="w-full border border-gray-300 p-3 rounded-lg bg-gray-100 text-gray-600">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Device Type *</label>
                                            <input name="device_type" required value="<?= htmlspecialchars($row['device_type']) ?>"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Brand</label>
                                            <select name="brand_id" required class="w-full border border-gray-300 p-3 rounded-lg">
                                                <?php foreach ($brandsArr as $b): ?>
                                                    <option value="<?= $b['id'] ?>" <?= $row['brand_id'] == $b['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($b['brand_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                                            <input name="model" value="<?= htmlspecialchars($row['model']) ?>"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                                            <input name="serial_number" value="<?= htmlspecialchars($row['serial_number']) ?>"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Specifications</label>
                                            <input name="specifications" value="<?= htmlspecialchars($row['specifications']) ?>"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Assignment Details -->
                                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <i class="fas fa-user-tag text-blue-600"></i>
                                        Assignment Details
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                            <select name="department_id" required class="w-full border border-gray-300 p-3 rounded-lg">
                                                <?php foreach ($departmentsArr as $d): ?>
                                                    <option value="<?= $d['id'] ?>" <?= $row['department_id'] == $d['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($d['department_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Assigned User</label>
                                            <input name="assigned_user" value="<?= htmlspecialchars($row['assigned_user']) ?>"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                                            <select name="location_id" required class="w-full border border-gray-300 p-3 rounded-lg">
                                                <?php foreach ($locationsArr as $l): ?>
                                                    <option value="<?= $l['id'] ?>" <?= $row['location_id'] == $l['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($l['location_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Status & Category -->
                                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <i class="fas fa-cog text-blue-600"></i>
                                        Status & Category
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Condition</label>
                                            <select name="condition" class="w-full border border-gray-300 p-3 rounded-lg">
                                                <?php foreach (['Excellent', 'Good', 'Fair', 'Poor'] as $c): ?>
                                                    <option value="<?= $c ?>" <?= $row['condition'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                            <select name="status" class="w-full border border-gray-300 p-3 rounded-lg">
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
                                                    <option value="<?= $value ?>" <?= $row['status'] === $value ? 'selected' : '' ?>>
                                                        <?= $label ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                                            <select name="category_id" required class="w-full border border-gray-300 p-3 rounded-lg">
                                                <?php foreach ($categoriesArr as $c): ?>
                                                    <option value="<?= $c['id'] ?>" <?= $row['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($c['category_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Additional Notes -->
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                        <i class="fas fa-sticky-note text-blue-600"></i>
                                        Additional Notes
                                    </h3>
                                    <textarea name="remarks" rows="4"
                                        class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($row['remarks']) ?></textarea>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                            <button type="button" onclick="closeModal('editModal<?= $row['id'] ?>')"
                                class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 font-medium">
                                Cancel
                            </button>
                            <button type="submit" form="editForm<?= $row['id'] ?>" name="update_inventory"
                                class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>

            <!-- ================= ADD MODAL ================= -->
            <div id="addModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
                onclick="closeModalOnBackdrop(event, 'addModal')">
                <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
                    onclick="event.stopPropagation()">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
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
                                    <i class="fas fa-info-circle text-blue-600"></i>
                                    Basic Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Asset Tag</label>
                                        <input readonly name="asset_tag" value="<?= htmlspecialchars($asset_tag_preview) ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg bg-gray-100 text-gray-600">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Device Type <span class="text-red-500">*</span></label>
                                        <input name="device_type" value="<?= $item['device_type'] ?? '' ?>" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., Laptop, Desktop, Monitor">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Brand <span class="text-red-500">*</span></label>
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
                                    <i class="fas fa-user-tag text-blue-600"></i>
                                    Assignment Details
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Department <span class="text-red-500">*</span></label>
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
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Location <span class="text-red-500">*</span></label>
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
                                    <i class="fas fa-cog text-blue-600"></i>
                                    Status & Category
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Condition <span class="text-red-500">*</span></label>
                                        <select name="condition" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="Excellent" <?= (isset($item['condition']) && $item['condition'] == 'Excellent') ? 'selected' : '' ?>>Excellent</option>
                                            <option value="Good" <?= (isset($item['condition']) && $item['condition'] == 'Good') ? 'selected' : '' ?>>Good</option>
                                            <option value="Fair" <?= (isset($item['condition']) && $item['condition'] == 'Fair') ? 'selected' : '' ?>>Fair</option>
                                            <option value="Poor" <?= (isset($item['condition']) && $item['condition'] == 'Poor') ? 'selected' : '' ?>>Poor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
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
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
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
                                    <i class="fas fa-sticky-note text-blue-600"></i>
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
                            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
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
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
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
                        <div class="bg-gradient-to-br from-blue-50 to-blue-50 rounded-xl p-5 mb-5">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle text-blue-600"></i>
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
                                <i class="fas fa-user-tag text-blue-600"></i>
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
                        <div class="bg-gradient-to-br from-blue-50 to-blue-50 rounded-xl p-5 mb-5">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-cog text-blue-600"></i>
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
                                <i class="fas fa-sticky-note text-blue-600"></i>
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
                            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            <i class="fas fa-check mr-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= RETIRE MODAL ================= -->
            <div id="retireModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
                <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Retire Device</h3>
                        <button onclick="closeRetireModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="px-6 py-5 text-gray-700">
                        <p class="mb-2 font-medium">Are you sure you want to retire this device?</p>
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
                                class="px-4 py-2 rounded-lg bg-gray-600 text-white hover:bg-gray-700">
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
                        <h3 class="text-lg font-semibold text-gray-800">Confirm Delete</h3>
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
                        <a id="confirmDeleteBtn" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                            Yes, Delete
                        </a>
                    </div>
                </div>
            </div>
        </main>
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
            modal.classList.add('flex');
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
                const assetTag = row.querySelector('[data-key="asset_tag"]')?.textContent.toLowerCase() || '';
                const deviceType = row.querySelector('[data-key="device_type"]')?.textContent.toLowerCase() || '';
                const brandName = row.querySelector('[data-key="brand_name"]')?.textContent.toLowerCase() || '';
                const model = row.querySelector('[data-key="model"]')?.textContent.toLowerCase() || '';
                const assignedUser = row.querySelector('[data-key="assigned_user"]')?.textContent.toLowerCase() || '';

                const searchableText = `${assetTag} ${deviceType} ${brandName} ${model} ${assignedUser}`;
                row.style.display = searchableText.includes(searchTerm) ? "" : "none";
            });
        }

        // ==================== PAGINATION ====================
        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }
    </script>
</body>
</html>