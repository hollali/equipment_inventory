<?php
session_start();

/* ================== ERROR REPORTING ================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ================== SESSION AUTHENTICATION CHECK ================== */
/*if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}*/

/* ================== DB CONNECTION ================== */
require_once "./config/database.php";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
    }
} catch (Exception $e) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    die("Database connection failed. Please try again later.");
}

/* ================== STATUS LABELS ================== */
$statusLabels = [
    'active' => 'Active',
    'in_use' => 'In Use',
    'in_storage' => 'Store',
    'repairing' => 'Repairing',
    'faulty' => 'Faulty',
    'retired' => 'Retired'
];

$conditionLabels = [
    'New' => 'New',
    'Good' => 'Good',
    'Fair' => 'Fair',
    'Faulty' => 'Faulty'
];

/* ================== FETCH TOP DEVICES AND USERS ================== */
$topDevices = [];
$topUsers = [];

try {
    // Top 5 most assigned devices
    $topDevicesQuery = mysqli_query($conn, "
        SELECT 
            i.id,
            i.asset_tag,
            i.device_type,
            i.model,
            i.status,
            b.brand_name,
            c.category_name,
            COUNT(DISTINCT dua.id) as assignment_count,
            COUNT(DISTINCT dua.user_id) as user_count,
            DATEDIFF(NOW(), MIN(dua.assigned_at)) as days_in_service
        FROM inventory_items i
        LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN categories c ON i.category_id = c.id
        GROUP BY i.id
        HAVING assignment_count > 0
        ORDER BY assignment_count DESC, user_count DESC
        LIMIT 5
    ");
    
    if ($topDevicesQuery) {
        while ($row = mysqli_fetch_assoc($topDevicesQuery)) {
            $topDevices[] = $row;
        }
    }

    // Top 5 users with most assignments
    $topUsersQuery = mysqli_query($conn, "
        SELECT 
            u.id,
            u.firstname,
            u.lastname,
            u.email,
            u.role,
            u.status as user_status,
            COUNT(DISTINCT dua.id) as assignment_count,
            COUNT(DISTINCT dua.inventory_id) as device_count,
            AVG(TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW()))) as avg_days_per_assignment,
            SUM(TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW()))) as total_days_assigned
        FROM users u
        LEFT JOIN device_user_assignments dua ON u.id = dua.user_id
        GROUP BY u.id
        HAVING assignment_count > 0
        ORDER BY assignment_count DESC, total_days_assigned DESC
        LIMIT 5
    ");
    
    if ($topUsersQuery) {
        while ($row = mysqli_fetch_assoc($topUsersQuery)) {
            $topUsers[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching top devices/users: " . $e->getMessage());
}

/* ================== FETCH DEVICE DETAILS FOR MODAL ================== */
if (isset($_GET['get_device_details']) && is_numeric($_GET['get_device_details'])) {
    $device_id = (int) $_GET['get_device_details'];
    $response = ['success' => false, 'error' => 'Device not found'];
    
    try {
        $device_query = mysqli_prepare($conn, "
            SELECT 
                i.id,
                i.asset_tag,
                i.device_type,
                i.model,
                i.serial_number,
                i.status,
                i.`condition`,
                i.brand_id,
                i.category_id,
                i.department_id,
                i.remarks,
                i.created_at,
                i.updated_at,
                b.brand_name,
                c.category_name,
                d.department_name,
                (SELECT COUNT(*) FROM device_user_assignments WHERE inventory_id = i.id) as total_assignments,
                (SELECT COUNT(DISTINCT user_id) FROM device_user_assignments WHERE inventory_id = i.id) as unique_users
            FROM inventory_items i
            LEFT JOIN brands b ON i.brand_id = b.id
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN departments d ON i.department_id = d.id
            WHERE i.id = ?
        ");
        
        if ($device_query) {
            mysqli_stmt_bind_param($device_query, "i", $device_id);
            mysqli_stmt_execute($device_query);
            $device_result = mysqli_stmt_get_result($device_query);
            $device_details = mysqli_fetch_assoc($device_result);
            mysqli_stmt_close($device_query);
            
            if ($device_details) {
                $history_query = mysqli_prepare($conn, "
                    SELECT 
                        dua.*,
                        u.firstname,
                        u.lastname,
                        u.email,
                        u.role,
                        TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW())) as days_assigned,
                        CASE 
                            WHEN dua.returned_at IS NULL THEN 'assigned'
                            ELSE 'returned'
                        END as assignment_status
                    FROM device_user_assignments dua
                    JOIN users u ON dua.user_id = u.id
                    WHERE dua.inventory_id = ?
                    ORDER BY dua.assigned_at DESC
                ");
                
                $assignment_history = [];
                if ($history_query) {
                    mysqli_stmt_bind_param($history_query, "i", $device_id);
                    mysqli_stmt_execute($history_query);
                    $history_result = mysqli_stmt_get_result($history_query);
                    
                    while ($row = mysqli_fetch_assoc($history_result)) {
                        $assignment_history[] = $row;
                    }
                    mysqli_stmt_close($history_query);
                }
                
                $response = [
                    'success' => true,
                    'device' => $device_details,
                    'history' => $assignment_history
                ];
            }
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/* ================== FETCH ALL DEVICES WITH HISTORY ================== */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$category_filter = isset($_GET['category']) && is_numeric($_GET['category']) ? (int) $_GET['category'] : 0;

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(i.asset_tag LIKE ? OR i.device_type LIKE ? OR i.model LIKE ? OR b.brand_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if (!empty($status_filter) && array_key_exists($status_filter, $statusLabels)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($category_filter) && $category_filter > 0) {
    $where_conditions[] = "i.category_id = ?";
    $params[] = $category_filter;
    $param_types .= 'i';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

/* ================== PAGINATION ================== */
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 25, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_devices = 0;
try {
    $count_query = "SELECT COUNT(DISTINCT i.id) as total FROM inventory_items i LEFT JOIN brands b ON i.brand_id = b.id $where_sql";
    $count_stmt = mysqli_prepare($conn, $count_query);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_devices = mysqli_fetch_assoc($count_result)['total'] ?? 0;
    mysqli_stmt_close($count_stmt);
} catch (Exception $e) {
    error_log("Error counting devices: " . $e->getMessage());
}

$total_pages = $total_devices > 0 ? ceil($total_devices / $limit) : 1;

/* ================== FETCH DEVICES WITH ASSIGNMENT COUNTS ================== */
$devices = [];
try {
    $devices_query = "
        SELECT 
            i.id,
            i.asset_tag,
            i.device_type,
            i.model,
            i.serial_number,
            i.status,
            i.`condition`,
            i.created_at,
            b.brand_name,
            c.category_name,
            COUNT(DISTINCT dua.user_id) as total_users,
            COUNT(DISTINCT dua.id) as total_assignments,
            MAX(dua.assigned_at) as last_assigned,
            MIN(dua.assigned_at) as first_assigned
        FROM inventory_items i
        LEFT JOIN brands b ON i.brand_id = b.id
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
        $where_sql
        GROUP BY i.id
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $devices_stmt = mysqli_prepare($conn, $devices_query);
    
    $all_params = array_merge($params, [$limit, $offset]);
    $all_types = $param_types . 'ii';
    
    if (!empty($all_params)) {
        mysqli_stmt_bind_param($devices_stmt, $all_types, ...$all_params);
    }
    
    mysqli_stmt_execute($devices_stmt);
    $devices_result = mysqli_stmt_get_result($devices_stmt);
    
    while ($row = mysqli_fetch_assoc($devices_result)) {
        $devices[] = $row;
    }
    mysqli_stmt_close($devices_stmt);
} catch (Exception $e) {
    error_log("Error fetching devices: " . $e->getMessage());
}

/* ================== FETCH DROPDOWN DATA ================== */
$categories = [];
try {
    $categories_result = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name");
    if ($categories_result) {
        $categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

/* ================== FETCH STATISTICS ================== */
$stats = [
    'total_devices' => 0,
    'total_users' => 0,
    'total_assignments' => 0,
    'avg_assignment_days' => null
];

try {
    $stats_query = mysqli_query($conn, "
        SELECT 
            COUNT(DISTINCT i.id) as total_devices,
            COUNT(DISTINCT dua.user_id) as total_users,
            COUNT(dua.id) as total_assignments,
            AVG(TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW()))) as avg_assignment_days
        FROM inventory_items i
        LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
    ");
    
    if ($stats_query) {
        $stats = mysqli_fetch_assoc($stats_query);
    }
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Device Assignment History | Asset Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Professional Design System */
        :root {
            --primary: #1e293b;
            --primary-light: #334155;
            --secondary: #2563eb;
            --secondary-light: #3b82f6;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --info: #4f46e5;
            --background: #f1f5f9;
            --surface: #ffffff;
            --surface-hover: #f8fafc;
            --border: #e2e8f0;
            --border-dark: #cbd5e1;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --text-disabled: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--background);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.5;
        }

        /* Sidebar styles - matching the provided sidebar */
        #sidebar {
            width: 16rem;
            transition: width 0.3s ease;
        }

        #sidebar.collapsed {
            width: 5rem;
        }

        #sidebar.collapsed .nav-text,
        #sidebar.collapsed .header-text,
        #sidebar.collapsed #logo,
        #sidebar.collapsed .badge {
            display: none;
        }

        #mainContent {
            margin-left: 16rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        #mainContent.collapsed {
            margin-left: 5rem;
        }

        /* FOOTER FIX */
        @media (min-width: 768px) {
            #mainFooter {
                margin-left: 16rem;
                width: calc(100% - 16rem);
                transition: margin-left 0.3s ease, width 0.3s ease;
            }

            body.sidebar-collapsed #mainFooter {
                margin-left: 5rem;
                width: calc(100% - 5rem);
            }
        }

        /* Navigation link styles */
        .nav-link {
            position: relative;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            transform: translateX(2px);
        }

        .nav-link.active {
            box-shadow: 0 1px 3px rgba(37, 99, 235, 0.1);
        }

        .badge {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            transition: opacity 0.2s ease;
        }

        .nav-text {
            flex: 1;
            min-width: 0;
        }

        .nav-link.with-badge {
            padding-right: 3rem;
        }

        .nav-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e5e7eb, transparent);
            margin: 0.5rem 1rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-5px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.2s ease-out;
        }

        .animate-slide {
            animation: slideIn 0.2s ease-out;
        }

        /* Status Badges - Solid Colors */
        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1.25rem;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .badge-status-active { background: #dcfce7; color: #166534; border-color: #86efac; }
        .badge-status-in-use { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-status-in-storage { background: #fef9c3; color: #854d0e; border-color: #fde047; }
        .badge-status-repairing { background: #ffedd5; color: #9a3412; border-color: #fdba74; }
        .badge-status-faulty { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .badge-status-retired { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
        
        .badge-condition-new { background: #dcfce7; color: #166534; border-color: #86efac; }
        .badge-condition-good { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-condition-fair { background: #fef9c3; color: #854d0e; border-color: #fde047; }
        .badge-condition-faulty { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }

        .badge-role-admin { background: #f3e8ff; color: #6b21a8; border-color: #d8b4fe; }
        .badge-role-user { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }

        /* Cards */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface-hover);
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--surface-hover);
        }

        /* Tables */
        .table-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--surface-hover);
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
        }

        .table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr {
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover td {
            background: var(--surface-hover);
        }

        /* Progress Bar */
        .progress {
            width: 100%;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--secondary);
            border-radius: 2px;
            transition: width 0.2s ease;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease;
            cursor: pointer;
            border: 1px solid transparent;
            gap: 0.5rem;
            line-height: 1.25rem;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text-primary);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--surface-hover);
            border-color: var(--border-dark);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border-color: var(--border);
        }

        .btn-outline:hover {
            background: var(--surface-hover);
            color: var(--text-primary);
            border-color: var(--border-dark);
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Inputs */
        .form-label {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .input-wrapper {
            position: relative;
            width: 100%;
        }

        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.875rem;
            pointer-events: none;
        }

        .input-field {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            transition: all 0.15s ease;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        .input-field:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .input-field.input-with-icon {
            padding-left: 2.25rem;
        }

        .select-field {
            width: 100%;
            padding: 0.625rem 2rem 0.625rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-primary);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.25rem;
        }

        .select-field:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Stats Cards */
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            transition: all 0.15s ease;
        }

        .stat-card:hover {
            border-color: var(--secondary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .stat-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-blue { background: #dbeafe; color: #1e40af; }
        .stat-icon-green { background: #dcfce7; color: #166534; }
        .stat-icon-purple { background: #f3e8ff; color: #6b21a8; }
        .stat-icon-amber { background: #fef3c7; color: #92400e; }

        /* Top Items Cards */
        .top-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            width: 100%;
        }

        .rank-badge {
            width: 2rem;
            height: 2rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .rank-1 { background: #fef3c7; color: #92400e; }
        .rank-2 { background: #f1f5f9; color: #334155; }
        .rank-3 { background: #ffedd5; color: #9a3412; }
        .rank-other { background: #f1f5f9; color: #475569; }

        .device-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .device-icon-blue { background: #dbeafe; color: #1e40af; }
        .device-icon-green { background: #dcfce7; color: #166534; }
        .device-icon-purple { background: #f3e8ff; color: #6b21a8; }
        .device-icon-amber { background: #fef3c7; color: #92400e; }
        .device-icon-gray { background: #f1f5f9; color: #475569; }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 6px;
            background: #dbeafe;
            color: #1e40af;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-backdrop.active {
            display: flex;
        }

        .modal {
            background: var(--surface);
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 1300px;
            max-height: 90vh;
            overflow: hidden;
            animation: fadeIn 0.2s ease-out;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            flex-shrink: 0;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 1.25rem 1.5rem;
            background: var(--surface-hover);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-width: 350px;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-left-width: 4px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.2s ease-out;
            width: 100%;
        }

        .toast-success { border-left-color: var(--success); }
        .toast-error { border-left-color: var(--danger); }
        .toast-warning { border-left-color: var(--warning); }
        .toast-info { border-left-color: var(--info); }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .page-link:hover {
            background: var(--surface-hover);
            border-color: var(--border-dark);
            color: var(--text-primary);
        }

        .page-link.active {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
        }

        /* Loading Spinner */
        .spinner {
            border: 2px solid var(--border);
            border-top-color: var(--secondary);
            border-radius: 50%;
            width: 1.5rem;
            height: 1.5rem;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Confirmation Modal */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            padding: 1rem;
        }

        .confirmation-modal.active {
            display: flex;
        }

        .confirmation-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
        }

        .confirmation-content {
            position: relative;
            background: var(--surface);
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.2s ease-out;
            z-index: 10002;
        }

        .confirmation-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .confirmation-body {
            padding: 1.5rem;
        }

        .confirmation-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Assignment Cards */
        .assignment-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .assignment-card:last-child {
            margin-bottom: 0;
        }

        .assignment-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active { background: #dcfce7; color: #166534; }
        .status-completed { background: #f1f5f9; color: #475569; }

        /* Grid Layouts */
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .grid-top {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        /* Filter Bar */
        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        /* Responsive Design */
        @media (max-width: 1280px) {
            .grid-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .grid-top {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .grid-stats {
                grid-template-columns: 1fr;
            }
            
            .table th,
            .table td {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
            
            .modal {
                max-height: 100vh;
                border-radius: 0;
            }

            #sidebar {
                width: 5rem;
            }

            #sidebar .nav-text,
            #sidebar .header-text,
            #sidebar #logo,
            #sidebar .badge,
            #sidebar .nav-divider span {
                display: none;
            }

            #mainContent {
                margin-left: 5rem;
            }
        }

        @media (max-width: 640px) {
            .toast-container {
                left: 1rem;
                right: 1rem;
                max-width: none;
            }
            
            .card-body,
            .card-header,
            .card-footer {
                padding: 1rem;
            }
            
            .modal-body {
                padding: 1rem;
            }

            #sidebar {
                width: 0;
                transform: translateX(-100%);
            }

            #sidebar.collapsed {
                width: 0;
            }

            #mainContent {
                margin-left: 0;
            }

            #mainContent.collapsed {
                margin-left: 0;
            }
        }

        /* Utility Classes */
        .hidden {
            display: none !important;
        }

        .text-muted { color: var(--text-muted); }
        .text-secondary { color: var(--text-secondary); }
        .bg-surface { background: var(--surface); }
        .bg-primary { background: #1e293b; color: white; }
        
        .divider {
            height: 1px;
            background: var(--border);
            margin: 1rem 0;
        }

        .space-y-3 > * + * {
            margin-top: 0.75rem;
        }

        .space-y-4 > * + * {
            margin-top: 1rem;
        }

        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }

        .flex {
            display: flex;
        }

        .flex-1 {
            flex: 1;
        }

        .flex-shrink-0 {
            flex-shrink: 0;
        }

        .items-center {
            align-items: center;
        }

        .items-start {
            align-items: flex-start;
        }

        .justify-between {
            justify-content: space-between;
        }

        .justify-end {
            justify-content: flex-end;
        }

        .w-full {
            width: 100%;
        }

        .min-w-0 {
            min-width: 0;
        }

        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .overflow-x-auto {
            overflow-x: auto;
        }

        /* Container for main content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        /* User Info in top items */
        .user-info {
            flex: 1;
            min-width: 0;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="min-h-screen bg-[#f1f5f9]">
        <!-- Toast Container -->
        <div id="toastContainer" class="toast-container"></div>

        <!-- Confirmation Modal -->
        <div id="confirmationModal" class="confirmation-modal">
            <div class="confirmation-backdrop" onclick="closeConfirmation()"></div>
            <div class="confirmation-content">
                <div class="confirmation-header">
                    <h3 class="text-lg font-semibold text-[#0f172a]" id="confirmationTitle">Confirm Action</h3>
                </div>
                <div class="confirmation-body">
                    <p class="text-[#475569]" id="confirmationMessage">Are you sure you want to perform this action?</p>
                </div>
                <div class="confirmation-footer">
                    <button onclick="closeConfirmation()" class="btn btn-outline">
                        Cancel
                    </button>
                    <button onclick="confirmAction()" id="confirmButton" class="btn btn-primary">
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-[#0f172a]">Device Assignment History</h1>
                    <p class="text-[#475569] text-sm mt-1">Track all device assignments and user history</p>
                </div>
                <div>
                    <button onclick="showExportConfirmation()" class="btn btn-secondary">
                        <i class="fas fa-download text-xs"></i>
                        Export
                    </button>
                </div>
            </div>

            <!-- Statistics Summary -->
            <div class="grid-stats mb-6">
                <?php
                $stat_icons = ['blue', 'green', 'purple', 'amber'];
                $stat_items = [
                    [
                        'title' => 'Total Devices',
                        'value' => number_format($stats['total_devices'] ?? 0),
                        'icon' => 'fa-laptop',
                        'description' => 'Devices in system'
                    ],
                    [
                        'title' => 'Total Users',
                        'value' => number_format($stats['total_users'] ?? 0),
                        'icon' => 'fa-users',
                        'description' => 'Unique users assigned'
                    ],
                    [
                        'title' => 'Total Assignments',
                        'value' => number_format($stats['total_assignments'] ?? 0),
                        'icon' => 'fa-exchange-alt',
                        'description' => 'All assignments'
                    ],
                    [
                        'title' => 'Avg. Assignment',
                        'value' => isset($stats['avg_assignment_days']) && $stats['avg_assignment_days'] ? round($stats['avg_assignment_days'], 1) . ' days' : 'N/A',
                        'icon' => 'fa-calendar-alt',
                        'description' => 'Average duration'
                    ]
                ];
                ?>

                <?php foreach ($stat_items as $index => $stat): ?>
                    <div class="stat-card animate-fade-in">
                        <div class="flex items-start justify-between">
                            <div style="flex: 1; min-width: 0;">
                                <p class="text-xs font-medium text-[#64748b] uppercase tracking-wider"><?= htmlspecialchars($stat['title']) ?></p>
                                <p class="text-2xl font-bold text-[#0f172a] mt-1"><?= htmlspecialchars($stat['value']) ?></p>
                                <p class="text-xs text-[#64748b] mt-1"><?= htmlspecialchars($stat['description']) ?></p>
                            </div>
                            <div class="stat-icon stat-icon-<?= $stat_icons[$index] ?>">
                                <i class="fas <?= htmlspecialchars($stat['icon']) ?>"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Top Devices and Users Section -->
            <div class="grid-top mb-6">
                <!-- Top 5 Most Assigned Devices -->
                <div class="card animate-fade-in">
                    <div class="card-header">
                        <h2 class="font-semibold text-[#0f172a]">Top 5 Most Assigned Devices</h2>
                        <p class="text-xs text-[#64748b] mt-0.5">Based on total assignment count</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topDevices)): ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-[#f1f5f9] rounded flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-inbox text-[#64748b]"></i>
                                </div>
                                <p class="text-[#64748b] text-sm">No device assignment data available</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($topDevices as $index => $device): ?>
                                    <?php
                                    $rank = $index + 1;
                                    $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                                    $deviceIconColor = match ($device['device_type'] ?? '') {
                                        'Laptop' => 'device-icon-blue',
                                        'Desktop' => 'device-icon-purple',
                                        'Tablet' => 'device-icon-green',
                                        'Mobile' => 'device-icon-amber',
                                        'Monitor' => 'device-icon-gray',
                                        default => 'device-icon-gray'
                                    };
                                    $statusKey = $device['status'] ?? 'active';
                                    $statusClass = 'badge-status-' . str_replace('_', '-', $statusKey);
                                    ?>
                                    <div class="top-item">
                                        <div class="flex items-center gap-3">
                                            <div class="rank-badge <?= $rankClass ?>"><?= $rank ?></div>
                                            <div class="device-icon <?= $deviceIconColor ?>">
                                                <i class="fas fa-laptop"></i>
                                            </div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="truncate" style="flex: 1; min-width: 0;">
                                                        <p class="font-medium text-[#0f172a]"><?= htmlspecialchars($device['asset_tag'] ?? 'Unknown') ?></p>
                                                        <p class="text-xs text-[#64748b] truncate">
                                                            <?= htmlspecialchars($device['device_type'] ?? 'Unknown') ?> •
                                                            <?= htmlspecialchars($device['brand_name'] ?? '') ?> <?= htmlspecialchars($device['model'] ?? '') ?>
                                                        </p>
                                                    </div>
                                                    <p class="font-bold text-[#0f172a] whitespace-nowrap"><?= (int)($device['assignment_count'] ?? 0) ?></p>
                                                </div>
                                                <div class="flex items-center justify-between mt-2 text-xs gap-3">
                                                    <span class="text-[#64748b]">Unique users: <?= (int)($device['user_count'] ?? 0) ?></span>
                                                    <span class="badge-status <?= $statusClass ?> whitespace-nowrap">
                                                        <?= htmlspecialchars($statusLabels[$device['status'] ?? ''] ?? ucfirst(str_replace('_', ' ', $device['status'] ?? 'Unknown'))) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top 5 Users with Most Assignments -->
                <div class="card animate-fade-in">
                    <div class="card-header">
                        <h2 class="font-semibold text-[#0f172a]">Top 5 Active Users</h2>
                        <p class="text-xs text-[#64748b] mt-0.5">Based on total assignment count</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topUsers)): ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-[#f1f5f9] rounded flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-users text-[#64748b]"></i>
                                </div>
                                <p class="text-[#64748b] text-sm">No user assignment data available</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($topUsers as $index => $user): ?>
                                    <?php
                                    $rank = $index + 1;
                                    $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                                    $roleClass = ($user['role'] ?? '') === 'admin' ? 'badge-role-admin' : 'badge-role-user';
                                    $avatarInitials = strtoupper(substr($user['firstname'] ?? '', 0, 1) . substr($user['lastname'] ?? '', 0, 1));
                                    if (empty(trim($avatarInitials))) $avatarInitials = '?';
                                    ?>
                                    <div class="top-item">
                                        <div class="flex items-center gap-3">
                                            <div class="rank-badge <?= $rankClass ?>"><?= $rank ?></div>
                                            <div class="user-avatar"><?= htmlspecialchars($avatarInitials) ?></div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div style="flex: 1; min-width: 0;">
                                                        <p class="font-medium text-[#0f172a] truncate">
                                                            <?= htmlspecialchars(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?>
                                                        </p>
                                                        <p class="text-xs text-[#64748b] truncate"><?= htmlspecialchars($user['email'] ?? 'No email') ?></p>
                                                    </div>
                                                    <p class="font-bold text-[#0f172a] whitespace-nowrap"><?= (int)($user['assignment_count'] ?? 0) ?></p>
                                                </div>
                                                <div class="flex items-center gap-3 mt-2 flex-wrap">
                                                    <span class="badge <?= $roleClass ?>">
                                                        <?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?>
                                                    </span>
                                                    <span class="text-xs text-[#64748b]">
                                                        <i class="fas fa-laptop mr-1"></i><?= (int)($user['device_count'] ?? 0) ?> devices
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="filter-bar mb-6 animate-fade-in">
                <form method="GET" class="filter-grid">
                    <!-- Search -->
                    <div>
                        <label class="form-label">Search Devices</label>
                        <div class="input-wrapper">
                            <i class="fas fa-search input-icon"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by asset tag, model, or brand..." class="input-field input-with-icon"
                            autocomplete="off">
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="select-field">
                            <option value="">All Status</option>
                            <?php foreach ($statusLabels as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $status_filter == $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <label class="form-label">Category</label>
                        <select name="category" class="select-field">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name'] ?? 'Unknown') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            Filter
                        </button>
                        <a href="device_history.php" class="btn btn-outline">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Devices Table -->
            <div class="card animate-fade-in">
                <div class="card-header">
                    <h2 class="font-semibold text-[#0f172a]">Device Assignment History</h2>
                    <p class="text-xs text-[#64748b] mt-0.5">Showing <?= count($devices) ?> of <?= number_format($total_devices) ?> devices</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>Assignments</th>
                                <th>Users</th>
                                <th>First Assigned</th>
                                <th>Last Assigned</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($devices)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-12">
                                        <div class="flex flex-col items-center">
                                            <div class="w-12 h-12 bg-[#f1f5f9] rounded flex items-center justify-center mb-3">
                                                <i class="fas fa-search text-[#64748b]"></i>
                                            </div>
                                            <p class="text-[#64748b]">No devices found matching your criteria</p>
                                            <a href="device_history.php" class="btn btn-outline btn-sm mt-4">
                                                <i class="fas fa-redo mr-2"></i>Clear Filters
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devices as $device): ?>
                                    <?php
                                    $assignment_percentage = min(100, (($device['total_assignments'] ?? 0) * 4));
                                    $deviceIconColor = match ($device['device_type'] ?? '') {
                                        'Laptop' => 'device-icon-blue',
                                        'Desktop' => 'device-icon-purple',
                                        'Tablet' => 'device-icon-green',
                                        'Mobile' => 'device-icon-amber',
                                        'Monitor' => 'device-icon-gray',
                                        default => 'device-icon-gray'
                                    };
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="device-icon <?= $deviceIconColor ?>">
                                                    <i class="fas fa-laptop"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <p class="font-medium text-[#0f172a]"><?= htmlspecialchars($device['asset_tag'] ?? 'Unknown') ?></p>
                                                    <p class="text-xs text-[#64748b]">
                                                        <?= htmlspecialchars($device['device_type'] ?? 'Unknown') ?>
                                                        <?php if (!empty($device['brand_name']) || !empty($device['model'])): ?>
                                                            • <?= htmlspecialchars($device['brand_name'] ?? '') ?> <?= htmlspecialchars($device['model'] ?? '') ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div style="width: 80px;">
                                                    <div class="progress">
                                                        <div class="progress-bar" style="width: <?= $assignment_percentage ?>%"></div>
                                                    </div>
                                                </div>
                                                <span class="font-medium text-[#0f172a]"><?= (int)($device['total_assignments'] ?? 0) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div style="width: 1.5rem; height: 1.5rem; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-user text-[#64748b]" style="font-size: 0.75rem;"></i>
                                                </div>
                                                <span class="font-medium text-[#0f172a]"><?= (int)($device['total_users'] ?? 0) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($device['first_assigned'])): ?>
                                                <p class="text-sm text-[#0f172a]"><?= date('M d, Y', strtotime($device['first_assigned'])) ?></p>
                                                <p class="text-xs text-[#64748b]"><?= date('h:i A', strtotime($device['first_assigned'])) ?></p>
                                            <?php else: ?>
                                                <span class="text-sm text-[#64748b] italic">Never assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($device['last_assigned'])): ?>
                                                <p class="text-sm text-[#0f172a]"><?= date('M d, Y', strtotime($device['last_assigned'])) ?></p>
                                                <p class="text-xs text-[#64748b]"><?= date('h:i A', strtotime($device['last_assigned'])) ?></p>
                                            <?php else: ?>
                                                <span class="text-sm text-[#64748b] italic">Not applicable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="openDeviceHistoryModal(<?= (int)($device['id'] ?? 0) ?>, '<?= htmlspecialchars(addslashes($device['asset_tag'] ?? 'Unknown'), ENT_QUOTES) ?>')" class="btn btn-primary btn-sm">
                                                <i class="fas fa-history"></i>
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <?php
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $base_url = '?' . (!empty($query_params) ? http_build_query($query_params) . '&' : '');
                    ?>
                    <div class="card-footer">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;">
                                <p class="text-sm text-[#64748b]">
                                    Page <?= $page ?> of <?= $total_pages ?> • Showing <?= count($devices) ?> of <?= number_format($total_devices) ?> devices
                                </p>

                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="<?= $base_url ?>page=<?= $page - 1 ?>" class="page-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);

                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <a href="<?= $base_url ?>page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="<?= $base_url ?>page=<?= $page + 1 ?>" class="page-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-[#64748b]">Show:</span>
                                    <select onchange="changeItemsPerPage(this)" class="select-field" style="width: auto; padding: 0.25rem 2rem 0.25rem 0.5rem; font-size: 0.875rem;">
                                        <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                    </select>
                                    <span class="text-sm text-[#64748b]">per page</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Device History Modal -->
    <div id="deviceHistoryModal" class="modal-backdrop">
        <div class="modal">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#334155] rounded flex items-center justify-center">
                            <i class="fas fa-history text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white" id="modalTitle">Device Assignment History</h2>
                            <p class="text-[#94a3b8] text-sm" id="modalSubtitle">Select a device to view history</p>
                        </div>
                    </div>
                    <button onclick="closeDeviceHistoryModal()" class="text-[#94a3b8] hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <!-- Loading Spinner (hidden by default) -->
                <div id="loadingSpinner" class="text-center py-12 hidden">
                    <div class="spinner mx-auto mb-4"></div>
                    <p class="text-[#64748b]">Loading device history...</p>
                </div>

                <!-- Error Message (hidden by default) -->
                <div id="errorMessage" class="text-center py-12 hidden">
                    <div class="w-16 h-16 bg-[#fee2e2] rounded flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-[#dc2626] text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-[#0f172a] mb-2">Error Loading Data</h3>
                    <p class="text-[#64748b]" id="errorMessageText">Failed to load device history. Please try again.</p>
                    <button onclick="retryLoadDeviceHistory()" class="btn btn-primary mt-4">
                        <i class="fas fa-redo mr-2"></i>Retry
                    </button>
                </div>

                <!-- Modal Content (hidden by default) -->
                <div id="modalContent" class="hidden">
                    <!-- Device Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Asset Tag</p>
                            <p class="font-mono font-medium text-[#0f172a]" id="modalAssetTag">-</p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Brand & Model</p>
                            <p class="font-medium text-[#0f172a]" id="modalBrand">-</p>
                            <p class="text-sm text-[#64748b]" id="modalModel">-</p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Serial Number</p>
                            <p class="font-mono font-medium text-[#0f172a]" id="modalSerial">-</p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Category</p>
                            <p class="font-medium text-[#0f172a]" id="modalCategory">-</p>
                        </div>
                    </div>

                    <!-- Status & Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-2">Current Status</p>
                            <div id="modalStatus" class="flex gap-2"></div>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Total Assignments</p>
                            <p class="text-2xl font-bold text-[#0f172a]" id="modalTotalAssignments">0</p>
                            <p class="text-sm text-[#64748b]" id="modalUniqueUsers">0 unique users</p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Current Assignment</p>
                            <p class="font-medium text-[#0f172a]" id="modalCurrentAssignment">Not assigned</p>
                            <p class="text-sm text-[#64748b]" id="modalAssignmentDate">-</p>
                        </div>
                    </div>

                    <!-- Assignment History -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <h3 class="font-semibold text-[#0f172a]">Assignment History</h3>
                            <p class="text-sm text-[#64748b]" id="timelineDescription">No assignments yet</p>
                        </div>
                        <div class="card-body">
                            <div id="assignmentHistory"></div>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="font-semibold text-[#0f172a]">Assignment Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div id="assignmentStatistics"></div>
                        </div>
                    </div>
                </div>

                <!-- No History Message (hidden by default) -->
                <div id="noHistoryMessage" class="hidden text-center py-12">
                    <div class="w-16 h-16 bg-[#f1f5f9] rounded flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-history text-[#64748b] text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-[#0f172a] mb-2">No Assignment History</h3>
                    <p class="text-[#64748b]">This device has never been assigned to any user.</p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button onclick="printModalContent()" class="btn btn-outline">
                    <i class="fas fa-print"></i>
                    Print
                </button>
                <button onclick="closeDeviceHistoryModal()" class="btn btn-primary">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Status labels mapping from PHP
        const statusLabels = <?= json_encode($statusLabels) ?>;
        const conditionLabels = <?= json_encode($conditionLabels) ?>;

        // Status colors mapping
        const statusColors = {
            'active': 'badge-status-active',
            'in_use': 'badge-status-in-use',
            'in_storage': 'badge-status-in-storage',
            'repairing': 'badge-status-repairing',
            'faulty': 'badge-status-faulty',
            'retired': 'badge-status-retired'
        };

        const conditionColors = {
            'New': 'badge-condition-new',
            'Good': 'badge-condition-good',
            'Fair': 'badge-condition-fair',
            'Faulty': 'badge-condition-faulty'
        };

        // Toast Notification System
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;

            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'warning') icon = 'fa-exclamation-triangle';

            toast.innerHTML = `
                <i class="fas ${icon} text-[#475569]"></i>
                <div class="flex-1 text-sm">${message}</div>
                <button onclick="this.parentElement.remove()" class="text-[#64748b] hover:text-[#0f172a]">
                    <i class="fas fa-times"></i>
                </button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, duration);
        }

        // Confirmation Modal System
        let currentConfirmationCallback = null;

        function showConfirmation(title, message, confirmCallback, confirmText = 'Confirm') {
            const modal = document.getElementById('confirmationModal');
            if (!modal) return;
            
            document.getElementById('confirmationTitle').textContent = title;
            document.getElementById('confirmationMessage').textContent = message;
            document.getElementById('confirmButton').textContent = confirmText;

            currentConfirmationCallback = confirmCallback;
            modal.classList.add('active');
        }

        function closeConfirmation() {
            const modal = document.getElementById('confirmationModal');
            if (modal) modal.classList.remove('active');
            currentConfirmationCallback = null;
        }

        function confirmAction() {
            if (currentConfirmationCallback) {
                currentConfirmationCallback();
            }
            closeConfirmation();
        }

        function showExportConfirmation() {
            showConfirmation(
                'Export Device History',
                'Are you sure you want to export the device assignment history?',
                () => {
                    exportToCSV();
                    showToast('Export started successfully', 'success');
                },
                'Export'
            );
        }

        // Pagination function
        function changeItemsPerPage(select) {
            showConfirmation(
                'Change Items Per Page',
                'Changing items per page will reset to the first page. Continue?',
                () => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('limit', select.value);
                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                },
                'Continue'
            );
        }

        // Export function
        function exportToCSV() {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            const status = new URLSearchParams(window.location.search).get('status') || '';
            const category = new URLSearchParams(window.location.search).get('category') || '';
            
            let exportUrl = 'export_device_history.php?';
            if (search) exportUrl += 'search=' + encodeURIComponent(search) + '&';
            if (status) exportUrl += 'status=' + encodeURIComponent(status) + '&';
            if (category) exportUrl += 'category=' + encodeURIComponent(category) + '&';
            
            window.location.href = exportUrl;
        }

        // Date formatting helper
        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00 00:00:00') return 'Unknown date';

            try {
                const date = new Date(dateString.replace(' ', 'T'));
                if (isNaN(date.getTime())) {
                    return 'Invalid date';
                }
                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                console.error('Date formatting error:', e);
                return 'Invalid date';
            }
        }

        // Modal functions
        let currentDeviceId = null;
        let currentAssetTag = '';

        function openDeviceHistoryModal(deviceId, assetTag) {
            if (!deviceId || deviceId === 0) {
                showToast('Invalid device ID', 'error');
                return;
            }
            
            currentDeviceId = deviceId;
            currentAssetTag = assetTag;
            
            const modal = document.getElementById('deviceHistoryModal');
            const subtitle = document.getElementById('modalSubtitle');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const errorMessage = document.getElementById('errorMessage');
            const modalContent = document.getElementById('modalContent');
            const noHistoryMessage = document.getElementById('noHistoryMessage');

            if (!modal) {
                console.error('Modal element not found');
                return;
            }

            if (subtitle) subtitle.textContent = `Loading history for ${assetTag}...`;

            if (loadingSpinner) loadingSpinner.classList.remove('hidden');
            if (errorMessage) errorMessage.classList.add('hidden');
            if (modalContent) modalContent.classList.add('hidden');
            if (noHistoryMessage) noHistoryMessage.classList.add('hidden');

            modal.classList.add('active');

            fetchDeviceHistory(deviceId, assetTag);
        }

        function closeDeviceHistoryModal() {
            const modal = document.getElementById('deviceHistoryModal');
            if (modal) modal.classList.remove('active');
        }

        function retryLoadDeviceHistory() {
            if (currentDeviceId) {
                fetchDeviceHistory(currentDeviceId, currentAssetTag);
            }
        }

        function fetchDeviceHistory(deviceId, assetTag) {
            const xhr = new XMLHttpRequest();
            const url = `device_history.php?get_device_details=${deviceId}&ajax=1&_=${Date.now()}`;

            xhr.open('GET', url, true);
            xhr.timeout = 10000;

            xhr.onload = function() {
                const loadingSpinner = document.getElementById('loadingSpinner');
                const errorMessage = document.getElementById('errorMessage');
                const modalContent = document.getElementById('modalContent');
                const noHistoryMessage = document.getElementById('noHistoryMessage');
                const errorMessageText = document.getElementById('errorMessageText');

                if (loadingSpinner) loadingSpinner.classList.add('hidden');

                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);

                        if (data.success && data.device) {
                            if (data.history && data.history.length > 0) {
                                populateModal(data.device, data.history);
                                if (modalContent) modalContent.classList.remove('hidden');
                                if (noHistoryMessage) noHistoryMessage.classList.add('hidden');
                                if (errorMessage) errorMessage.classList.add('hidden');
                                showToast(`Loaded history for ${assetTag}`, 'success');
                            } else {
                                if (noHistoryMessage) noHistoryMessage.classList.remove('hidden');
                                if (modalContent) modalContent.classList.add('hidden');
                                if (errorMessage) errorMessage.classList.add('hidden');
                            }
                        } else {
                            if (errorMessageText) {
                                errorMessageText.textContent = data.error || 'Failed to load device history';
                            }
                            if (errorMessage) errorMessage.classList.remove('hidden');
                            if (modalContent) modalContent.classList.add('hidden');
                            if (noHistoryMessage) noHistoryMessage.classList.add('hidden');
                            showToast(data.error || 'Error loading device history', 'error');
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        if (errorMessageText) errorMessageText.textContent = 'Invalid response from server';
                        if (errorMessage) errorMessage.classList.remove('hidden');
                        if (modalContent) modalContent.classList.add('hidden');
                        if (noHistoryMessage) noHistoryMessage.classList.add('hidden');
                        showToast('Invalid response from server', 'error');
                    }
                } else {
                    if (errorMessageText) {
                        errorMessageText.textContent = `Server error: ${xhr.status} ${xhr.statusText}`;
                    }
                    if (errorMessage) errorMessage.classList.remove('hidden');
                    if (modalContent) modalContent.classList.add('hidden');
                    if (noHistoryMessage) noHistoryMessage.classList.add('hidden');
                    showToast(`Server error: ${xhr.status}`, 'error');
                }
            };

            xhr.onerror = function() {
                const loadingSpinner = document.getElementById('loadingSpinner');
                const errorMessage = document.getElementById('errorMessage');
                const errorMessageText = document.getElementById('errorMessageText');

                if (loadingSpinner) loadingSpinner.classList.add('hidden');
                if (errorMessageText) errorMessageText.textContent = 'Network error. Please check your connection.';
                if (errorMessage) errorMessage.classList.remove('hidden');
                
                showToast('Network error. Please check your connection.', 'error');
            };

            xhr.ontimeout = function() {
                const loadingSpinner = document.getElementById('loadingSpinner');
                const errorMessage = document.getElementById('errorMessage');
                const errorMessageText = document.getElementById('errorMessageText');

                if (loadingSpinner) loadingSpinner.classList.add('hidden');
                if (errorMessageText) errorMessageText.textContent = 'Request timeout. Please try again.';
                if (errorMessage) errorMessage.classList.remove('hidden');
                
                showToast('Request timeout', 'error');
            };

            xhr.send();
        }

        function populateModal(device, history) {
            document.getElementById('modalAssetTag').textContent = device.asset_tag || 'N/A';
            document.getElementById('modalBrand').textContent = device.brand_name || 'N/A';
            document.getElementById('modalModel').textContent = device.model || 'N/A';
            document.getElementById('modalSerial').textContent = device.serial_number || 'N/A';
            document.getElementById('modalCategory').textContent = device.category_name || 'N/A';
            document.getElementById('modalTotalAssignments').textContent = device.total_assignments || 0;
            document.getElementById('modalUniqueUsers').textContent = (device.unique_users || 0) + ' unique users';

            const statusClass = statusColors[device.status] || 'bg-[#f1f5f9] text-[#475569] border-[#cbd5e1]';
            const conditionClass = conditionColors[device.condition] || 'bg-[#f1f5f9] text-[#475569] border-[#cbd5e1]';

            const statusText = device.status ?
                (statusLabels[device.status] || device.status.charAt(0).toUpperCase() + device.status.slice(1).replace('_', ' ')) :
                'Unknown';

            const conditionText = device.condition ?
                (conditionLabels[device.condition] || device.condition.charAt(0).toUpperCase() + device.condition.slice(1)) :
                'Unknown';

            const statusBadge = document.getElementById('modalStatus');
            statusBadge.innerHTML = `
                <span class="badge-status ${statusClass}">${statusText}</span>
                <span class="badge-status ${conditionClass}">${conditionText}</span>
            `;

            const currentAssignment = Array.isArray(history) ?
                history.find(a => a.assignment_status === 'assigned' || !a.returned_at) : null;

            if (currentAssignment) {
                document.getElementById('modalCurrentAssignment').textContent =
                    (currentAssignment.firstname || '') + ' ' + (currentAssignment.lastname || '');
                document.getElementById('modalAssignmentDate').textContent =
                    'Since ' + formatDate(currentAssignment.assigned_at);
            } else {
                document.getElementById('modalCurrentAssignment').textContent = 'Not assigned';
                document.getElementById('modalAssignmentDate').textContent = 'Available for assignment';
            }

            document.getElementById('modalSubtitle').textContent = `History for ${device.asset_tag}`;
            document.getElementById('timelineDescription').textContent =
                `Showing ${history.length} assignment${history.length !== 1 ? 's' : ''}`;

            populateAssignmentHistory(history || []);
            populateStatistics(history || []);
        }

        function populateAssignmentHistory(history) {
            const historyContainer = document.getElementById('assignmentHistory');

            if (!history || history.length === 0) {
                historyContainer.innerHTML = `
                    <div class="text-center py-8">
                        <div class="w-12 h-12 bg-[#f1f5f9] rounded flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-history text-[#64748b]"></i>
                        </div>
                        <p class="text-[#64748b]">No assignment history available</p>
                    </div>
                `;
                return;
            }

            let historyHTML = '';

            history.forEach((assignment, index) => {
                const isActive = assignment.assignment_status === 'assigned' || !assignment.returned_at;
                const assignedDate = formatDate(assignment.assigned_at);
                const returnedDate = assignment.returned_at ? formatDate(assignment.returned_at) : null;
                const daysAssigned = assignment.days_assigned || 0;
                
                const durationText = daysAssigned > 0 ?
                    `${daysAssigned} day${daysAssigned > 1 ? 's' : ''}` :
                    'Less than a day';

                const userName = [assignment.firstname, assignment.lastname].filter(Boolean).join(' ') || 'Unknown User';
                const userInitial = (assignment.firstname ? assignment.firstname.charAt(0).toUpperCase() : '?');

                historyHTML += `
                    <div class="assignment-card">
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-start gap-4">
                                    <div class="user-avatar flex-shrink-0">
                                        ${userInitial}
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="flex flex-wrap items-center gap-3 mb-3">
                                            <span class="assignment-status ${isActive ? 'status-active' : 'status-completed'}">
                                                <i class="fas ${isActive ? 'fa-user-check' : 'fa-user-times'} mr-1"></i>
                                                ${isActive ? 'ACTIVE' : 'COMPLETED'}
                                            </span>
                                            <span class="text-xs text-[#64748b]">
                                                <i class="far fa-calendar mr-1"></i>${assignedDate}
                                            </span>
                                            <span class="text-xs text-[#64748b]">
                                                <i class="far fa-clock mr-1"></i>${durationText}
                                            </span>
                                        </div>
                                        
                                        <h4 class="font-medium text-[#0f172a] mb-2">
                                            ${userName}
                                        </h4>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p class="text-[#64748b]">
                                                    <i class="fas fa-envelope mr-2 text-xs"></i>
                                                    ${assignment.email || 'N/A'}
                                                </p>
                                                <p class="text-[#64748b] mt-1">
                                                    <i class="fas fa-user-tag mr-2 text-xs"></i>
                                                    Role: ${assignment.role || 'N/A'}
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-[#64748b]">
                                                    <i class="fas fa-calendar-alt mr-2 text-xs"></i>
                                                    Assigned: ${assignedDate}
                                                </p>
                                                ${returnedDate ? `
                                                    <p class="text-[#64748b] mt-1">
                                                        <i class="fas fa-calendar-times mr-2 text-xs"></i>
                                                        Returned: ${returnedDate}
                                                    </p>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:text-right">
                                <p class="text-xs text-[#64748b] mb-1">
                                    <i class="fas fa-hashtag"></i> Assignment #${history.length - index}
                                </p>
                                <span class="badge-status ${isActive ? 'badge-status-active' : 'bg-[#f1f5f9] text-[#475569] border-[#cbd5e1]'}">
                                    ${isActive ? 'Active' : 'Completed'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });

            historyContainer.innerHTML = historyHTML;
        }

        function populateStatistics(history) {
            const statsContainer = document.getElementById('assignmentStatistics');

            if (!history || history.length === 0) {
                statsContainer.innerHTML = '<p class="text-[#64748b] text-center">No statistics available</p>';
                return;
            }

            let totalDays = 0;
            let activeAssignments = 0;
            let completedAssignments = 0;
            let uniqueUserIds = [];
            let userCounts = {};
            let longestAssignment = 0;
            let shortestAssignment = Infinity;
            let firstAssignmentDate = null;
            let lastAssignmentDate = null;

            history.forEach(assignment => {
                if (assignment.assignment_status === 'assigned' || !assignment.returned_at) {
                    activeAssignments++;
                } else {
                    completedAssignments++;
                }

                const daysAssigned = parseInt(assignment.days_assigned) || 0;
                totalDays += daysAssigned;

                if (daysAssigned > longestAssignment) longestAssignment = daysAssigned;
                if (daysAssigned < shortestAssignment) shortestAssignment = daysAssigned;

                if (assignment.user_id && !uniqueUserIds.includes(assignment.user_id)) {
                    uniqueUserIds.push(assignment.user_id);
                }

                if (assignment.user_id) {
                    if (!userCounts[assignment.user_id]) {
                        userCounts[assignment.user_id] = {
                            name: [assignment.firstname, assignment.lastname].filter(Boolean).join(' ') || 'Unknown User',
                            count: 0,
                            totalDays: 0
                        };
                    }
                    userCounts[assignment.user_id].count++;
                    userCounts[assignment.user_id].totalDays += daysAssigned;
                }

                if (!firstAssignmentDate || new Date(assignment.assigned_at) < new Date(firstAssignmentDate)) {
                    firstAssignmentDate = assignment.assigned_at;
                }
                if (!lastAssignmentDate || new Date(assignment.assigned_at) > new Date(lastAssignmentDate)) {
                    lastAssignmentDate = assignment.assigned_at;
                }
            });

            if (shortestAssignment === Infinity) shortestAssignment = 0;

            const avgDays = history.length > 0 ? Math.round((totalDays / history.length) * 10) / 10 : 0;

            const userCountsArray = Object.values(userCounts);
            userCountsArray.sort((a, b) => b.count - a.count);
            const topUsers = userCountsArray.slice(0, 5);

            let statsHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                        <p class="text-xs font-medium text-[#475569] mb-1">Total Assignments</p>
                        <p class="text-2xl font-bold text-[#0f172a]">${history.length}</p>
                        <p class="text-xs text-[#64748b]">${activeAssignments} active, ${completedAssignments} completed</p>
                    </div>
                    
                    <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                        <p class="text-xs font-medium text-[#475569] mb-1">Unique Users</p>
                        <p class="text-2xl font-bold text-[#0f172a]">${uniqueUserIds.length}</p>
                        <p class="text-xs text-[#64748b]">Distinct users assigned</p>
                    </div>
                    
                    <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                        <p class="text-xs font-medium text-[#475569] mb-1">Avg. Duration</p>
                        <p class="text-2xl font-bold text-[#0f172a]">${avgDays} days</p>
                        <p class="text-xs text-[#64748b]">Average per assignment</p>
                    </div>
                    
                    <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                        <p class="text-xs font-medium text-[#475569] mb-1">Longest Assignment</p>
                        <p class="text-2xl font-bold text-[#0f172a]">${longestAssignment} days</p>
                        <p class="text-xs text-[#64748b]">Shortest: ${shortestAssignment} days</p>
                    </div>
                </div>
            `;

            if (topUsers.length > 0) {
                statsHTML += `
                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-[#0f172a] mb-3">Top Users by Assignments</h4>
                        <div class="space-y-3">
                `;

                topUsers.forEach(user => {
                    const percentage = Math.min(100, (user.count / history.length * 100));
                    const avgUserDays = user.count > 0 ? Math.round(user.totalDays / user.count * 10) / 10 : 0;
                    const userInitial = user.name.charAt(0).toUpperCase();

                    statsHTML += `
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="user-avatar w-8 h-8 text-xs">
                                        ${userInitial}
                                    </div>
                                    <div>
                                        <p class="font-medium text-[#0f172a]">${user.name}</p>
                                        <p class="text-xs text-[#64748b]">Avg: ${avgUserDays} days per assignment</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-[#0f172a]">${user.count}</p>
                                    <p class="text-xs text-[#64748b]">assignments</p>
                                </div>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${percentage}%"></div>
                            </div>
                        </div>
                    `;
                });

                statsHTML += `
                        </div>
                    </div>
                `;
            }

            if (firstAssignmentDate && lastAssignmentDate) {
                const firstDate = formatDate(firstAssignmentDate);
                const lastDate = formatDate(lastAssignmentDate);

                statsHTML += `
                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-[#0f172a] mb-3">Assignment Timeline</h4>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-[#64748b]">First Assignment:</span>
                                <span class="font-medium text-[#0f172a]">${firstDate}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-[#64748b]">Last Assignment:</span>
                                <span class="font-medium text-[#0f172a]">${lastDate}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-[#64748b]">Total Time Period:</span>
                                <span class="font-medium text-[#0f172a]">${totalDays} days</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            statsContainer.innerHTML = statsHTML;
        }

        function printModalContent() {
            showConfirmation(
                'Print Device History',
                'Do you want to print the device assignment history?',
                () => {
                    const modalContent = document.getElementById('modalContent');
                    if (!modalContent) return;
                    
                    const printContent = modalContent.innerHTML;
                    const deviceName = document.getElementById('modalAssetTag').textContent;
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Device History - ${deviceName}</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; color: #0f172a; }
                                .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
                                .badge-status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; border: 1px solid #e2e8f0; }
                                .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
                                .stat-box { border: 1px solid #e2e8f0; padding: 10px; border-radius: 4px; }
                                .assignment-card { border: 1px solid #e2e8f0; padding: 15px; margin-bottom: 10px; border-radius: 4px; }
                                @media print {
                                    body { font-size: 12px; }
                                    .no-print { display: none; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="print-header">
                                <h1>Device Assignment History</h1>
                                <p>Device: ${deviceName}</p>
                                <p>Generated on ${new Date().toLocaleDateString()}</p>
                            </div>
                            ${printContent}
                            <p class="no-print" style="text-align: center; margin-top: 30px; color: #64748b;">
                                Generated by Asset Management System
                            </p>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                    showToast('Print dialog opened', 'info');
                },
                'Print'
            );
        }

        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('deviceHistoryModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeDeviceHistoryModal();
                    }
                });
            }

            const confirmationModal = document.getElementById('confirmationModal');
            if (confirmationModal) {
                confirmationModal.addEventListener('click', function(e) {
                    if (e.target.classList.contains('confirmation-backdrop')) {
                        closeConfirmation();
                    }
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDeviceHistoryModal();
                    closeConfirmation();
                }
            });

            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
            }

            setTimeout(() => {
                showToast('Welcome to Device Assignment History', 'info', 3000);
            }, 1000);
        });
    </script>
</body>

</html>