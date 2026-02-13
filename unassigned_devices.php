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

// Status colors - solid, no gradients
$statusColors = [
    'active' => 'bg-green-50 text-green-700 border-green-200',
    'in_use' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'in_storage' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'repairing' => 'bg-gray-50 text-gray-700 border-gray-200',
    'faulty' => 'bg-pink-50 text-pink-700 border-pink-200',
    'retired' => 'bg-red-50 text-red-700 border-red-200'
];

$statusLabels = [
    'active' => 'Active',
    'in_use' => 'In Use',
    'in_storage' => 'Store',
    'repairing' => 'Repairing',
    'faulty' => 'Faulty',
    'retired' => 'Retired'
];

// Condition colors - solid, no gradients
$conditionColors = [
    'new' => 'bg-blue-50 text-blue-700 border-blue-200',
    'good' => 'bg-green-50 text-green-700 border-green-200',
    'fair' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
    'poor' => 'bg-orange-50 text-orange-700 border-orange-200',
    'faulty' => 'bg-red-50 text-red-700 border-red-200'
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

    $colorClass = $statusColors[$status] ?? 'bg-gray-50 text-gray-700 border-gray-200';
    $label = $statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status));

    return ['color' => $colorClass, 'label' => $label];
}

// Function to get condition display
function getConditionDisplay($condition, $conditionColors, $conditionLabels)
{
    $condition = strtolower($condition);

    $colorClass = $conditionColors[$condition] ?? 'bg-gray-50 text-gray-700 border-gray-200';
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
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        /* Clean color palette - no gradients */
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-200: #bfdbfe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fed7aa;
            --error: #ef4444;
            --error-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
        }

        body {
            background-color: var(--gray-50);
            color: var(--gray-800);
        }

        /* Card styles - clean, no gradients */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-color: var(--gray-300);
        }

        /* Stats cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-blue {
            background-color: var(--primary-100);
            color: var(--primary-dark);
        }

        .stat-icon-green {
            background-color: #d1fae5;
            color: #065f46;
        }

        .stat-icon-yellow {
            background-color: #fed7aa;
            color: #92400e;
        }

        .stat-icon-red {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Filter panel */
        .filter-panel {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Form inputs */
        .form-input {
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: white;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Select2 customization */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.5rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px;
            color: var(--gray-700);
        }

        .select2-dropdown {
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Table styles - FIXED COLUMN WIDTHS */
        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow-x: auto;
            overflow-y: visible;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            width: 100%;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .data-table thead th {
            background-color: var(--gray-50);
            padding: 1rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        /* FIXED COLUMN WIDTHS */
        .data-table th:nth-child(1),
        .data-table td:nth-child(1) {
            width: 200px;
            min-width: 200px;
            max-width: 200px;
        }

        /* Device */
        .data-table th:nth-child(2),
        .data-table td:nth-child(2) {
            width: 110px;
            min-width: 110px;
            max-width: 110px;
        }

        /* Asset Tag */
        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        /* Department */
        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 100px;
            min-width: 100px;
            max-width: 100px;
        }

        /* Condition */
        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 100px;
            min-width: 100px;
            max-width: 100px;
        }

        /* Status */
        .data-table th:nth-child(6),
        .data-table td:nth-child(6) {
            width: 130px;
            min-width: 130px;
            max-width: 130px;
        }

        /* Last Updated */
        .data-table th:nth-child(7),
        .data-table td:nth-child(7) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        /* Actions */

        .data-table tbody tr {
            border-bottom: 1px solid var(--gray-100);
            transition: background-color 0.15s ease;
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .data-table tbody td {
            padding: 1rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            vertical-align: middle;
        }

        /* Status and condition badges */
        .status-badge,
        .condition-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        /* Asset tag badge */
        .asset-tag-badge {
            font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            background-color: var(--primary-50);
            color: var(--primary-dark);
            border-radius: 4px;
            border: 1px solid var(--primary-200);
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Device icon */
        .device-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-100);
            color: var(--primary-dark);
            flex-shrink: 0;
        }

        /* Action buttons */
        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .action-btn-blue {
            background-color: var(--primary);
        }

        .action-btn-purple {
            background-color: #8b5cf6;
        }

        .action-btn-yellow {
            background-color: var(--warning);
        }

        /* Button styles - solid colors */
        .btn-primary {
            background-color: var(--primary);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: white;
            color: var(--gray-700);
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-outline-green {
            background-color: white;
            color: #065f46;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid #a7f3d0;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-outline-green:hover {
            background-color: #ecfdf5;
            border-color: #6ee7b7;
        }

        /* View toggle buttons */
        .view-toggle-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid var(--gray-300);
        }

        .view-toggle-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .view-toggle-btn.inactive {
            background-color: white;
            color: var(--gray-700);
            border-color: var(--gray-300);
        }

        .view-toggle-btn.inactive:hover {
            background-color: var(--gray-50);
        }

        /* Card view */
        .device-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
            overflow: hidden;
        }

        .device-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-color: var(--gray-300);
            transform: translateY(-2px);
        }

        /* Toast notifications - solid colors with left border */
        #toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
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
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success {
            background-color: var(--success-light);
            border-left-color: var(--success);
            color: #065f46;
        }

        .toast-error {
            background-color: var(--error-light);
            border-left-color: var(--error);
            color: #991b1b;
        }

        .toast-warning {
            background-color: var(--warning-light);
            border-left-color: var(--warning);
            color: #92400e;
        }

        .toast-info {
            background-color: var(--info-light);
            border-left-color: var(--info);
            color: #1e40af;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(0, 0, 0, 0.1);
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

        /* Modal styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 48rem;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        /* Pagination */
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 0.5rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
        }

        .pagination-item:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
        }

        .pagination-item.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Text utilities */
        .text-ellipsis {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Sidebar adjustment */
        .ml-64 {
            margin-left: 16rem;
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

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .ml-64 {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>

<body class="antialiased">
    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-8 ml-64">

        <!-- Display PHP Session Messages as Toasts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="success-toast" class="hidden"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div id="error-toast" class="hidden"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center shadow-sm">
                        <i class="fas fa-boxes-stacked text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Unassigned & Stored Devices</h1>
                        <p class="text-gray-500 mt-1 flex items-center gap-2">
                            <i class="fas fa-archive text-gray-400"></i>
                            <span>Manage devices available for assignment</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-primary-50 px-4 py-2 rounded-lg border border-primary-200">
                    <span class="text-sm font-semibold text-primary-700">
                        <i class="fas fa-tools mr-2"></i>
                        <?= number_format($unassignedCount) ?> Devices Available
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Overview - Solid colors, no gradients -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card flex items-center gap-4">
                <div class="stat-icon stat-icon-blue">
                    <i class="fas fa-laptop text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Available</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($unassignedCount) ?></p>
                </div>
            </div>

            <div class="stat-card flex items-center gap-4">
                <div class="stat-icon stat-icon-green">
                    <i class="fas fa-check-circle text-xl"></i>
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

            <div class="stat-card flex items-center gap-4">
                <div class="stat-icon stat-icon-yellow">
                    <i class="fas fa-star text-xl"></i>
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

            <div class="stat-card flex items-center gap-4">
                <div class="stat-icon stat-icon-red">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
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

        <!-- Filter Panel -->
        <form method="GET" id="filterForm">
            <div class="filter-panel">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-md font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-filter text-primary"></i>
                        Filter Devices
                    </h3>
                    <button type="button" onclick="clearFilters()"
                        class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                        <i class="fas fa-times-circle"></i> Clear Filters
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                    <!-- Status -->
                    <div>
                        <label class="form-label">Status</label>
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
                        <label class="form-label">Department</label>
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
                        <label class="form-label">Brand</label>
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
                        <label class="form-label">Category</label>
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
                        <label class="form-label">Condition</label>
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
                <div class="mt-6 flex justify-end gap-3">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button type="button" onclick="exportUnassigned()" class="btn-outline-green">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </form>

        <!-- View Toggle and Search -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Available Devices</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Showing <span
                        class="font-medium"><?= number_format(min($offset + 1, $totalUnassigned)) ?>-<?= number_format(min($offset + $perPage, $totalUnassigned)) ?></span>
                    of <span class="font-medium"><?= number_format($totalUnassigned) ?></span> devices
                </p>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <!-- Search - Table View Only -->
                <div id="tableSearchContainer" class="relative flex-1 sm:flex-initial">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchTable" placeholder="Search devices..."
                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-100 focus:border-primary w-full md:w-64">
                </div>

                <!-- View Toggle -->
                <div class="flex gap-2">
                    <button id="tableViewBtn" class="view-toggle-btn active">
                        <i class="fas fa-table"></i> Table
                    </button>
                    <button id="cardViewBtn" class="view-toggle-btn inactive">
                        <i class="fas fa-th-large"></i> Cards
                    </button>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div id="tableView" class="mb-8">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Asset Tag</th>
                            <th>Department</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($unassignedDevices)): ?>
                            <tr>
                                <td colspan="7" class="py-16 text-center">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="w-20 h-20 bg-gray-100 rounded-xl flex items-center justify-center">
                                            <i class="fas fa-check-circle text-3xl text-green-500"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-1">All devices are assigned!
                                            </h3>
                                            <p class="text-sm text-gray-500">No available devices found with current filters
                                            </p>
                                        </div>
                                        <button onclick="clearFilters()" class="mt-2 btn-secondary text-sm">
                                            <i class="fas fa-times mr-2"></i>Clear Filters
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($unassignedDevices as $device): ?>
                                <?php
                                $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                                $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);
                                $isAssigned = !empty($device['assigned_user_id']);
                                ?>
                                <tr class="<?= $isAssigned ? 'bg-yellow-50/30' : '' ?>">
                                    <!-- Device Info -->
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="device-icon">
                                                <?php
                                                $deviceType = strtolower($device['device_type'] ?? 'laptop');
                                                if (strpos($deviceType, 'phone') !== false || strpos($deviceType, 'mobile') !== false): ?>
                                                    <i class="fas fa-mobile-alt"></i>
                                                <?php elseif (strpos($deviceType, 'tablet') !== false): ?>
                                                    <i class="fas fa-tablet-alt"></i>
                                                <?php elseif (strpos($deviceType, 'desktop') !== false || strpos($deviceType, 'pc') !== false): ?>
                                                    <i class="fas fa-desktop"></i>
                                                <?php elseif (strpos($deviceType, 'printer') !== false): ?>
                                                    <i class="fas fa-print"></i>
                                                <?php elseif (strpos($deviceType, 'server') !== false): ?>
                                                    <i class="fas fa-server"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-laptop"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-medium text-gray-900 text-sm text-ellipsis"
                                                    title="<?= htmlspecialchars($device['brand_name'] ?? 'Unknown') . ' ' . htmlspecialchars($device['model'] ?? '') ?>">
                                                    <?= htmlspecialchars($device['brand_name'] ?? 'Unknown') ?>
                                                    <span
                                                        class="text-gray-600"><?= htmlspecialchars($device['model'] ?? '') ?></span>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1 text-ellipsis"
                                                    title="<?= htmlspecialchars($device['device_type'] ?? 'Device') . (!empty($device['category_name']) ? ' • ' . htmlspecialchars($device['category_name']) : '') ?>">
                                                    <?= htmlspecialchars($device['device_type'] ?? 'Device') ?>
                                                    <?php if (!empty($device['category_name'])): ?>
                                                        • <?= htmlspecialchars($device['category_name']) ?>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if ($isAssigned): ?>
                                                    <span
                                                        class="inline-flex items-center mt-1 text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">
                                                        <i class="fas fa-user mr-1"></i> Assigned
                                                    </span>
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
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-building text-gray-400 text-xs"></i>
                                            <span class="text-gray-700 text-sm text-ellipsis"
                                                title="<?= htmlspecialchars($device['department_name'] ?? 'N/A') ?>">
                                                <?= htmlspecialchars($device['department_name'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Condition -->
                                    <td>
                                        <span class="condition-badge <?= $conditionDisplay['color'] ?>">
                                            <?= htmlspecialchars($conditionDisplay['label']) ?>
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <span class="status-badge <?= $statusDisplay['color'] ?>">
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
                                                    <i class="fas fa-clock"></i>
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
                                                    class="action-btn action-btn-blue" title="View in Inventory">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="redirectToInventory(<?= $device['id'] ?>)"
                                                    class="action-btn action-btn-yellow" title="View in Inventory">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="viewDeviceDetails(<?= htmlspecialchars(json_encode($device)) ?>)"
                                                class="action-btn action-btn-purple" title="View Details">
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
                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-gray-600">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                class="pagination-item">
                                <i class="fas fa-chevron-left text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        if ($startPage > 1) {
                            echo '<a href="?page=1&status=' . $filterStatus . '&department=' . $filterDepartment . '&brand=' . $filterBrand . '&category=' . $filterCategory . '&condition=' . $filterCondition . '" class="pagination-item">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="px-2 text-gray-400">...</span>';
                            }
                        }

                        for ($i = $startPage; $i <= $endPage; $i++):
                            $activeClass = $i === $page ? 'active' : '';
                            ?>
                            <a href="?page=<?= $i ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                class="pagination-item <?= $activeClass ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="px-2 text-gray-400">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $totalPages ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                class="pagination-item">
                                <?= $totalPages ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= $filterStatus ?>&department=<?= $filterDepartment ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                class="pagination-item">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Card View -->
        <div id="cardView" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php if (empty($unassignedDevices)): ?>
                <div class="col-span-full">
                    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                        <div class="flex flex-col items-center gap-4">
                            <div class="w-20 h-20 bg-gray-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-3xl text-green-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-1">All devices are assigned!</h3>
                                <p class="text-sm text-gray-500">No available devices found with current filters</p>
                            </div>
                            <button onclick="clearFilters()" class="mt-2 btn-secondary text-sm">
                                <i class="fas fa-times mr-2"></i>Clear Filters
                            </button>
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
                    <div class="device-card">
                        <!-- Card Header -->
                        <div class="p-5 border-b border-gray-200">
                            <div class="flex items-start gap-3">
                                <div class="device-icon w-12 h-12">
                                    <?php
                                    $deviceType = strtolower($device['device_type'] ?? 'laptop');
                                    if (strpos($deviceType, 'phone') !== false || strpos($deviceType, 'mobile') !== false): ?>
                                        <i class="fas fa-mobile-alt text-lg"></i>
                                    <?php elseif (strpos($deviceType, 'tablet') !== false): ?>
                                        <i class="fas fa-tablet-alt text-lg"></i>
                                    <?php elseif (strpos($deviceType, 'desktop') !== false || strpos($deviceType, 'pc') !== false): ?>
                                        <i class="fas fa-desktop text-lg"></i>
                                    <?php elseif (strpos($deviceType, 'printer') !== false): ?>
                                        <i class="fas fa-print text-lg"></i>
                                    <?php elseif (strpos($deviceType, 'server') !== false): ?>
                                        <i class="fas fa-server text-lg"></i>
                                    <?php else: ?>
                                        <i class="fas fa-laptop text-lg"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 text-ellipsis"
                                        title="<?= htmlspecialchars($device['brand_name'] ?? 'Unknown') . ' ' . htmlspecialchars($device['model'] ?? '') ?>">
                                        <?= htmlspecialchars($device['brand_name'] ?? 'Unknown') ?>
                                        <span
                                            class="text-gray-600 font-normal"><?= htmlspecialchars($device['model'] ?? '') ?></span>
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-1 text-ellipsis">
                                        <?= htmlspecialchars($device['device_type'] ?? 'Device') ?>
                                    </p>
                                    <?php if ($isAssigned): ?>
                                        <span
                                            class="inline-flex items-center mt-2 text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                                            <i class="fas fa-user mr-1"></i> Currently Assigned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-5">
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

                            <!-- Department -->
                            <div class="flex items-center gap-2 text-sm mb-4">
                                <i class="fas fa-building text-gray-400"></i>
                                <span class="text-gray-700 text-ellipsis"
                                    title="<?= htmlspecialchars($device['department_name'] ?? 'N/A') ?>">
                                    <?= htmlspecialchars($device['department_name'] ?? 'N/A') ?>
                                </span>
                            </div>

                            <!-- Category & Serial -->
                            <div class="text-xs text-gray-500 mb-4 space-y-1">
                                <?php if (!empty($device['category_name'])): ?>
                                    <div><span class="font-medium">Category:</span>
                                        <?= htmlspecialchars($device['category_name']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($device['serial_number'])): ?>
                                    <div><span class="font-medium">S/N:</span> <span
                                            class="font-mono"><?= htmlspecialchars($device['serial_number']) ?></span></div>
                                <?php endif; ?>
                            </div>

                            <!-- Last Updated -->
                            <div class="text-xs text-gray-500 mb-4 pb-4 border-b border-gray-100">
                                <i class="fas fa-clock mr-1"></i>
                                Updated: <?= date('M j, Y', strtotime($device['updated_at'] ?? 'now')) ?>
                                <?php if (!empty($device['assigned_at'])): ?>
                                    <br>
                                    <i class="fas fa-user-clock mr-1 text-orange-500"></i>
                                    Assigned: <?= date('M j, Y', strtotime($device['assigned_at'])) ?>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <button onclick="redirectToInventory(<?= $device['id'] ?>)"
                                    class="flex-1 px-3 py-2 bg-primary text-white rounded-lg text-xs font-medium hover:bg-primary-dark transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-external-link-alt"></i> View
                                </button>
                                <button onclick="viewDeviceDetails(<?= htmlspecialchars(json_encode($device)) ?>)"
                                    class="flex-1 px-3 py-2 bg-purple-600 text-white rounded-lg text-xs font-medium hover:bg-purple-700 transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- Device Details Modal -->
    <div id="viewModal" class="hidden modal-backdrop" onclick="closeViewModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-info-circle text-primary text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Device Details</h2>
                        <p class="text-sm text-gray-500 mt-1" id="deviceTitle"></p>
                    </div>
                </div>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="deviceDetails" class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                <button onclick="closeViewModal()" class="btn-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // ==================== TOAST NOTIFICATION SYSTEM ====================
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
                if (!container) return;

                const toast = document.createElement('div');
                toast.id = this.id;
                toast.className = `toast toast-${this.type}`;

                const icons = {
                    'success': '<i class="fas fa-check-circle"></i>',
                    'error': '<i class="fas fa-exclamation-circle"></i>',
                    'warning': '<i class="fas fa-exclamation-triangle"></i>',
                    'info': '<i class="fas fa-info-circle"></i>'
                };

                toast.innerHTML = `
                    <div class="toast-icon text-xl">${icons[this.type]}</div>
                    <div class="toast-content flex-1">
                        <div class="toast-title font-semibold text-sm">${this.title}</div>
                        <div class="toast-message text-sm opacity-90">${this.message}</div>
                    </div>
                    <button class="toast-close w-8 h-8 rounded-lg hover:bg-black/5 transition-colors" onclick="Toast.hide('${this.id}')">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="toast-progress" style="animation-duration: ${this.duration}ms"></div>
                `;

                container.appendChild(toast);

                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);

                this.timeout = setTimeout(() => {
                    this.hide();
                }, this.duration);
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

            static showSuccess(message, title = 'Success') {
                new Toast('success', title, message).show();
            }

            static showError(message, title = 'Error') {
                new Toast('error', title, message).show();
            }

            static showWarning(message, title = 'Warning') {
                new Toast('warning', title, message, 7000).show();
            }

            static showInfo(message, title = 'Info') {
                new Toast('info', title, message, 3000).show();
            }
        }

        // Function to show PHP session messages as toasts
        function showPHPToasts() {
            const successToast = document.getElementById('success-toast');
            if (successToast && successToast.textContent.trim()) {
                Toast.showSuccess(successToast.textContent.trim(), 'Success');
            }

            const errorToast = document.getElementById('error-toast');
            if (errorToast && errorToast.textContent.trim()) {
                Toast.showError(errorToast.textContent.trim(), 'Error');
            }
        }

        // ==================== INITIALIZATION ====================
        $(document).ready(function () {
            // Show PHP toasts
            showPHPToasts();

            // Initialize Select2
            $('.filter-select').select2({
                placeholder: "Select option...",
                allowClear: true,
                width: '100%'
            });

            // View toggle functionality
            $('#tableViewBtn').click(function () {
                $('#tableView').removeClass('hidden');
                $('#cardView').addClass('hidden');
                $('#tableSearchContainer').show();
                $(this).removeClass('inactive').addClass('active');
                $('#cardViewBtn').removeClass('active').addClass('inactive');
            });

            $('#cardViewBtn').click(function () {
                $('#cardView').removeClass('hidden');
                $('#tableView').addClass('hidden');
                $('#tableSearchContainer').hide();
                $(this).removeClass('inactive').addClass('active');
                $('#tableViewBtn').removeClass('active').addClass('inactive');
            });

            // Table search functionality
            $('#searchTable').on('input', function () {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#tableView tbody tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    if (row.querySelector('td[colspan]')) return;

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
                            <td colspan="7" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-20 h-20 bg-gray-100 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-search text-3xl text-gray-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-700 mb-1">No matching devices</h3>
                                        <p class="text-sm text-gray-500">Try different search terms</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', noResultsHTML);
                } else if (visibleCount > 0 && noResultsRow) {
                    noResultsRow.remove();
                }
            });

            // Adjust table layout on resize
            function adjustTableLayout() {
                const container = document.querySelector('.table-container');
                if (window.innerWidth < 768) {
                    container.style.overflowX = 'auto';
                    container.classList.remove('no-scrollbar');
                } else {
                    container.style.overflowX = 'auto';
                    container.classList.add('no-scrollbar');
                }
            }

            adjustTableLayout();
            window.addEventListener('resize', adjustTableLayout);
        });

        // ==================== FILTER FUNCTIONS ====================
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
            const status = document.getElementById('filterStatus').value;
            const department = document.getElementById('filterDepartment').value;
            const brand = document.getElementById('filterBrand').value;
            const category = document.getElementById('filterCategory').value;
            const condition = document.getElementById('filterCondition').value;

            const params = new URLSearchParams();
            if (status) params.append('status', status);
            if (department) params.append('department', department);
            if (brand) params.append('brand', brand);
            if (category) params.append('category', category);
            if (condition) params.append('condition', condition);

            window.location.href = `export_unassigned.php?${params.toString()}`;
        }

        // ==================== DEVICE FUNCTIONS ====================
        function redirectToInventory(deviceId) {
            window.location.href = `inventory.php?device_id=${deviceId}&highlight=true`;
        }

        function viewDeviceDetails(device) {
            document.getElementById('deviceTitle').textContent =
                `${device.brand_name || 'Unknown'} ${device.model || ''} - ${device.asset_tag || 'N/A'}`;

            const detailsHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-info-circle text-primary"></i>
                            Device Information
                        </h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Asset Tag:</span>
                                <span class="font-mono font-medium bg-primary-50 px-2 py-1 rounded">${escapeHtml(device.asset_tag || 'N/A')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Device Type:</span>
                                <span class="font-medium">${escapeHtml(device.device_type || 'N/A')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Category:</span>
                                <span class="font-medium">${escapeHtml(device.category_name || 'N/A')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Serial Number:</span>
                                <span class="font-mono font-medium">${escapeHtml(device.serial_number || 'N/A')}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-cog text-primary"></i>
                            Status & Condition
                        </h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Condition:</span>
                                <span class="condition-badge ${getConditionClass(device.condition || '')}">
                                    ${escapeHtml(getConditionLabel(device.condition || ''))}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="status-badge ${getStatusClass(device.status || '')}">
                                    ${escapeHtml(getStatusLabel(device.status || ''))}
                                </span>
                            </div>
                            ${device.assigned_user_id ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Assigned User ID:</span>
                                    <span class="font-medium">${device.assigned_user_id}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Assigned Since:</span>
                                    <span class="font-medium">${escapeHtml(device.assigned_at ? new Date(device.assigned_at).toLocaleDateString() : 'N/A')}</span>
                                </div>
                            ` : ''}
                            <div class="flex justify-between">
                                <span class="text-gray-600">Last Updated:</span>
                                <span class="font-medium">${escapeHtml(device.updated_at ? new Date(device.updated_at).toLocaleDateString() : 'N/A')}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 md:col-span-2">
                        <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-microchip text-primary"></i>
                            Specifications
                        </h3>
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <p class="text-gray-700 whitespace-pre-line text-sm">${escapeHtml(device.specifications || 'No specifications provided.')}</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-tag text-primary"></i>
                            Device Details
                        </h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Brand:</span>
                                <span class="font-medium">${escapeHtml(device.brand_name || 'N/A')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Model:</span>
                                <span class="font-medium">${escapeHtml(device.model || 'N/A')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Department:</span>
                                <span class="font-medium">${escapeHtml(device.department_name || 'N/A')}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-sticky-note text-primary"></i>
                        Remarks
                    </h3>
                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                        <p class="text-gray-700 whitespace-pre-line text-sm">${escapeHtml(device.remarks || 'No remarks available.')}</p>
                    </div>
                </div>
            `;

            document.getElementById('deviceDetails').innerHTML = detailsHTML;
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // Helper functions for status/condition classes
        function getStatusClass(status) {
            const statusMap = {
                'active': 'bg-green-50 text-green-700 border-green-200',
                'in_use': 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'in_storage': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                'repairing': 'bg-gray-50 text-gray-700 border-gray-200',
                'faulty': 'bg-pink-50 text-pink-700 border-pink-200',
                'retired': 'bg-red-50 text-red-700 border-red-200'
            };
            return statusMap[status.toLowerCase()] || 'bg-gray-50 text-gray-700 border-gray-200';
        }

        function getStatusLabel(status) {
            const labelMap = {
                'active': 'Active',
                'in_use': 'In Use',
                'in_storage': 'Store',
                'repairing': 'Repairing',
                'faulty': 'Faulty',
                'retired': 'Retired'
            };
            return labelMap[status.toLowerCase()] || status;
        }

        function getConditionClass(condition) {
            const conditionMap = {
                'new': 'bg-blue-50 text-blue-700 border-blue-200',
                'good': 'bg-green-50 text-green-700 border-green-200',
                'fair': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                'poor': 'bg-orange-50 text-orange-700 border-orange-200',
                'faulty': 'bg-red-50 text-red-700 border-red-200'
            };
            return conditionMap[condition.toLowerCase()] || 'bg-gray-50 text-gray-700 border-gray-200';
        }

        function getConditionLabel(condition) {
            return condition.charAt(0).toUpperCase() + condition.slice(1).toLowerCase();
        }

        // Utility function for escaping HTML
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('viewModal');
                if (!modal.classList.contains('hidden')) {
                    closeViewModal();
                }
            }
        });
    </script>
</body>

</html>