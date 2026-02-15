<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

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

    $action = $_POST['action'] ?? '';

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

        $stmt->close();
        $check_stmt->close();

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

        $stmt->close();
        $check_stmt->close();

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

    /* Fetch Departments and Categories for Filters */
    $departmentsArr = [];
    $deptResult = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name");
    if ($deptResult) {
        while ($row = $deptResult->fetch_assoc()) {
            $departmentsArr[] = $row;
        }
    } else {
        error_log("Departments query failed: " . $conn->error);
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

    // Get brands for filter
    $brandsArr = [];
    $brandResult = $conn->query("SELECT id, brand_name FROM brands ORDER BY brand_name");
    if ($brandResult) {
        while ($row = $brandResult->fetch_assoc()) {
            $brandsArr[] = $row;
        }
    }

    /* Get retired devices count */
    $retiredCount = 0;
    $retiredCountQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired'";
    $retiredCountResult = $conn->query($retiredCountQuery);
    if ($retiredCountResult) {
        $retiredCount = $retiredCountResult->fetch_assoc()['count'];
    }

    /* Get statistics */
    $assignedCount = 0;
    $assignedQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired' AND assigned_user IS NOT NULL";
    $assignedResult = $conn->query($assignedQuery);
    if ($assignedResult) {
        $assignedCount = $assignedResult->fetch_assoc()['count'];
    }

    $goodCount = 0;
    $goodQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired' AND `condition` = 'good'";
    $goodResult = $conn->query($goodQuery);
    if ($goodResult) {
        $goodCount = $goodResult->fetch_assoc()['count'];
    }

    $faultyCount = 0;
    $faultyQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired' AND `condition` = 'faulty'";
    $faultyResult = $conn->query($faultyQuery);
    if ($faultyResult) {
        $faultyCount = $faultyResult->fetch_assoc()['count'];
    }

    /* Get retired devices with pagination */
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 15;
    $offset = ($page - 1) * $perPage;

    // Build WHERE clause for filters
    $whereConditions = ["i.status = 'retired'"];
    $filterParams = [];
    $filterParamTypes = "";

    if (!empty($_GET['department'])) {
        $whereConditions[] = "d.department_name = ?";
        $filterParams[] = $_GET['department'];
        $filterParamTypes .= "s";
    }

    if (!empty($_GET['brand'])) {
        $whereConditions[] = "i.brand_id = ?";
        $filterParams[] = intval($_GET['brand']);
        $filterParamTypes .= "i";
    }

    if (!empty($_GET['category'])) {
        $whereConditions[] = "i.category_id = ?";
        $filterParams[] = intval($_GET['category']);
        $filterParamTypes .= "i";
    }

    if (!empty($_GET['condition'])) {
        $whereConditions[] = "i.`condition` = ?";
        $filterParams[] = $_GET['condition'];
        $filterParamTypes .= "s";
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    /* Get total count with filters */
    $totalRetired = 0;
    $totalPages = 0;
    $countQuery = "SELECT COUNT(*) as total FROM inventory_items i 
                   LEFT JOIN departments d ON i.department_id = d.id
                   $whereClause";

    if (!empty($filterParams)) {
        $countStmt = $conn->prepare($countQuery);
        if ($countStmt) {
            $countStmt->bind_param($filterParamTypes, ...$filterParams);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            if ($countResult) {
                $totalRetired = $countResult->fetch_assoc()['total'];
            }
            $countStmt->close();
        }
    } else {
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $totalRetired = $countResult->fetch_assoc()['total'];
        }
    }

    $totalPages = $totalRetired > 0 ? ceil($totalRetired / $perPage) : 1;

    /* Get retired devices */
    $retiredDevices = [];
    $query = " 
        SELECT 
            i.*,
            b.brand_name AS brand_name,
            d.department_name AS department_name,
            c.category_name AS category_name,
            u.firstname AS assigned_firstname,
            u.lastname AS assigned_lastname,
            u.email AS assigned_email
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN departments d ON i.department_id = d.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN users u ON i.assigned_user = u.id
        $whereClause
        ORDER BY i.updated_at DESC
        LIMIT ? OFFSET ?
    ";

    // Combine filter params with pagination params
    $queryParams = $filterParams;
    $queryParamTypes = $filterParamTypes;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    $queryParamTypes .= "ii";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($queryParams)) {
            $stmt->bind_param($queryParamTypes, ...$queryParams);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $retiredDevices[] = $row;
            }
        }
        $stmt->close();
    }

    /* ================== FILTER INPUTS ================== */
    $filterDepartment = $_GET['department'] ?? '';
    $filterBrand = $_GET['brand'] ?? '';
    $filterCategory = $_GET['category'] ?? '';
    $filterCondition = $_GET['condition'] ?? '';

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
        'faulty' => 'bg-pink-100 text-pink-700 border-pink-200'
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
        exportToCSV($whereClause, $filterParams, $filterParamTypes);
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

            .compact-column-xs {
                max-width: 90px;
                min-width: 90px;
            }

            .actions-column {
                max-width: 120px;
                min-width: 120px;
                white-space: nowrap;
            }

            /* Hide scrollbar for Chrome, Safari and Opera */
            .no-scrollbar::-webkit-scrollbar {
                display: none;
            }

            /* Hide scrollbar for IE, Edge and Firefox */
            .no-scrollbar {
                -ms-overflow-style: none;
                /* IE and Edge */
                scrollbar-width: none;
                /* Firefox */
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
                width: 32px;
                height: 32px;
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

            .user-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.75rem;
                font-weight: 600;
                color: white;
                background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            }

            .retired-overlay {
                position: absolute;
                inset: 0;
                background: linear-gradient(45deg, rgba(59, 130, 246, 0.05), rgba(37, 99, 235, 0.05));
                pointer-events: none;
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
                pointer-events: none;
            }

            .toast {
                pointer-events: auto;
                animation: slideInRight 0.3s ease-out forwards;
                border-radius: 0.75rem;
                padding: 1rem;
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                position: relative;
                overflow: hidden;
                transition: transform 0.3s ease, opacity 0.3s ease;
            }

            .toast.hiding {
                animation: slideOutRight 0.3s ease-out forwards;
            }

            .toast-success {
                background: linear-gradient(135deg, #10b981, #059669);
                color: white;
                border-left: 4px solid #047857;
            }

            .toast-error {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                border-left: 4px solid #b91c1c;
            }

            .toast-warning {
                background: linear-gradient(135deg, #f59e0b, #d97706);
                color: white;
                border-left: 4px solid #b45309;
            }

            .toast-info {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
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
                line-height: 1.4;
            }

            .toast-title {
                font-weight: 600;
                margin-bottom: 0.25rem;
            }

            .toast-message {
                opacity: 0.9;
            }

            .toast-close {
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.7);
                cursor: pointer;
                font-size: 1rem;
                padding: 0;
                margin-left: 0.5rem;
                transition: color 0.2s;
                flex-shrink: 0;
            }

            .toast-close:hover {
                color: white;
            }

            .toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: rgba(255, 255, 255, 0.3);
                width: 100%;
                transform-origin: left;
            }

            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }

                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }

                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }

            @keyframes progressBar {
                from {
                    transform: scaleX(1);
                }

                to {
                    transform: scaleX(0);
                }
            }

            /* Confirmation Modal Styles */
            .confirmation-modal {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                padding: 1rem;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }

            .confirmation-modal.active {
                opacity: 1;
                pointer-events: all;
            }

            .confirmation-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
            }

            .confirmation-content {
                position: relative;
                background: white;
                border-radius: 1rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                width: 100%;
                max-width: 400px;
                animation: modalScaleIn 0.3s ease-out;
                overflow: hidden;
            }

            @keyframes modalScaleIn {
                from {
                    transform: scale(0.9);
                    opacity: 0;
                }

                to {
                    transform: scale(1);
                    opacity: 1;
                }
            }

            .confirmation-header {
                padding: 1.5rem 1.5rem 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .confirmation-icon {
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 0.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                font-size: 1.25rem;
            }

            .confirmation-title {
                font-size: 1.125rem;
                font-weight: 600;
                color: #1f2937;
            }

            .confirmation-body {
                padding: 0 1.5rem 1.5rem;
            }

            .confirmation-message {
                color: #6b7280;
                font-size: 0.875rem;
                line-height: 1.5;
                margin-bottom: 1.25rem;
            }

            .confirmation-footer {
                display: flex;
                gap: 0.75rem;
                padding: 1rem 1.5rem;
                background: #f9fafb;
                border-top: 1px solid #e5e7eb;
            }

            .confirmation-btn {
                flex: 1;
                padding: 0.625rem 1rem;
                border-radius: 0.5rem;
                font-weight: 500;
                font-size: 0.875rem;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .confirmation-btn-cancel {
                background: white;
                color: #4b5563;
                border: 1px solid #d1d5db;
            }

            .confirmation-btn-cancel:hover {
                background: #f9fafb;
                border-color: #9ca3af;
            }

            .confirmation-btn-confirm {
                background: #dc2626;
                color: white;
                border: 1px solid #dc2626;
            }

            .confirmation-btn-confirm:hover {
                background: #b91c1c;
                border-color: #b91c1c;
            }

            .confirmation-btn-success {
                background: #10b981;
                color: white;
                border: 1px solid #10b981;
            }

            .confirmation-btn-success:hover {
                background: #059669;
                border-color: #059669;
            }
        </style>
    </head>

    <body class="bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-50 min-h-screen">
        <!-- Toast Container -->
        <div id="toast-container"></div>

        <!-- Confirmation Modal -->
        <div id="confirmationModal" class="confirmation-modal">
            <div class="confirmation-backdrop" onclick="closeConfirmationModal()"></div>
            <div class="confirmation-content">
                <div class="confirmation-header">
                    <div class="confirmation-icon" id="confirmationIcon"></div>
                    <h3 class="confirmation-title" id="confirmationTitle"></h3>
                </div>
                <div class="confirmation-body">
                    <p class="confirmation-message" id="confirmationMessage"></p>
                </div>
                <div class="confirmation-footer">
                    <button type="button" onclick="closeConfirmationModal()"
                        class="confirmation-btn confirmation-btn-cancel">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="button" onclick="performConfirmedAction()" class="confirmation-btn"
                        id="confirmationButton">
                        <i class="fas fa-check"></i>
                        Confirm
                    </button>
                </div>
            </div>
        </div>

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
                            class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                            Retired Devices
                        </h1>
                        <p class="text-gray-600 text-sm mt-2 flex items-center gap-2">
                            <i class="fas fa-recycle text-blue-500"></i>
                            View devices that have been retired from service
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl shadow-lg flex items-center gap-2">
                            <i class="fas fa-history"></i>
                            <span class="font-semibold text-sm">RETIRED</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Connection Error -->
            <?php if (!$conn): ?>
                <div class="glass-effect rounded-2xl shadow-lg p-6 mb-6 border border-red-200 bg-red-50">
                    <h3 class="font-bold text-lg mb-2 text-red-800">Database Connection Error</h3>
                    <p class="text-red-700">Unable to connect to the database. Please check your database configuration in
                        <code class="bg-red-100 px-2 py-1 rounded">config/database.php</code>.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg">
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
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($assignedCount) ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($goodCount) ?></p>
                        </div>
                    </div>
                </div>

                <div class="glass-effect rounded-2xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-pink-500 to-pink-600 flex items-center justify-center shadow-lg">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Faulty Condition</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($faultyCount) ?></p>
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
                            Filter Retired Devices
                        </h3>
                        <button type="button" onclick="clearFilters()"
                            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                            <i class="fas fa-times-circle"></i> Clear Filters
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        <button type="button" onclick="exportRetired()"
                            class="px-6 py-2.5 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-700 rounded-xl hover:from-green-100 hover:to-emerald-100 hover:border-green-300 transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow font-medium">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </form>

            <!-- View Toggle and Info -->
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
                    <button id="tableViewBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2">
                        <i class="fas fa-table"></i> Table View
                    </button>
                    <button id="cardViewBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg flex items-center gap-2">
                        <i class="fas fa-th-large"></i> Card View
                    </button>
                </div>
            </div>

            <!-- Table View -->
            <div id="tableView"
                class="glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100 mb-8 relative">
                <div class="retired-overlay"></div>
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
                                <th class="compact-column-xs">Asset Tag</th>
                                <th class="compact-column-sm">Department</th>
                                <th class="compact-column-xs">Assigned To</th>
                                <th class="compact-column-xs">Condition</th>
                                <th class="compact-column-xs">Status</th>
                                <th class="compact-column-xs">Last Updated</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($retiredDevices)): ?>
                                <tr>
                                    <td colspan="8" class="py-12 text-center">
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
                                    <tr>
                                        <!-- Device Info -->
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="device-icon bg-gradient-to-br from-gray-100 to-gray-200">
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

                                        <!-- Assigned To -->
                                        <td>
                                            <?php if ($isAssigned && !empty($device['assigned_firstname'])): ?>
                                                <div class="flex items-center gap-2">
                                                    <div class="user-avatar"
                                                        title="<?= htmlspecialchars($device['assigned_firstname'] . ' ' . $device['assigned_lastname']) ?>">
                                                        <?= strtoupper(substr($device['assigned_firstname'], 0, 1)) . strtoupper(substr($device['assigned_lastname'], 0, 1)) ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-gray-700 text-xs font-medium text-ellipsis"
                                                            title="<?= htmlspecialchars($device['assigned_firstname'] . ' ' . $device['assigned_lastname']) ?>">
                                                            <?= htmlspecialchars($device['assigned_firstname'] . ' ' . $device['assigned_lastname']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs italic">Unassigned</span>
                                            <?php endif; ?>
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
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <div class="flex gap-1">
                                                <button onclick='viewDeviceDetails(<?= json_encode($device, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                    class="action-btn bg-blue-500 text-white hover:bg-blue-600"
                                                    title="View Details">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>
                                                <button
                                                    onclick="showRestoreConfirm(<?= $device['id'] ?>, '<?= htmlspecialchars($device['asset_tag'], ENT_QUOTES) ?>')"
                                                    class="action-btn bg-green-500 text-white hover:bg-green-600"
                                                    title="Restore Device">
                                                    <i class="fas fa-undo text-xs"></i>
                                                </button>
                                                <button
                                                    onclick="showDeleteConfirm(<?= $device['id'] ?>, '<?= htmlspecialchars($device['asset_tag'], ENT_QUOTES) ?>')"
                                                    class="action-btn bg-red-500 text-white hover:bg-red-600"
                                                    title="Delete Permanently">
                                                    <i class="fas fa-trash text-xs"></i>
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
                                    <a href="?page=<?= $page - 1 ?>&department=<?= urlencode($filterDepartment) ?>&brand=<?= urlencode($filterBrand) ?>&category=<?= urlencode($filterCategory) ?>&condition=<?= urlencode($filterCondition) ?>"
                                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-2">
                                        <i class="fas fa-chevron-left"></i> Previous
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
                                    <a href="?page=<?= $i ?>&department=<?= urlencode($filterDepartment) ?>&brand=<?= urlencode($filterBrand) ?>&category=<?= urlencode($filterCategory) ?>&condition=<?= urlencode($filterCondition) ?>"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-all <?= $activeClass ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&department=<?= urlencode($filterDepartment) ?>&brand=<?= urlencode($filterBrand) ?>&category=<?= urlencode($filterCategory) ?>&condition=<?= urlencode($filterCondition) ?>"
                                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-all flex items-center gap-2">
                                        Next <i class="fas fa-chevron-right"></i>
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
                        $statusDisplay = getStatusDisplay($device['status'] ?? '', $statusColors, $statusLabels);
                        $conditionDisplay = getConditionDisplay($device['condition'] ?? '', $conditionColors, $conditionLabels);

                        // Check if device is assigned
                        $isAssigned = !empty($device['assigned_user']);
                        ?>
                        <div class="device-card glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                            <!-- Card Header -->
                            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
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
                                    <span class="asset-tag-badge">RETIRED</span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-6">
                                <!-- Asset Tag -->
                                <div class="mb-4">
                                    <span class="asset-tag-badge text-lg">
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
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center gap-2 text-sm">
                                        <i class="fas fa-building text-gray-400"></i>
                                        <span
                                            class="text-gray-700"><?= htmlspecialchars($device['department_name'] ?? 'N/A') ?></span>
                                    </div>
                                </div>

                                <!-- Assigned User -->
                                <?php if ($isAssigned && !empty($device['assigned_firstname'])): ?>
                                    <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <p class="text-xs text-gray-500 mb-1">Assigned To:</p>
                                        <div class="flex items-center gap-2">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($device['assigned_firstname'], 0, 1)) . strtoupper(substr($device['assigned_lastname'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-700">
                                                    <?= htmlspecialchars($device['assigned_firstname'] . ' ' . $device['assigned_lastname']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Last Updated -->
                                <div class="text-xs text-gray-500 mb-6">
                                    <i class="fas fa-clock mr-1"></i>
                                    Last updated: <?= date('M j, Y', strtotime($device['updated_at'] ?? 'now')) ?>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-2">
                                    <button onclick='viewDeviceDetails(<?= json_encode($device, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        class="flex-1 px-3 py-2 bg-blue-500 text-white rounded-lg text-xs font-medium hover:bg-blue-600 transition-all flex items-center justify-center gap-2"
                                        title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button
                                        onclick="showRestoreConfirm(<?= $device['id'] ?>, '<?= htmlspecialchars($device['asset_tag'], ENT_QUOTES) ?>')"
                                        class="flex-1 px-3 py-2 bg-green-500 text-white rounded-lg text-xs font-medium hover:bg-green-600 transition-all flex items-center justify-center gap-2"
                                        title="Restore Device">
                                        <i class="fas fa-undo"></i> Restore
                                    </button>
                                    <button
                                        onclick="showDeleteConfirm(<?= $device['id'] ?>, '<?= htmlspecialchars($device['asset_tag'], ENT_QUOTES) ?>')"
                                        class="flex-1 px-3 py-2 bg-red-500 text-white rounded-lg text-xs font-medium hover:bg-red-600 transition-all flex items-center justify-center gap-2"
                                        title="Delete">
                                        <i class="fas fa-trash"></i> Delete
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

        <?php include __DIR__ . '/footer.php'; ?>
        
        <!-- JavaScript Libraries -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
            // Toast Notification System
            class ToastNotification {
                static show(message, type = 'success', duration = 5000) {
                    const container = document.getElementById('toast-container');

                    // Create toast element
                    const toast = document.createElement('div');
                    toast.className = `toast toast-${type}`;

                    // Icons for different toast types
                    const icons = {
                        success: 'check-circle',
                        error: 'exclamation-circle',
                        warning: 'exclamation-triangle',
                        info: 'info-circle'
                    };

                    const titles = {
                        success: 'Success',
                        error: 'Error',
                        warning: 'Warning',
                        info: 'Info'
                    };

                    // Create progress bar
                    const progressBar = document.createElement('div');
                    progressBar.className = 'toast-progress';
                    progressBar.style.animation = `progressBar ${duration}ms linear forwards`;

                    // Create toast content
                    toast.innerHTML = `
                        <div class="toast-icon">
                            <i class="fas fa-${icons[type]}"></i>
                        </div>
                        <div class="toast-content">
                            <div class="toast-title">${titles[type]}</div>
                            <div class="toast-message">${message}</div>
                        </div>
                        <button class="toast-close" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    `;

                    toast.appendChild(progressBar);
                    container.appendChild(toast);

                    // Auto remove after duration
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.classList.add('hiding');
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, duration);

                    return toast;
                }
            }

            // Confirmation Modal System
            let currentAction = '';
            let currentDeviceId = 0;
            let currentDeviceTag = '';

            function showConfirmationModal(title, message, type = 'warning', confirmText = 'Confirm', confirmClass = 'confirmation-btn-confirm') {
                const modal = document.getElementById('confirmationModal');
                const icon = document.getElementById('confirmationIcon');
                const titleEl = document.getElementById('confirmationTitle');
                const messageEl = document.getElementById('confirmationMessage');
                const button = document.getElementById('confirmationButton');

                // Set modal content based on type
                const icons = {
                    warning: '<i class="fas fa-exclamation-triangle text-yellow-600"></i>',
                    danger: '<i class="fas fa-trash text-red-600"></i>',
                    success: '<i class="fas fa-undo text-green-600"></i>',
                    info: '<i class="fas fa-info-circle text-blue-600"></i>'
                };

                const iconClasses = {
                    warning: 'bg-yellow-50 border border-yellow-200',
                    danger: 'bg-red-50 border border-red-200',
                    success: 'bg-green-50 border border-green-200',
                    info: 'bg-blue-50 border border-blue-200'
                };

                icon.className = `confirmation-icon ${iconClasses[type]}`;
                icon.innerHTML = icons[type];
                titleEl.textContent = title;
                messageEl.textContent = message;
                button.innerHTML = `<i class="fas fa-check"></i> ${confirmText}`;
                button.className = `confirmation-btn ${confirmClass}`;

                modal.classList.add('active');
            }

            function closeConfirmationModal() {
                const modal = document.getElementById('confirmationModal');
                modal.classList.remove('active');
                currentAction = '';
                currentDeviceId = 0;
                currentDeviceTag = '';
            }

            function showDeleteConfirm(deviceId, assetTag) {
                currentAction = 'delete';
                currentDeviceId = deviceId;
                currentDeviceTag = assetTag;

                showConfirmationModal(
                    'Delete Device Permanently',
                    `Are you sure you want to delete device "${assetTag}"? This action cannot be undone and will permanently remove the device from the database.`,
                    'danger',
                    'Delete Permanently',
                    'confirmation-btn-confirm'
                );
            }

            function showRestoreConfirm(deviceId, assetTag) {
                currentAction = 'restore';
                currentDeviceId = deviceId;
                currentDeviceTag = assetTag;

                showConfirmationModal(
                    'Restore Device',
                    `Are you sure you want to restore device "${assetTag}" to active service? The device will be set to "in_storage" status and will be available for assignment.`,
                    'success',
                    'Restore Device',
                    'confirmation-btn-success'
                );
            }

            function performConfirmedAction() {
                if (currentAction === 'delete') {
                    deleteDevice(currentDeviceId);
                } else if (currentAction === 'restore') {
                    restoreDeviceAction(currentDeviceId);
                }
                closeConfirmationModal();
            }

            function deleteDevice(deviceId) {
                fetch('retired_devices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_device&device_id=${deviceId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            ToastNotification.show('Device deleted successfully!', 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            ToastNotification.show('Error: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        ToastNotification.show('An error occurred. Please try again.', 'error');
                    });
            }

            function restoreDeviceAction(deviceId) {
                fetch('retired_devices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=restore_device&device_id=${deviceId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            ToastNotification.show('Device restored successfully!', 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            ToastNotification.show('Error: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        ToastNotification.show('An error occurred. Please try again.', 'error');
                    });
            }

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
                        <td colspan="8" class="py-12 text-center">
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
                document.getElementById('filterDepartment').value = '';
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
                const brand = document.getElementById('filterBrand').value;
                const category = document.getElementById('filterCategory').value;
                const condition = document.getElementById('filterCondition').value;

                // Build query string
                const params = new URLSearchParams();
                if (department) params.append('department', department);
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
                document.getElementById('deviceTitle').textContent = `${device.brand_name || 'Unknown'} ${device.model || ''}`;

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
                            <p><span class="font-medium">Brand:</span> ${escapeHtml(device.brand_name || 'N/A')}</p>
                            <p><span class="font-medium">Model:</span> ${escapeHtml(device.model || 'N/A')}</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-2">Device Status</h3>
                        <div class="space-y-2">
                            <p><span class="font-medium">Condition:</span> ${escapeHtml(device.condition || 'N/A')}</p>
                            <p><span class="font-medium">Status:</span> <span class="text-blue-600 font-semibold">${escapeHtml(device.status || 'N/A')}</span></p>
                            <p><span class="font-medium">Department:</span> ${escapeHtml(device.department_name || 'N/A')}</p>
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
                
                <div class="mt-6 bg-blue-50 p-4 rounded-xl border border-blue-200">
                    <h3 class="font-semibold text-blue-800 mb-2">Retirement Information</h3>
                    <p class="text-blue-700 text-sm">
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

function exportToCSV($whereClause, $filterParams, $filterParamTypes)
{
    global $conn;

    if (!$conn) {
        die("Database connection failed");
    }

    // Get retired devices for export (without pagination)
    $query = " 
        SELECT 
            i.asset_tag,
            i.device_type,
            i.model,
            b.brand_name,
            c.category_name,
            d.department_name,
            i.serial_number,
            i.`condition`,
            i.status,
            i.remarks,
            i.created_at,
            i.updated_at,
            CONCAT(u.firstname, ' ', u.lastname) as assigned_to,
            u.email as assigned_email
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN departments d ON i.department_id = d.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN users u ON i.assigned_user = u.id
        $whereClause
        ORDER BY i.updated_at DESC
    ";

    if (!empty($filterParams)) {
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($filterParamTypes, ...$filterParams);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            die("Query preparation failed: " . $conn->error);
        }
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
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    exit();
}