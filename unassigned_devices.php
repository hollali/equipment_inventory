<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/config/database.php";

// Authorization check removed as requested
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

$db = new Database();
$conn = $db->getConnection();

/* Fetch Departments and Categories for Filters */
$departmentsArr = [];
$deptResult = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name");
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departmentsArr[] = $row;
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

/* Get unassigned and stored devices count */
// Updated query to match your database schema
$unassignedCountQuery = "SELECT COUNT(*) as count FROM inventory_items i
                         LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id AND dua.status = 'assigned'
                         WHERE (dua.id IS NULL OR i.status = 'in_storage') 
                         AND i.status != 'retired'";
$unassignedCountResult = $conn->query($unassignedCountQuery);
$unassignedCount = $unassignedCountResult->fetch_assoc()['count'];

/* Get unassigned and stored devices with pagination */
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build WHERE clause for filters - Updated for your schema
$whereConditions = [
    "(dua.id IS NULL OR i.status = 'in_storage')",
    "i.status != 'retired'"
];
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

/* Get total count with filters - Updated query */
$countQuery = "SELECT COUNT(DISTINCT i.id) as total 
               FROM inventory_items i
               LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id AND dua.status = 'assigned'
               $whereClause";

$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($paramTypes, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalUnassigned = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalUnassigned / $perPage);

/* Get unassigned and stored devices - Updated query */
$unassignedDevices = [];
$query = " 
    SELECT DISTINCT
        i.*,
        b.brand_name AS brand_name,
        d.department_name AS department_name,
        c.category_name AS category_name,
        dua.user_id as assigned_user_id,
        dua.assigned_at
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id AND dua.status = 'assigned'
    $whereClause
    ORDER BY i.updated_at DESC
    LIMIT ? OFFSET ?
";

// Add pagination parameters
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

// Filter options for dropdowns - Updated to match your statuses
$statusOptions = [
    'in_storage' => 'Store',
    'active' => 'Active',
    'faulty' => 'Faulty',
    'repairing' => 'Repairing'
];

$conditionOptions = [
    'New' => 'New',
    'Good' => 'Good',
    'Fair' => 'Fair',
    'Poor' => 'Poor',
    'Faulty' => 'Faulty'
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
    <title>Unassigned & Stored Devices - Equipment Inventory</title>
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
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid;
            white-space: nowrap;
        }

        .condition-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            border: 1px solid;
            white-space: nowrap;
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

        /* Improved Table Styling */
        .table-container {
            width: 100%;
            overflow: visible;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
            padding: 1rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4b5563;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }

        .data-table tbody tr {
            transition: all 0.15s ease;
        }

        .data-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .data-table tbody td {
            padding: 1rem 1rem;
            font-size: 0.875rem;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .compact-column {
            max-width: 120px;
            min-width: 120px;
        }

        .compact-column-sm {
            max-width: 100px;
            min-width: 100px;
        }

        .actions-column {
            max-width: 140px;
            min-width: 140px;
            white-space: nowrap;
        }

        /* Toast Notification Styles */
        #toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-width: 400px;
            width: 100%;
        }

        .toast {
            position: relative;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.hide {
            transform: translateX(100%);
            opacity: 0;
        }

        .toast-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-left: 4px solid #065f46;
        }

        .toast-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-left: 4px solid #7f1d1d;
        }

        .toast-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border-left: 4px solid #92400e;
        }

        .toast-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-left: 4px solid #1e40af;
        }

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
            font-size: 0.875rem;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .toast-message {
            opacity: 0.9;
        }

        .toast-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .toast-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.5);
            width: 100%;
            transform-origin: left;
            animation: progress 5s linear forwards;
        }

        @keyframes progress {
            from {
                transform: scaleX(1);
            }

            to {
                transform: scaleX(0);
            }
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .text-ellipsis {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .modal-container {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            padding: 1rem;
        }

        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            position: relative;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 90vw;
            max-height: 90vh;
            overflow: hidden;
            animation: fadeInUp 0.3s ease-out;
        }

        .device-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .asset-tag-badge {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-4 md:p-8 ml-0 md:ml-64">

        <!-- Display Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="success-toast" class="hidden">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div id="error-toast" class="hidden">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Header -->
        <div class="mb-8 animate-fade-in-up">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1
                        class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Unassigned & Stored Devices
                    </h1>
                    <p class="text-gray-600 text-sm mt-2 flex items-center gap-2">
                        <i class="fas fa-boxes-stacked text-blue-500"></i>
                        Manage devices that are not assigned to any user or are in storage
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-tools"></i>
                        <span class="font-semibold text-sm">UNASSIGNED INVENTORY</span>
                    </div>
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
                        <p class="text-sm text-gray-500">Total Available</p>
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
                        <p class="text-sm text-gray-500">In Storage</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php
                            $storeQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'in_storage' AND status != 'retired'";
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
                        <p class="text-sm text-gray-500">Active Devices</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php
                            $activeQuery = "SELECT COUNT(*) as count FROM inventory_items 
                                         WHERE status = 'active' AND status != 'retired'";
                            $activeResult = $conn->query($activeQuery);
                            echo number_format($activeResult->fetch_assoc()['count']);
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
                            $faultyQuery = "SELECT COUNT(*) as count FROM inventory_items 
                                            WHERE status = 'faulty' AND status != 'retired'";
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
                    <button type="button" onclick="clearFilters()"
                        class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                        <i class="fas fa-times-circle"></i> Clear Filters
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
                                <option value="<?= $d['id'] ?>" <?= ($filterDepartment == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
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
                        class="px-6 py-2.5 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-700 rounded-xl hover:from-green-100 hover:to-emerald-100 hover:border-green-300 transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow font-medium">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </form>

        <!-- View Toggle and Info -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Available Devices List</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Showing <?= number_format(min($offset + 1, $totalUnassigned)) ?> -
                    <?= number_format(min($offset + $perPage, $totalUnassigned)) ?> of
                    <?= number_format($totalUnassigned) ?> devices
                </p>
            </div>
            <div class="flex gap-2">
                <button id="tableViewBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2">
                    <i class="fas fa-table"></i> Table View
                </button>
                <button id="cardViewBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg flex items-center gap-2">
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

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="compact-column-sm">Device</th>
                            <th class="compact-column">Asset Tag</th>
                            <th class="compact-column">Department</th>
                            <th class="compact-column-sm">Condition</th>
                            <th class="compact-column-sm">Status</th>
                            <th class="compact-column-sm">Last Updated</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($unassignedDevices)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-check-circle text-4xl text-green-400"></i>
                                        </div>
                                        <p class="text-gray-400 font-medium">All devices are assigned!</p>
                                        <p class="text-xs text-gray-400">No available devices found with current filters</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($unassignedDevices as $device): ?>
                                <?php
                                // Get status and condition display
                                $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                                $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);

                                // Check if device is actually assigned
                                $isAssigned = !empty($device['assigned_user_id']);
                                $statusClass = $isAssigned ? 'bg-gray-100' : '';
                                ?>
                                <tr class="<?= $statusClass ?>">
                                    <!-- Device Info -->
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="device-icon bg-gradient-to-br from-blue-100 to-blue-200">
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
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900 text-sm text-ellipsis"
                                                    title="<?= htmlspecialchars($device['brand_name'] ?? 'Unknown') . ' ' . htmlspecialchars($device['model'] ?? '') ?>">
                                                    <?= htmlspecialchars($device['brand_name'] ?? 'Unknown') ?>
                                                    <?php if (!empty($device['model'])): ?>
                                                        <span class="text-gray-600"><?= htmlspecialchars($device['model']) ?></span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1 text-ellipsis"
                                                    title="<?= htmlspecialchars($device['device_type'] ?? 'Device') . (!empty($device['category_name']) ? ' • ' . htmlspecialchars($device['category_name']) : '') ?>">
                                                    <?= htmlspecialchars($device['device_type'] ?? 'Device') ?>
                                                    <?php if (!empty($device['category_name'])): ?>
                                                        • <?= htmlspecialchars($device['category_name']) ?>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if ($isAssigned): ?>
                                                    <div class="mt-1">
                                                        <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                                                            <i class="fas fa-user mr-1"></i>Assigned (needs update)
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Asset Tag -->
                                    <td>
                                        <span class="asset-tag-badge"
                                            title="Asset Tag: <?= htmlspecialchars($device['asset_tag'] ?? 'N/A') ?>">
                                            <?= htmlspecialchars($device['asset_tag'] ?? 'N/A') ?>
                                        </span>
                                    </td>

                                    <!-- Department -->
                                    <td>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-building text-gray-400 text-xs"></i>
                                                <span class="text-gray-700 text-sm text-ellipsis"
                                                    title="<?= htmlspecialchars($device['department_name'] ?? 'N/A') ?>">
                                                    <?= htmlspecialchars($device['department_name'] ?? 'N/A') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Condition -->
                                    <td>
                                        <span class="condition-badge <?= $conditionDisplay['color'] ?>"
                                            title="Condition: <?= htmlspecialchars($conditionDisplay['label']) ?>">
                                            <?= htmlspecialchars($conditionDisplay['label']) ?>
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <span class="status-badge <?= $statusDisplay['color'] ?>"
                                            title="Status: <?= htmlspecialchars($statusDisplay['label']) ?>">
                                            <?= htmlspecialchars($statusDisplay['label']) ?>
                                        </span>
                                    </td>

                                    <!-- Last Updated -->
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-medium text-gray-700">
                                                <?= date('M j, Y', strtotime($device['updated_at'] ?? 'now')) ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <?= date('g:i A', strtotime($device['updated_at'] ?? 'now')) ?>
                                            </span>
                                            <?php if (!empty($device['assigned_at'])): ?>
                                                <span class="text-xs text-orange-600 mt-1">
                                                    <i class="fas fa-clock"></i> Assigned:
                                                    <?= date('M j', strtotime($device['assigned_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <div class="flex gap-2">
                                            <?php if (!$isAssigned || $device['status'] === 'in_storage'): ?>
                                                <button onclick="redirectToInventory(<?= $device['id'] ?>)"
                                                    class="action-btn bg-blue-500 text-white hover:bg-blue-600"
                                                    title="Go to Device in Inventory">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="redirectToInventory(<?= $device['id'] ?>)"
                                                    class="action-btn bg-yellow-500 text-white hover:bg-yellow-600"
                                                    title="Go to Device in Inventory">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </button>
                                            <?php endif; ?>
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
                                <a href="?page=<?= $page - 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
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
                                <a href="?page=<?= $i ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-all <?= $activeClass ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
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
                            <p class="text-xs text-gray-400">No available devices found with current filters</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($unassignedDevices as $device): ?>
                    <?php
                    $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                    $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);
                    $isAssigned = !empty($device['assigned_user_id']);
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
                                        <?php if ($isAssigned): ?>
                                            <div class="mt-1">
                                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                                                    <i class="fas fa-user mr-1"></i>Assigned
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6">
                            <!-- Asset Tag -->
                            <div class="mb-4">
                                <span class="asset-tag-badge">
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

                            <!-- Department Info -->
                            <div class="space-y-2 mb-6">
                                <div class="flex items-center gap-2 text-sm">
                                    <i class="fas fa-building text-gray-400"></i>
                                    <span
                                        class="text-gray-700"><?= htmlspecialchars($device['department_name'] ?? 'N/A') ?></span>
                                </div>
                            </div>

                            <!-- Last Updated -->
                            <div class="text-xs text-gray-500 mb-6">
                                <i class="fas fa-clock mr-1"></i>
                                Last updated: <?= date('M j, Y', strtotime($device['updated_at'] ?? 'now')) ?>
                                <?php if (!empty($device['assigned_at'])): ?>
                                    <br>
                                    <i class="fas fa-user-clock mr-1 text-orange-500"></i>
                                    Assigned: <?= date('M j, Y', strtotime($device['assigned_at'])) ?>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <button onclick="redirectToInventory(<?= $device['id'] ?>)"
                                    class="flex-1 px-4 py-2.5 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-all flex items-center justify-center gap-2"
                                    title="Go to Device in Inventory">
                                    <i class="fas fa-external-link-alt"></i> View in Inventory
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

    <!-- Device Details Modal -->
    <div id="viewModal" class="modal-container hidden">
        <div class="modal-backdrop" onclick="closeViewModal()"></div>
        <div class="modal-content w-full max-w-2xl">
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
    <!--footer-->
    <?php include __DIR__ . '/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // ==================== TOAST NOTIFICATION FUNCTIONS ====================
        class Toast {
            constructor(type, title, message, duration = 5000) {
                this.type = type;
                this.title = title;
                this.message = message;
                this.duration = duration;
                this.id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                this.timeout = null;
            }

            show() {
                const container = document.getElementById('toast-container');
                if (!container) {
                    console.error('Toast container not found!');
                    return;
                }

                // Create toast element
                const toast = document.createElement('div');
                toast.id = this.id;
                toast.className = `toast toast-${this.type}`;
                toast.innerHTML = `
                    <div class="toast-icon">
                        ${this.getIcon()}
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${this.title}</div>
                        <div class="toast-message">${this.message}</div>
                    </div>
                    <button class="toast-close" onclick="Toast.hide('${this.id}')">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="toast-progress" style="animation-duration: ${this.duration}ms"></div>
                `;

                // Add to container
                container.appendChild(toast);

                // Trigger animation
                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);

                // Auto dismiss
                this.timeout = setTimeout(() => {
                    this.hide();
                }, this.duration);
            }

            getIcon() {
                const icons = {
                    'success': '<i class="fas fa-check-circle"></i>',
                    'error': '<i class="fas fa-exclamation-circle"></i>',
                    'warning': '<i class="fas fa-exclamation-triangle"></i>',
                    'info': '<i class="fas fa-info-circle"></i>'
                };
                return icons[this.type] || icons['info'];
            }

            static showSuccess(message, title = 'Success', duration = 5000) {
                new Toast('success', title, message, duration).show();
            }

            static showError(message, title = 'Error', duration = 5000) {
                new Toast('error', title, message, duration).show();
            }

            static showWarning(message, title = 'Warning', duration = 5000) {
                new Toast('warning', title, message, duration).show();
            }

            static showInfo(message, title = 'Info', duration = 3000) {
                new Toast('info', title, message, duration).show();
            }

            hide() {
                const toast = document.getElementById(this.id);
                if (!toast) return;

                toast.classList.remove('show');
                toast.classList.add('hide');

                clearTimeout(this.timeout);

                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }

            static hide(id) {
                const toast = document.getElementById(id);
                if (!toast) return;

                toast.classList.remove('show');
                toast.classList.add('hide');

                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }

            static clearAll() {
                const container = document.getElementById('toast-container');
                if (!container) return;
                container.innerHTML = '';
            }
        }

        // Function to show PHP session messages as toasts
        function showPHPToasts() {
            // Success toast
            const successToast = document.getElementById('success-toast');
            if (successToast) {
                Toast.showSuccess(successToast.textContent.trim(), 'Success');
            }

            // Error toast
            const errorToast = document.getElementById('error-toast');
            if (errorToast) {
                Toast.showError(errorToast.textContent.trim(), 'Error');
            }
        }

        // Function to redirect to inventory page for specific device
        function redirectToInventory(deviceId) {
            // Redirect to the main inventory page with the device ID as parameter
            window.location.href = `inventory.php?device_id=${deviceId}&highlight=true`;
        }

        // Initialize on DOM ready
        $(document).ready(function () {
            // Show any PHP toasts
            showPHPToasts();

            // Initialize Select2
            $('.filter-select').select2({
                placeholder: "Select option...",
                allowClear: true
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

            // Make table responsive on mobile
            function adjustTableLayout() {
                const container = document.querySelector('.table-container');
                const screenWidth = window.innerWidth;

                if (screenWidth < 768) {
                    container.style.overflowX = 'auto';
                    container.classList.remove('no-scrollbar');
                } else {
                    container.style.overflowX = 'visible';
                    container.classList.add('no-scrollbar');
                }
            }

            // Initial adjustment
            adjustTableLayout();

            // Adjust on resize
            window.addEventListener('resize', adjustTableLayout);
        });

        // Table search functionality
        document.getElementById('searchTable').addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#tableView tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide no results message
            const tbody = document.querySelector('#tableView tbody');
            let noResultsRow = tbody.querySelector('.no-results-row');

            if (visibleCount === 0 && !noResultsRow) {
                const noResultsHTML = `
                    <tr class="no-results-row">
                        <td colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="fas fa-search text-4xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-400 font-medium">No matching devices found</p>
                                <p class="text-xs text-gray-400">Try different search terms</p>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', noResultsHTML);
            } else if (visibleCount > 0 && noResultsRow) {
                noResultsRow.remove();
            }
        });

        function clearFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDepartment').value = '';
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
            const brand = document.getElementById('filterBrand').value;
            const category = document.getElementById('filterCategory').value;
            const condition = document.getElementById('filterCondition').value;

            // Build query string
            const params = new URLSearchParams();
            if (status) params.append('status', status);
            if (department) params.append('department', department);
            if (brand) params.append('brand', brand);
            if (category) params.append('category', category);
            if (condition) params.append('condition', condition);

            // Redirect to export script
            window.location.href = `export_unassigned.php?${params.toString()}`;
        }

        // View Modal Functions
        function viewDeviceDetails(device) {
            // Set title
            document.getElementById('deviceTitle').textContent = `${device.brand_name} ${device.model || ''} - ${device.asset_tag}`;

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
                                <span class="condition-badge">${escapeHtml(device.condition || 'N/A')}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">Status:</span>
                                <span class="status-badge">${escapeHtml(device.status || 'N/A')}</span>
                            </div>
                            ${device.assigned_user_id ?
                    `<p><span class="font-medium">Assigned to User ID:</span> ${device.assigned_user_id}</p>
                                 <p><span class="font-medium">Assigned Since:</span> ${escapeHtml(device.assigned_at ? new Date(device.assigned_at).toLocaleDateString() : 'N/A')}</p>`
                    : ''}
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
                        <h3 class="font-semibold text-gray-800 mb-2">Device Details</h3>
                        <div class="space-y-2">
                            <p><span class="font-medium">Brand:</span> ${escapeHtml(device.brand_name || 'N/A')}</p>
                            <p><span class="font-medium">Model:</span> ${escapeHtml(device.model || 'N/A')}</p>
                            <p><span class="font-medium">Department:</span> ${escapeHtml(device.department_name || 'N/A')}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-2">Remarks</h3>
                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                        <p class="text-gray-700 whitespace-pre-line">${escapeHtml(device.remarks || 'No remarks available.')}</p>
                    </div>
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
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Escape key closes modals
            if (e.key === 'Escape') {
                if (!document.getElementById('viewModal').classList.contains('hidden')) {
                    closeViewModal();
                }
            }
        });
    </script>

</body>

</html>