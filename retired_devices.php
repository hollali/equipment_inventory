<?php
// retired_devices.php - Complete single file solution
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// First, check if database config exists and load it
$config_file = __DIR__ . "/config/database.php";
if (!file_exists($config_file)) {
    die("Database configuration file not found: " . $config_file);
}

require_once $config_file;

// Initialize database connection
$db = null;
$conn = null;
try {
    $db = new Database();
    $conn = $db->getConnection();

    // Test the connection
    if (!$conn) {
        throw new Exception("Database connection is null");
    }

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Check if this is an AJAX request for processing actions
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleAjaxRequest();
    exit();
}

// Main page logic
displayRetiredDevicesPage();

// ================ FUNCTIONS ================

function handleAjaxRequest()
{
    global $conn;

    header('Content-Type: application/json');

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'restore_device':
            restoreDevice();
            break;
        case 'delete_device':
            deleteDevice();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit();
    }
}

function restoreDevice()
{
    global $conn;

    error_log("Restore device called");

    $device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;

    error_log("Device ID: " . $device_id);

    if ($device_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing device ID']);
        exit();
    }

    try {
        // Debug: Check if device exists and is retired
        $check_query = "SELECT id, asset_tag, status FROM inventory_items WHERE id = ?";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $check_stmt->bind_param("i", $device_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Device not found']);
            exit();
        }

        $device = $check_result->fetch_assoc();
        error_log("Device found: " . print_r($device, true));

        // Update device status to 'in_storage'
        $update_query = "UPDATE inventory_items 
                         SET status = 'in_storage', 
                             updated_at = NOW()
                         WHERE id = ?";

        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $device_id);

        if ($stmt->execute()) {
            error_log("Device restored successfully");
            echo json_encode([
                'success' => true,
                'message' => 'Device restored successfully!'
            ]);
        } else {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $stmt->error
            ]);
        }

    } catch (Exception $e) {
        error_log("Exception in restoreDevice: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

function deleteDevice()
{
    global $conn;

    error_log("Delete device called");

    $device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;

    if ($device_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing device ID']);
        exit();
    }

    try {
        // First, check if device exists and is retired
        $check_query = "SELECT id, asset_tag FROM inventory_items WHERE id = ? AND status = 'retired'";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $check_stmt->bind_param("i", $device_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Device not found or not retired']);
            exit();
        }

        // Delete the device
        $delete_query = "DELETE FROM inventory_items WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $device_id);

        if ($stmt->execute()) {
            error_log("Device deleted successfully");
            echo json_encode([
                'success' => true,
                'message' => 'Device deleted permanently!'
            ]);
        } else {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $stmt->error
            ]);
        }

    } catch (Exception $e) {
        error_log("Exception in deleteDevice: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

function displayRetiredDevicesPage()
{
    global $conn;

    // Check connection again
    if (!$conn) {
        echo "<div class='p-8 text-center text-red-600'>Database connection failed. Please check your configuration.</div>";
        return;
    }

    /* Fetch Departments, Locations, and Categories for Filters */
    $departmentsArr = [];
    $deptResult = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name");
    if ($deptResult) {
        while ($row = $deptResult->fetch_assoc()) {
            $departmentsArr[] = $row;
        }
    } else {
        // Handle query error
        error_log("Departments query failed: " . $conn->error);
    }

    $locationsArr = [];
    $locResult = $conn->query("SELECT id, location_name FROM locations ORDER BY location_name");
    if ($locResult) {
        while ($row = $locResult->fetch_assoc()) {
            $locationsArr[] = ['id' => $row['id'], 'location_name' => $row['location_name']];
        }
    } else {
        error_log("Locations query failed: " . $conn->error);
    }

    // Get categories from categories table
    $categoriesArr = [];
    $catResult = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
    if ($catResult) {
        while ($row = $catResult->fetch_assoc()) {
            $categoriesArr[] = $row;
        }
    } else {
        error_log("Categories query failed: " . $conn->error);
    }

    /* Get retired devices count */
    $retiredCount = 0;
    $retiredCountQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired'";
    $retiredCountResult = $conn->query($retiredCountQuery);
    if ($retiredCountResult) {
        $retiredCount = $retiredCountResult->fetch_assoc()['count'];
    }

    /* Get retired devices with pagination */
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 15;
    $offset = ($page - 1) * $perPage;

    // Build WHERE clause for filters
    $whereConditions = ["i.status = 'retired'"];
    $params = [];
    $paramTypes = "";

    if (!empty($_GET['department'])) {
        $whereConditions[] = "d.department_name = ?";
        $params[] = $_GET['department'];
        $paramTypes .= "s";
    }

    if (!empty($_GET['location'])) {
        $whereConditions[] = "l.location_name = ?";
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
    $totalRetired = 0;
    $totalPages = 0;
    $countQuery = "SELECT COUNT(*) as total FROM inventory_items i 
                   LEFT JOIN departments d ON i.department_id = d.id
                   LEFT JOIN locations l ON i.location_id = l.id
                   $whereClause";

    if (!empty($params)) {
        $countStmt = $conn->prepare($countQuery);
        if ($countStmt) {
            $countStmt->bind_param($paramTypes, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            if ($countResult) {
                $totalRetired = $countResult->fetch_assoc()['total'];
                $totalPages = ceil($totalRetired / $perPage);
            }
        }
    } else {
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $totalRetired = $countResult->fetch_assoc()['total'];
            $totalPages = ceil($totalRetired / $perPage);
        }
    }

    /* Get retired devices */
    $retiredDevices = [];
    $query = " 
        SELECT 
            i.*,
            b.brand_name AS brand_name,
            d.department_name AS department_name,
            l.location_name AS location_name,
            c.category_name AS category_name,
            u.firstname AS assigned_firstname,
            u.lastname AS assigned_lastname,
            u.email AS assigned_email
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN departments d ON i.department_id = d.id
        LEFT JOIN locations l ON i.location_id = l.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN users u ON i.assigned_user = u.id
        $whereClause
        ORDER BY i.updated_at DESC
        LIMIT ? OFFSET ?
    ";

    // Add pagination parameters
    $params[] = $perPage;
    $params[] = $offset;
    $paramTypes .= "ii";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($paramTypes, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $retiredDevices[] = $row;
            }
        }
    }

    /* ================== FILTER INPUTS ================== */
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

    // Handle export request
    if (isset($_GET['export']) && $_GET['export'] == 'csv') {
        exportToCSV($whereClause, $params, $paramTypes);
        exit();
    }

    // Now display the HTML page
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Retired Devices - Equipment Inventory</title>
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

            /* Modal centering */
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

            .retired-tag {
                background: linear-gradient(45deg, #f87171, #dc2626);
                color: white;
                font-weight: bold;
            }

            .error-message {
                background-color: #fee;
                border: 1px solid #fcc;
                color: #c00;
                padding: 1rem;
                border-radius: 0.5rem;
                margin: 1rem 0;
            }
        </style>
    </head>

    <body class="bg-gradient-to-br from-gray-50 via-red-50 to-orange-50 min-h-screen">

        <!-- Sidebar -->
        <?php
        $sidebar_file = __DIR__ . "/sidebar.php";
        if (file_exists($sidebar_file)) {
            include $sidebar_file;
        } else {
            echo "<!-- Sidebar not found -->";
        }
        ?>

        <!-- Main Content -->
        <main id="mainContent" class="flex-1 p-4 md:p-8 ml-0 md:ml-64">

            <!-- Header -->
            <div class="mb-8 animate-fade-in-up">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div>
                        <h1
                            class="text-4xl font-bold bg-gradient-to-r from-red-600 to-orange-600 bg-clip-text text-transparent">
                            Retired Devices
                        </h1>
                        <p class="text-gray-600 text-sm mt-2 flex items-center gap-2">
                            <i class="fas fa-recycle text-red-500"></i>
                            View devices that have been retired from service
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="px-4 py-2 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-xl shadow-lg flex items-center gap-2">
                            <i class="fas fa-history"></i>
                            <span class="font-semibold text-sm">RETIRED</span>
                        </div>
                        <!--<a href="unassigned_devices.php"
                            class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i>
                            Back to Unassigned
                        </a>--->
                </div>
            </div>
        </div>

        <!-- Database Connection Error -->
            <?php if (!$conn): ?>
                <div class="error-message">
                    <h3 class="font-bold text-lg mb-2">Database Connection Error</h3>
                    <p>Unable to connect to the database. Please check your database configuration in
                        <code>config/database.php</code>.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-laptop text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Total Retired</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($retiredCount) ?></p>
                        </div>
                    </div>
                </div>

                <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-times text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Still Assigned</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php
                                $assignedCount = 0;
                                if ($conn) {
                                    $assignedQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired' AND assigned_user IS NOT NULL";
                                    $assignedResult = $conn->query($assignedQuery);
                                    if ($assignedResult) {
                                        $assignedCount = $assignedResult->fetch_assoc()['count'];
                                    }
                                }
                                echo number_format($assignedCount);
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
                            <p class="text-sm text-gray-500">Good Condition</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php
                                $goodCount = 0;
                                if ($conn) {
                                    $goodQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired' AND `condition` = 'good'";
                                    $goodResult = $conn->query($goodQuery);
                                    if ($goodResult) {
                                        $goodCount = $goodResult->fetch_assoc()['count'];
                                    }
                                }
                                echo number_format($goodCount);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Faulty Condition</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php
                                $faultyCount = 0;
                                if ($conn) {
                                    $faultyQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired' AND `condition` = 'faulty'";
                                    $faultyResult = $conn->query($faultyQuery);
                                    if ($faultyResult) {
                                        $faultyCount = $faultyResult->fetch_assoc()['count'];
                                    }
                                }
                                echo number_format($faultyCount);
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
                            <i class="fas fa-filter text-red-500"></i>
                            Filter Retired Devices
                        </h3>
                        <button type="button" onclick="clearFilters()" class="text-sm text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times-circle mr-1"></i>Clear Filters
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                            class="bg-red-600 text-white px-5 py-2.5 rounded-xl hover:bg-red-700 transition flex items-center gap-2">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button type="button" onclick="exportRetired()"
                            class="px-6 py-3 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-700 rounded-xl hover:from-green-100 hover:to-emerald-100 hover:border-green-300 transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow font-medium whitespace-nowrap">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </form>

            <!-- View Toggle Buttons -->
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Retired Devices List</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Showing <?= number_format(min($offset + 1, $totalRetired)) ?> -
                        <?= number_format(min($offset + $perPage, $totalRetired)) ?> of
                        <?= number_format($totalRetired) ?> devices
                    </p>
                </div>
                <div class="flex gap-2">
                    <button id="tableViewBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg">
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
                                class="pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent w-full md:w-64">
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
                                        <i class="fas fa-user text-gray-500"></i>
                                        Assigned To
                                    </div>
                                </th>
                                <th
                                    class="py-4 px-6 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-battery-half text-gray-500"></i>
                                        Status & Condition
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
                            <?php if (empty($retiredDevices)): ?>
                                <tr>
                                    <td colspan="7" class="py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
                                                <i class="fas fa-check-circle text-4xl text-green-400"></i>
                                            </div>
                                            <p class="text-gray-400 font-medium">No retired devices found!</p>
                                            <p class="text-xs text-gray-400">All devices are currently in service or in storage
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($retiredDevices as $device): ?>
                                    <?php
                                    // Get status and condition display
                                    $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                                    $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);

                                    // Check if device is assigned
                                    $isAssigned = !empty($device['assigned_user']);
                                    ?>
                                    <tr class="table-row-hover transition-all duration-200">
                                        <!-- Device Info -->
                                        <td class="table-cell">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                    <?php
                                                    $deviceType = strtolower($device['device_type'] ?? 'laptop');
                                                    if (strpos($deviceType, 'phone') !== false || strpos($deviceType, 'mobile') !== false): ?>
                                                        <i class="fas fa-mobile-alt text-gray-600"></i>
                                                    <?php elseif (strpos($deviceType, 'tablet') !== false): ?>
                                                        <i class="fas fa-tablet-alt text-gray-600"></i>
                                                    <?php elseif (strpos($deviceType, 'desktop') !== false || strpos($deviceType, 'pc') !== false): ?>
                                                        <i class="fas fa-desktop text-gray-600"></i>
                                                    <?php elseif (strpos($deviceType, 'printer') !== false): ?>
                                                        <i class="fas fa-print text-gray-600"></i>
                                                    <?php elseif (strpos($deviceType, 'server') !== false): ?>
                                                        <i class="fas fa-server text-gray-600"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-laptop text-gray-600"></i>
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
                                                            <?= htmlspecialchars($device['serial_number']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Asset Tag -->
                                        <td class="table-cell">
                                            <span class="asset-tag retired-tag px-3 py-1.5 rounded-lg">
                                                <?= htmlspecialchars($device['asset_tag'] ?? 'N/A') ?>
                                            </span>
                                        </td>

                                        <!-- Location -->
                                        <td class="table-cell">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-building text-gray-400 text-sm"></i>
                                                    <span
                                                        class="text-gray-700 font-medium"><?= htmlspecialchars($device['department_name'] ?? 'N/A') ?></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-location-dot text-gray-400 text-sm"></i>
                                                    <span
                                                        class="text-gray-600 text-sm"><?= htmlspecialchars($device['location_name'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Assigned To -->
                                        <td class="table-cell">
                                            <?php if ($isAssigned && !empty($device['assigned_firstname'])): ?>
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-2">
                                                        <i class="fas fa-user text-gray-400 text-sm"></i>
                                                        <span class="text-gray-700 font-medium">
                                                            <?= htmlspecialchars($device['assigned_firstname'] . ' ' . $device['assigned_lastname']) ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <i class="fas fa-envelope text-gray-400 text-sm"></i>
                                                        <span class="text-gray-600 text-sm">
                                                            <?= htmlspecialchars($device['assigned_email'] ?? '') ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm italic">Unassigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status & Condition -->
                                        <td class="table-cell">
                                            <div class="space-y-2">
                                                <span class="condition-badge <?= $conditionDisplay['color'] ?>">
                                                    <?= htmlspecialchars($conditionDisplay['label']) ?>
                                                </span>
                                                <br>
                                                <span class="status-badge <?= $statusDisplay['color'] ?>">
                                                    <i class="fas fa-skull-crossbones mr-1"></i>
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
                                                <button onclick="viewDeviceDetails(<?= htmlspecialchars(json_encode($device)) ?>)"
                                                    class="action-btn bg-blue-500 text-white hover:bg-blue-600"
                                                    title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="restoreDevice(<?= $device['id'] ?>)"
                                                    class="action-btn bg-green-500 text-white hover:bg-green-600"
                                                    title="Restore Device">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button
                                                    onclick="showDeleteConfirm(<?= $device['id'] ?>, '<?= htmlspecialchars($device['asset_tag']) ?>')"
                                                    class="action-btn bg-red-500 text-white hover:bg-red-600"
                                                    title="Delete Permanently">
                                                    <i class="fas fa-trash"></i>
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
                                    <a href="?page=<?= $page - 1 ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-2">
                                        <i class="fas fa-chevron-left mr-1"></i> Previous
                                    </a>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                for ($i = $startPage; $i <= $endPage; $i++):
                                    $activeClass = $i === $page
                                        ? 'bg-red-500 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50';
                                    ?>
                                    <a href="?page=<?= $i ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-all <?= $activeClass ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&department=<?= $filterDepartment ?>&location=<?= $filterLocation ?>&brand=<?= $filterBrand ?>&category=<?= $filterCategory ?>&condition=<?= $filterCondition ?>"
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
                <?php if (empty($retiredDevices)): ?>
                    <div class="col-span-3">
                        <div class="glass-effect rounded-2xl shadow-lg p-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-check-circle text-4xl text-green-400"></i>
                                </div>
                                <p class="text-gray-400 font-medium">No retired devices found!</p>
                                <p class="text-xs text-gray-400">All devices are currently in service or in storage</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($retiredDevices as $device): ?>
                        <?php
                        // Get status and condition display
                        $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                        $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);

                        // Check if device is assigned
                        $isAssigned = !empty($device['assigned_user']);
                        ?>
                        <div class="device-card glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                            <!-- Card Header -->
                            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-red-50 to-orange-50">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                            <?php
                                            $deviceType = strtolower($device['device_type'] ?? 'laptop');
                                            if (strpos($deviceType, 'phone') !== false || strpos($deviceType, 'mobile') !== false): ?>
                                                <i class="fas fa-mobile-alt text-gray-600 text-lg"></i>
                                            <?php elseif (strpos($deviceType, 'tablet') !== false): ?>
                                                <i class="fas fa-tablet-alt text-gray-600 text-lg"></i>
                                            <?php elseif (strpos($deviceType, 'desktop') !== false || strpos($deviceType, 'pc') !== false): ?>
                                                <i class="fas fa-desktop text-gray-600 text-lg"></i>
                                            <?php elseif (strpos($deviceType, 'printer') !== false): ?>
                                                <i class="fas fa-print text-gray-600 text-lg"></i>
                                            <?php elseif (strpos($deviceType, 'server') !== false): ?>
                                                <i class="fas fa-server text-gray-600 text-lg"></i>
                                            <?php else: ?>
                                                <i class="fas fa-laptop text-gray-600 text-lg"></i>
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
                                    <span class="retired-tag px-2 py-1 rounded text-xs">RETIRED</span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-6">
                                <!-- Asset Tag -->
                                <div class="mb-4">
                                    <span class="font-semibold text-red-600 bg-red-50 px-3 py-1.5 rounded-lg border border-red-100">
                                        <?= htmlspecialchars($device['asset_tag'] ?? 'N/A') ?>
                                    </span>
                                </div>

                                <!-- Condition & Status -->
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <span class="condition-badge <?= $conditionDisplay['color'] ?>">
                                        <?= htmlspecialchars($conditionDisplay['label']) ?>
                                    </span>
                                    <span class="status-badge <?= $statusDisplay['color'] ?>">
                                        <i class="fas fa-skull-crossbones mr-1"></i>
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
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-building text-gray-400"></i>
                                        <span
                                            class="text-gray-700"><?= htmlspecialchars($device['department_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-location-dot text-gray-400"></i>
                                        <span
                                            class="text-gray-700"><?= htmlspecialchars($device['location_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-tag text-gray-400"></i>
                                        <span
                                            class="text-gray-700"><?= htmlspecialchars($device['category_name'] ?? 'N/A') ?></span>
                                    </div>
                                </div>

                                <!-- Assigned User -->
                                <?php if ($isAssigned && !empty($device['assigned_firstname'])): ?>
                                    <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500 mb-1">Assigned To:</p>
                                        <p class="text-sm font-medium text-gray-700">
                                            <?= htmlspecialchars($device['assigned_firstname'] . ' ' . $device['assigned_lastname']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($device['assigned_email'] ?? '') ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Last Updated -->
                                <div class="text-xs text-gray-500 mb-6">
                                    <i class="fas fa-clock mr-1"></i>
                                    Last updated: <?= date('M j, Y', strtotime($device['updated_at'] ?? 'now')) ?>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-2">
                                    <button onclick="viewDeviceDetails(<?= htmlspecialchars(json_encode($device)) ?>)"
                                        class="flex-1 px-4 py-2.5 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-all flex items-center justify-center gap-2"
                                        title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button onclick="restoreDevice(<?= $device['id'] ?>)"
                                        class="flex-1 px-4 py-2.5 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600 transition-all flex items-center justify-center gap-2"
                                        title="Restore Device">
                                        <i class="fas fa-undo"></i> Restore
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

        <!-- Confirmation Modal -->
        <div id="confirmModal" class="modal-container hidden">
            <div class="modal-backdrop" onclick="closeConfirmModal()"></div>
            <div class="modal-content w-full max-w-md">
                <div class="sticky top-0 bg-white border-b border-gray-200 p-6">
                    <button onclick="closeConfirmModal()"
                        class="absolute top-6 right-6 text-gray-400 hover:text-gray-600 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900" id="confirmTitle"></h2>
                            <p class="text-gray-500 text-sm mt-1" id="confirmMessage"></p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div id="confirmContent"></div>
                    <div class="mt-6 flex gap-3">
                        <button onclick="closeConfirmModal()"
                            class="flex-1 px-4 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition-all">
                            Cancel
                        </button>
                        <button id="confirmActionBtn" onclick="performConfirmedAction()"
                            class="flex-1 px-4 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-all">
                            Confirm
                        </button>
                    </div>
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

                // View toggle functionality
                $('#tableViewBtn').click(function () {
                    $('#tableView').removeClass('hidden');
                    $('#cardView').addClass('hidden');
                    $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-red-600 text-white');
                    $('#cardViewBtn').removeClass('bg-red-600 text-white').addClass('bg-gray-200 text-gray-700');
                });

                $('#cardViewBtn').click(function () {
                    $('#cardView').removeClass('hidden');
                    $('#tableView').addClass('hidden');
                    $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-red-600 text-white');
                    $('#tableViewBtn').removeClass('bg-red-600 text-white').addClass('bg-gray-200 text-gray-700');
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

            function exportRetired() {
                // Get current filters
                const department = document.getElementById('filterDepartment').value;
                const location = document.getElementById('filterLocation').value;
                const brand = document.getElementById('filterBrand').value;
                const category = document.getElementById('filterCategory').value;
                const condition = document.getElementById('filterCondition').value;

                // Build query string
                const params = new URLSearchParams();
                if (department) params.append('department', department);
                if (location) params.append('location', location);
                if (brand) params.append('brand', brand);
                if (category) params.append('category', category);
                if (condition) params.append('condition', condition);
                params.append('export', 'csv');

                // Redirect with export parameter
                window.location.href = `retired_devices.php?${params.toString()}`;
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
                                <p><span class="font-medium">Asset Tag:</span> <span class="font-mono bg-red-50 px-2 py-1 rounded">${escapeHtml(device.asset_tag)}</span></p>
                                <p><span class="font-medium">Device Type:</span> ${escapeHtml(device.device_type || 'N/A')}</p>
                                <p><span class="font-medium">Category:</span> ${escapeHtml(device.category_name || 'N/A')}</p>
                                <p><span class="font-medium">Serial Number:</span> <span class="font-mono">${escapeHtml(device.serial_number || 'N/A')}</span></p>
                                <p><span class="font-medium">Brand:</span> ${escapeHtml(device.brand_name || 'N/A')}</p>
                                <p><span class="font-medium">Model:</span> ${escapeHtml(device.model || 'N/A')}</p>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <h3 class="font-semibold text-gray-800 mb-2">Device Status</h3>
                            <div class="space-y-2">
                                <p><span class="font-medium">Condition:</span> ${escapeHtml(device.condition || 'N/A')}</p>
                                <p><span class="font-medium">Status:</span> <span class="text-red-600 font-semibold">${escapeHtml(device.status || 'N/A')}</span></p>
                                <p><span class="font-medium">Department:</span> ${escapeHtml(device.department_name || 'N/A')}</p>
                                <p><span class="font-medium">Location:</span> ${escapeHtml(device.location_name || 'N/A')}</p>
                                <p><span class="font-medium">Created:</span> ${escapeHtml(device.created_at ? new Date(device.created_at).toLocaleDateString() : 'N/A')}</p>
                                <p><span class="font-medium">Last Updated:</span> ${escapeHtml(device.updated_at ? new Date(device.updated_at).toLocaleDateString() : 'N/A')}</p>
                            </div>
                        </div>
                        
                        ${device.assigned_firstname ? `
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <h3 class="font-semibold text-gray-800 mb-2">Assigned User</h3>
                            <div class="space-y-2">
                                <p><span class="font-medium">Name:</span> ${escapeHtml(device.assigned_firstname + ' ' + device.assigned_lastname)}</p>
                                <p><span class="font-medium">Email:</span> ${escapeHtml(device.assigned_email || 'N/A')}</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 ${device.assigned_firstname ? '' : 'md:col-span-2'}">
                            <h3 class="font-semibold text-gray-800 mb-2">Specifications</h3>
                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                <p class="text-gray-700 whitespace-pre-line">${escapeHtml(device.specifications || 'No specifications provided.')}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-2">Remarks</h3>
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <p class="text-gray-700 whitespace-pre-line">${escapeHtml(device.remarks || 'No remarks available.')}</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 bg-red-50 p-4 rounded-xl border border-red-200">
                        <h3 class="font-semibold text-red-800 mb-2">Retirement Information</h3>
                        <p class="text-red-700 text-sm">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            This device has been retired from service. It is no longer available for assignment.
                        </p>
                    </div>
                `;

                document.getElementById('deviceDetails').innerHTML = detailsHTML;
                document.getElementById('viewModal').classList.remove('hidden');
            }

            function closeViewModal() {
                document.getElementById('viewModal').classList.add('hidden');
            }

            // Confirmation Modal
            let currentAction = '';
            let currentDeviceId = 0;
            let currentDeviceTag = '';

            function showDeleteConfirm(deviceId, assetTag) {
                currentAction = 'delete';
                currentDeviceId = deviceId;
                currentDeviceTag = assetTag;

                document.getElementById('confirmTitle').textContent = 'Delete Device';
                document.getElementById('confirmMessage').textContent = 'This action cannot be undone';
                document.getElementById('confirmContent').innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl mt-1"></i>
                            <div>
                                <p class="text-red-800 font-medium">Are you sure you want to delete this device permanently?</p>
                                <p class="text-red-600 text-sm mt-2">
                                    Device: <span class="font-mono font-bold">${escapeHtml(assetTag)}</span><br>
                                    This will permanently remove the device from the database.
                                </p>
                            </div>
                        </div>
                    </div>
                `;

                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.textContent = 'Delete Permanently';
                confirmBtn.className = 'flex-1 px-4 py-3 bg-red-500 text-white rounded-xl font-semibold hover:bg-red-600 transition-all';

                document.getElementById('confirmModal').classList.remove('hidden');
            }

            function restoreDevice(deviceId) {
                currentAction = 'restore';
                currentDeviceId = deviceId;

                document.getElementById('confirmTitle').textContent = 'Restore Device';
                document.getElementById('confirmMessage').textContent = 'Return device to active service';
                document.getElementById('confirmContent').innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-question-circle text-green-500 text-xl mt-1"></i>
                            <div>
                                <p class="text-green-800 font-medium">Restore this device to active service?</p>
                                <p class="text-green-600 text-sm mt-2">
                                    The device will be set to "in_storage" status and will be available for assignment.
                                </p>
                            </div>
                        </div>
                    </div>
                `;

                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.textContent = 'Restore Device';
                confirmBtn.className = 'flex-1 px-4 py-3 bg-green-500 text-white rounded-xl font-semibold hover:bg-green-600 transition-all';

                document.getElementById('confirmModal').classList.remove('hidden');
            }

            function closeConfirmModal() {
                document.getElementById('confirmModal').classList.add('hidden');
                currentAction = '';
                currentDeviceId = 0;
                currentDeviceTag = '';
            }

            function performConfirmedAction() {
                if (currentAction === 'delete') {
                    deleteDevice(currentDeviceId);
                } else if (currentAction === 'restore') {
                    restoreDeviceAction(currentDeviceId);
                }
            }

            function deleteDevice(deviceId) {
                console.log('Deleting device:', deviceId);

                // Use AJAX to call the same file
                fetch('retired_devices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_device&device_id=${deviceId}`
                })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            alert('Device deleted successfully!');
                            closeConfirmModal();
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
            }

            function restoreDeviceAction(deviceId) {
                console.log('Restoring device:', deviceId);

                // Use AJAX to call the same file
                fetch('retired_devices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=restore_device&device_id=${deviceId}`
                })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            alert('Device restored successfully!');
                            closeConfirmModal();
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
            }

            // Utility function for escaping HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text || '';
                return div.innerHTML;
            }
        </script>

    </body>

    </html>
    <?php
}

function exportToCSV($whereClause, $params, $paramTypes)
{
    global $conn;

    if (!$conn) {
        die("Database connection failed");
    }

    // Get retired devices for export
    $query = " 
        SELECT 
            i.asset_tag,
            i.device_type,
            i.model,
            b.brand_name,
            c.category_name,
            d.department_name,
            l.location_name,
            i.serial_number,
            i.condition,
            i.status,
            i.remarks,
            i.created_at,
            i.updated_at,
            CONCAT(u.firstname, ' ', u.lastname) as assigned_to,
            u.email as assigned_email
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN departments d ON i.department_id = d.id
        LEFT JOIN locations l ON i.location_id = l.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN users u ON i.assigned_user = u.id
        $whereClause
        ORDER BY i.updated_at DESC
    ";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        // Remove pagination parameters for export
        $exportParams = array_slice($params, 0, count($params) - 2);
        $exportParamTypes = substr($paramTypes, 0, -2);
        if ($stmt) {
            $stmt->bind_param($exportParamTypes, ...$exportParams);
        }
    }

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    if (!$result) {
        die("Query failed: " . $conn->error);
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=retired_devices_' . date('Y-m-d') . '.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // Add CSV headers
    fputcsv($output, [
        'Asset Tag',
        'Device Type',
        'Model',
        'Brand',
        'Category',
        'Department',
        'Location',
        'Serial Number',
        'Condition',
        'Status',
        'Remarks',
        'Created At',
        'Last Updated',
        'Assigned To',
        'Assigned Email'
    ]);

    // Add data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['asset_tag'],
            $row['device_type'],
            $row['model'],
            $row['brand_name'],
            $row['category_name'],
            $row['department_name'],
            $row['location_name'],
            $row['serial_number'],
            $row['condition'],
            $row['status'],
            $row['remarks'],
            $row['created_at'],
            $row['updated_at'],
            $row['assigned_to'],
            $row['assigned_email']
        ]);
    }

    fclose($output);
    exit();
}
?>