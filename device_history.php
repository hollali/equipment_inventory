<?php
session_start();

/* ================== ERROR REPORTING ================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ================== DB CONNECTION ================== */
require_once "./config/database.php";

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    die("Database connection failed");
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

$topDevices = [];
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

$topUsers = [];
if ($topUsersQuery) {
    while ($row = mysqli_fetch_assoc($topUsersQuery)) {
        $topUsers[] = $row;
    }
}

/* ================== FETCH DEVICE DETAILS FOR MODAL ================== */
$device_details = null;
$assignment_history = [];

if (isset($_GET['get_device_details']) && is_numeric($_GET['get_device_details'])) {
    $device_id = (int) $_GET['get_device_details'];

    $device_query = mysqli_prepare($conn, "
        SELECT 
            i.id,
            i.asset_tag,
            i.device_type,
            i.model,
            i.serial_number,
            i.status,
            i.condition,
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

    if (!$device_query) {
        if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database query preparation failed: ' . mysqli_error($conn)]);
            exit;
        }
    }

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
                TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW())) as days_assigned
            FROM device_user_assignments dua
            JOIN users u ON dua.user_id = u.id
            WHERE dua.inventory_id = ?
            ORDER BY dua.assigned_at DESC
        ");

        if ($history_query) {
            mysqli_stmt_bind_param($history_query, "i", $device_id);
            mysqli_stmt_execute($history_query);
            $history_result = mysqli_stmt_get_result($history_query);

            while ($row = mysqli_fetch_assoc($history_result)) {
                $assignment_history[] = $row;
            }
            mysqli_stmt_close($history_query);
        }
    }

    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');

        $response = [
            'success' => true,
            'device' => $device_details,
            'history' => $assignment_history
        ];

        if ($device_details === null) {
            $response['success'] = false;
            $response['error'] = 'Device not found';
        }

        echo json_encode($response);
        exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

/* ================== FETCH ALL DEVICES WITH HISTORY ================== */
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$category_filter = isset($_GET['category']) ? (int) $_GET['category'] : 0;

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(i.asset_tag LIKE ? OR i.device_type LIKE ? OR i.model LIKE ? OR b.brand_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($category_filter)) {
    $where_conditions[] = "i.category_id = ?";
    $params[] = $category_filter;
    $param_types .= 'i';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

/* ================== PAGINATION ================== */
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 25, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

$count_query = mysqli_prepare($conn, "
    SELECT COUNT(DISTINCT i.id) as total
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    $where_sql
");

if (!empty($params)) {
    mysqli_stmt_bind_param($count_query, $param_types, ...$params);
}

mysqli_stmt_execute($count_query);
$count_result = mysqli_stmt_get_result($count_query);
$total_devices = mysqli_fetch_assoc($count_result)['total'] ?? 0;
mysqli_stmt_close($count_query);

$total_pages = ceil($total_devices / $limit);

/* ================== FETCH DEVICES WITH ASSIGNMENT COUNTS ================== */
$devices_query = mysqli_prepare($conn, "
    SELECT 
        i.id,
        i.asset_tag,
        i.device_type,
        i.model,
        i.serial_number,
        i.status,
        i.condition,
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
");

$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    mysqli_stmt_bind_param($devices_query, $param_types, ...$params);
}

mysqli_stmt_execute($devices_query);
$devices_result = mysqli_stmt_get_result($devices_query);
$devices = [];

while ($row = mysqli_fetch_assoc($devices_result)) {
    $devices[] = $row;
}
mysqli_stmt_close($devices_query);

/* ================== FETCH DROPDOWN DATA ================== */
$categories = [];
$categories_result = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name");
if ($categories_result) {
    $categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
}

$statuses = [];
$status_result = mysqli_query($conn, "SELECT DISTINCT status FROM inventory_items ORDER BY status");
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        $statuses[] = $row['status'];
    }
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
        /* Professional Design System - No Gradients */
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.5;
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
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1.25rem;
            border: 1px solid transparent;
        }

        .badge-status-active { background: #dcfce7; color: #166534; border-color: #86efac; }
        .badge-status-in_use { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-status-in_storage { background: #fef9c3; color: #854d0e; border-color: #fde047; }
        .badge-status-repairing { background: #ffedd5; color: #9a3412; border-color: #fdba74; }
        .badge-status-faulty { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .badge-status-retired { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
        
        .badge-condition-new { background: #dcfce7; color: #166534; border-color: #86efac; }
        .badge-condition-good { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-condition-fair { background: #fef9c3; color: #854d0e; border-color: #fde047; }
        .badge-condition-faulty { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }

        .badge-role-admin { background: #f3e8ff; color: #6b21a8; border-color: #d8b4fe; }
        .badge-role-user { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-status-user-active { background: #dcfce7; color: #166534; border-color: #86efac; }
        .badge-status-user-inactive { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }

        /* Cards */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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
        }

        .table tr:last-child td {
            border-bottom: none;
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
        }

        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.875rem;
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
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23475569' stroke-linecap='round' stroke-linecap='round' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
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
        }

        .stat-icon-blue { background: #dbeafe; color: #1e40af; }
        .stat-icon-green { background: #dcfce7; color: #166534; }
        .stat-icon-purple { background: #f3e8ff; color: #6b21a8; }
        .stat-icon-amber { background: #fef3c7; color: #92400e; }

        /* Top Items Cards - No hover effects */
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
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal {
            background: var(--surface);
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            background: #1e293b;
            border-bottom: 1px solid #334155;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 130px);
        }

        .modal-footer {
            padding: 1.25rem 1.5rem;
            background: var(--surface-hover);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
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
            max-width: 350px;
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
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 200;
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
            width: 90%;
            max-width: 400px;
            animation: fadeIn 0.2s ease-out;
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

        @media (max-width: 1024px) {
            .grid-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .grid-top {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .grid-stats {
                grid-template-columns: 1fr;
            }
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

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
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

        /* Fix for modal buttons */
        .modal-footer .btn {
            min-width: 80px;
        }

        /* Fix for status display in modal */
        .status-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        /* Fix for top users section - REMOVED HOVER EFFECTS */
        .user-info {
            min-width: 0;
            flex: 1;
        }

        .user-badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .user-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
        }

        /* Remove any hover effects from top items */
        .top-item:hover {
            background: var(--surface);
            border-color: var(--border);
            cursor: default;
        }

        /* Fix for status display in table */
        .status-badge-container {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
    </style>
</head>

<body class="bg-[#f1f5f9]">
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="confirmation-modal hidden">
        <div class="confirmation-backdrop"></div>
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

    <div class="flex">
        <?php include "sidebar.php"; ?>
        <main id="mainContent" class="flex-1 p-6">
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
                $stats_query = mysqli_query($conn, "
                    SELECT 
                        COUNT(DISTINCT i.id) as total_devices,
                        COUNT(DISTINCT dua.user_id) as total_users,
                        COUNT(dua.id) as total_assignments,
                        AVG(TIMESTAMPDIFF(DAY, dua.assigned_at, COALESCE(dua.returned_at, NOW()))) as avg_assignment_days
                    FROM inventory_items i
                    LEFT JOIN device_user_assignments dua ON i.id = dua.inventory_id
                ");
                $stats = mysqli_fetch_assoc($stats_query);

                $stat_icons = ['blue', 'green', 'purple', 'amber'];
                $stat_items = [
                    [
                        'title' => 'Total Devices',
                        'value' => $stats['total_devices'] ?? 0,
                        'icon' => 'fa-laptop',
                        'description' => 'Devices in system'
                    ],
                    [
                        'title' => 'Total Users',
                        'value' => $stats['total_users'] ?? 0,
                        'icon' => 'fa-users',
                        'description' => 'Unique users assigned'
                    ],
                    [
                        'title' => 'Total Assignments',
                        'value' => $stats['total_assignments'] ?? 0,
                        'icon' => 'fa-exchange-alt',
                        'description' => 'All assignments'
                    ],
                    [
                        'title' => 'Avg. Assignment',
                        'value' => $stats['avg_assignment_days'] ? round($stats['avg_assignment_days'], 1) . ' days' : 'N/A',
                        'icon' => 'fa-calendar-alt',
                        'description' => 'Average duration'
                    ]
                ];
                ?>

                <?php foreach ($stat_items as $index => $stat): ?>
                    <div class="stat-card animate-fade-in">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-medium text-[#64748b] uppercase tracking-wider"><?= $stat['title'] ?></p>
                                <p class="text-2xl font-bold text-[#0f172a] mt-1"><?= $stat['value'] ?></p>
                                <p class="text-xs text-[#64748b] mt-1"><?= $stat['description'] ?></p>
                            </div>
                            <div class="stat-icon stat-icon-<?= $stat_icons[$index] ?>">
                                <i class="fas <?= $stat['icon'] ?>"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Top Devices and Users Section -->
            <div class="grid-top mb-6">
                <!-- Top 5 Most Assigned Devices -->
                <div class="card animate-fade-in">
                    <div class="card-header flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold text-[#0f172a]">Top 5 Most Assigned Devices</h2>
                            <p class="text-xs text-[#64748b] mt-0.5">Highest assignment counts</p>
                        </div>
                        <div class="w-10 h-10 bg-[#dbeafe] rounded flex items-center justify-center">
                            <i class="fas fa-trophy text-[#1e40af]"></i>
                        </div>
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
                                    $deviceIconColor = match ($device['device_type']) {
                                        'Laptop' => 'device-icon-blue',
                                        'Desktop' => 'device-icon-purple',
                                        'Tablet' => 'device-icon-green',
                                        'Mobile' => 'device-icon-amber',
                                        'Monitor' => 'device-icon-gray',
                                        default => 'device-icon-gray'
                                    };
                                    ?>
                                    <div class="top-item">
                                        <div class="flex items-center gap-3">
                                            <div class="rank-badge <?= $rankClass ?>">
                                                <?= $rank ?>
                                            </div>
                                            <div class="device-icon <?= $deviceIconColor ?>">
                                                <i class="fas fa-laptop"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-start justify-between">
                                                    <div class="truncate pr-2">
                                                        <p class="font-medium text-[#0f172a]">
                                                            <?= htmlspecialchars($device['asset_tag']) ?>
                                                        </p>
                                                        <p class="text-xs text-[#64748b] truncate">
                                                            <?= htmlspecialchars($device['device_type']) ?> •
                                                            <?= htmlspecialchars($device['model']) ?>
                                                        </p>
                                                    </div>
                                                    <p class="font-bold text-[#0f172a] whitespace-nowrap">
                                                        <?= $device['assignment_count'] ?>
                                                    </p>
                                                </div>
                                                <div class="flex items-center justify-between mt-2 text-xs">
                                                    <span class="text-[#64748b]">Unique users: <?= $device['user_count'] ?></span>
                                                    <span class="badge badge-status-<?= $device['status'] ?> whitespace-nowrap">
                                                        <?= htmlspecialchars($statusLabels[$device['status']] ?? ucfirst(str_replace('_', ' ', $device['status']))) ?>
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

                <!-- Top 5 Users with Most Assignments - FIXED: Removed hover effects -->
                <div class="card animate-fade-in">
                    <div class="card-header flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold text-[#0f172a]">Top 5 Active Users</h2>
                            <p class="text-xs text-[#64748b] mt-0.5">Highest assignment counts</p>
                        </div>
                        <div class="w-10 h-10 bg-[#dcfce7] rounded flex items-center justify-center">
                            <i class="fas fa-users text-[#166534]"></i>
                        </div>
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
                                    $roleClass = $user['role'] === 'admin' ? 'badge-role-admin' : 'badge-role-user';
                                    $statusClass = $user['user_status'] === 'active' ? 'badge-status-user-active' : 'badge-status-user-inactive';
                                    $statusText = $user['user_status'] === 'active' ? 'Active' : 'Inactive';
                                    $avatarInitials = strtoupper(substr($user['firstname'] ?? '', 0, 1) . substr($user['lastname'] ?? '', 0, 1));
                                    ?>
                                    <div class="top-item">
                                        <div class="flex items-center gap-3">
                                            <div class="rank-badge <?= $rankClass ?>">
                                                <?= $rank ?>
                                            </div>
                                            <div class="user-avatar">
                                                <?= $avatarInitials ?: '?' ?>
                                            </div>
                                            <div class="user-info">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <p class="font-medium text-[#0f172a]">
                                                            <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                                        </p>
                                                        <p class="text-xs text-[#64748b]">
                                                            <?= htmlspecialchars($user['email']) ?>
                                                        </p>
                                                    </div>
                                                    <p class="font-bold text-[#0f172a] ml-2">
                                                        <?= $user['assignment_count'] ?>
                                                    </p>
                                                </div>
                                                <div class="user-badges">
                                                    <span class="badge <?= $roleClass ?>">
                                                        <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                                    </span>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <?= $statusText ?>
                                                    </span>
                                                    <span class="text-xs text-[#64748b]">
                                                        Devices: <?= $user['device_count'] ?>
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
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search by asset tag, model, or brand..."
                                class="input-field input-with-icon">
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="select-field">
                            <option value="">All Status</option>
                            <?php foreach ($statusLabels as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $status_filter == $key ? 'selected' : '' ?>>
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
                                <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name']) ?>
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
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Devices Table -->
            <div class="card animate-fade-in">
                <div class="card-header flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-[#0f172a]">Device Assignment History</h2>
                        <p class="text-xs text-[#64748b] mt-0.5">Showing <?= count($devices) ?> of <?= $total_devices ?> devices</p>
                    </div>
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
                                <th>Status / Condition</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($devices)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-12">
                                        <div class="flex flex-col items-center">
                                            <div class="w-12 h-12 bg-[#f1f5f9] rounded flex items-center justify-center mb-3">
                                                <i class="fas fa-search text-[#64748b]"></i>
                                            </div>
                                            <p class="text-[#64748b]">No devices found matching your criteria</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($devices as $device): ?>
                                    <?php
                                    $assignment_percentage = min(100, ($device['total_assignments'] * 20));
                                    
                                    // Fix for status display
                                    $status_key = $device['status'] ?? '';
                                    $status_display = isset($statusLabels[$status_key]) 
                                        ? $statusLabels[$status_key] 
                                        : ucfirst(str_replace('_', ' ', $status_key));
                                    
                                    // Fix for condition display
                                    $condition_value = $device['condition'] ?? '';
                                    $condition_display = isset($conditionLabels[$condition_value]) 
                                        ? $conditionLabels[$condition_value] 
                                        : ucfirst(str_replace('_', ' ', $condition_value));
                                    
                                    // Generate CSS classes
                                    $status_class = 'badge-status-' . str_replace('_', '-', $status_key);
                                    $condition_class = 'badge-condition-' . strtolower(str_replace(' ', '-', $condition_value));
                                    
                                    // Default classes if empty
                                    if (empty($status_key)) {
                                        $status_class = 'bg-[#f1f5f9] text-[#475569] border-[#cbd5e1]';
                                        $status_display = 'Unknown';
                                    }
                                    
                                    if (empty($condition_value)) {
                                        $condition_class = 'bg-[#f1f5f9] text-[#475569] border-[#cbd5e1]';
                                        $condition_display = 'Unknown';
                                    }
                                    ?>
                                    <tr>
                                        <!-- Device Info -->
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="device-icon device-icon-blue">
                                                    <i class="fas fa-laptop"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-[#0f172a]"><?= htmlspecialchars($device['asset_tag']) ?></p>
                                                    <p class="text-xs text-[#64748b]">
                                                        <?= htmlspecialchars($device['device_type']) ?>
                                                        <?php if ($device['model']): ?>• <?= htmlspecialchars($device['model']) ?><?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Assignments Count -->
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="w-20">
                                                    <div class="progress">
                                                        <div class="progress-bar" style="width: <?= $assignment_percentage ?>%"></div>
                                                    </div>
                                                </div>
                                                <span class="font-medium text-[#0f172a]"><?= $device['total_assignments'] ?></span>
                                            </div>
                                        </td>

                                        <!-- Users Count -->
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 bg-[#f1f5f9] rounded flex items-center justify-center">
                                                    <i class="fas fa-user text-[#64748b] text-xs"></i>
                                                </div>
                                                <span class="font-medium text-[#0f172a]"><?= $device['total_users'] ?></span>
                                            </div>
                                        </td>

                                        <!-- First Assigned -->
                                        <td>
                                            <?php if ($device['first_assigned']): ?>
                                                <p class="text-sm text-[#0f172a]"><?= date('M d, Y', strtotime($device['first_assigned'])) ?></p>
                                                <p class="text-xs text-[#64748b]"><?= date('h:i A', strtotime($device['first_assigned'])) ?></p>
                                            <?php else: ?>
                                                <span class="text-sm text-[#64748b] italic">Never assigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Last Assigned -->
                                        <td>
                                            <?php if ($device['last_assigned']): ?>
                                                <p class="text-sm text-[#0f172a]"><?= date('M d, Y', strtotime($device['last_assigned'])) ?></p>
                                                <p class="text-xs text-[#64748b]"><?= date('h:i A', strtotime($device['last_assigned'])) ?></p>
                                            <?php else: ?>
                                                <span class="text-sm text-[#64748b] italic">Not applicable</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status - FIXED: Now displays correctly -->
                                        <td>
                                            <div class="status-badge-container">
                                                <span class="badge <?= $status_class ?>">
                                                    <?= htmlspecialchars($status_display) ?>
                                                </span>
                                                
                                                <?php if (!empty($device['condition'])): ?>
                                                    <span class="badge <?= $condition_class ?>">
                                                        <?= htmlspecialchars($condition_display) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <button
                                                onclick="openDeviceHistoryModal(<?= $device['id'] ?>, '<?= htmlspecialchars(addslashes($device['asset_tag'])) ?>')"
                                                class="btn btn-primary btn-sm">
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
                        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                            <p class="text-sm text-[#64748b]">
                                Page <?= $page ?> of <?= $total_pages ?> • Showing <?= count($devices) ?> of <?= $total_devices ?> devices
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
                                <select onchange="changeItemsPerPage(this)" class="select-field !w-auto !py-1 !text-sm">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <span class="text-sm text-[#64748b]">per page</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Device History Modal - FIXED: Buttons now display properly -->
    <div id="deviceHistoryModal" class="modal-backdrop hidden">
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
                            <p class="text-[#94a3b8] text-sm" id="modalSubtitle">Loading...</p>
                        </div>
                    </div>
                    <button onclick="closeDeviceHistoryModal()" class="text-[#94a3b8] hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <div id="loadingSpinner" class="text-center py-12">
                    <div class="spinner mx-auto mb-4"></div>
                    <p class="text-[#64748b]">Loading device history...</p>
                </div>

                <div id="modalContent" class="hidden">
                    <!-- Device Info Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Asset Tag</p>
                            <p class="font-mono font-medium text-[#0f172a]" id="modalAssetTag"></p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Brand & Model</p>
                            <p class="font-medium text-[#0f172a]" id="modalBrand"></p>
                            <p class="text-sm text-[#64748b]" id="modalModel"></p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Serial Number</p>
                            <p class="font-mono font-medium text-[#0f172a]" id="modalSerial"></p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Category</p>
                            <p class="font-medium text-[#0f172a]" id="modalCategory"></p>
                        </div>
                    </div>

                    <!-- Status & Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-2">Current Status</p>
                            <div id="modalStatus" class="status-container"></div>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Total Assignments</p>
                            <p class="text-2xl font-bold text-[#0f172a]" id="modalTotalAssignments"></p>
                            <p class="text-sm text-[#64748b]" id="modalUniqueUsers"></p>
                        </div>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <p class="text-xs font-medium text-[#475569] mb-1">Current Assignment</p>
                            <p class="font-medium text-[#0f172a]" id="modalCurrentAssignment"></p>
                            <p class="text-sm text-[#64748b]" id="modalAssignmentDate"></p>
                        </div>
                    </div>

                    <!-- Assignment History -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <h3 class="font-semibold text-[#0f172a]">Assignment History</h3>
                            <p class="text-sm text-[#64748b]" id="timelineDescription"></p>
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

                <div id="noHistoryMessage" class="hidden text-center py-12">
                    <div class="w-16 h-16 bg-[#f1f5f9] rounded flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-history text-[#64748b] text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-[#0f172a] mb-2">No Assignment History</h3>
                    <p class="text-[#64748b]">This device has never been assigned to any user.</p>
                </div>
            </div>

            <!-- Modal Footer - FIXED: Buttons now display properly -->
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

    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // Status labels mapping
        const statusLabels = <?= json_encode($statusLabels) ?>;
        const conditionLabels = <?= json_encode($conditionLabels) ?>;

        // Status colors mapping for modal
        const statusColors = {
            'active': 'badge-status-active',
            'in_use': 'badge-status-in_use',
            'in_storage': 'badge-status-in_storage',
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
            document.getElementById('confirmationTitle').textContent = title;
            document.getElementById('confirmationMessage').textContent = message;
            document.getElementById('confirmButton').textContent = confirmText;

            currentConfirmationCallback = confirmCallback;
            document.getElementById('confirmationModal').classList.remove('hidden');
        }

        function closeConfirmation() {
            document.getElementById('confirmationModal').classList.add('hidden');
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
                    url.searchParams.set('page', 1);
                    window.location.href = url.toString();
                    showToast('Items per page updated', 'info');
                },
                'Continue'
            );
        }

        // Export function
        function exportToCSV() {
            const search = "<?= htmlspecialchars($search) ?>";
            const status = "<?= htmlspecialchars($status_filter) ?>";
            const category = "<?= $category_filter ?>";

            let exportUrl = 'export_device_history.php?';
            if (search) exportUrl += 'search=' + encodeURIComponent(search) + '&';
            if (status) exportUrl += 'status=' + encodeURIComponent(status) + '&';
            if (category) exportUrl += 'category=' + category + '&';

            window.location.href = exportUrl;
        }

        // Date formatting helper
        function formatDate(dateString) {
            if (!dateString) return 'Unknown date';

            try {
                const date = new Date(dateString);
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
                return 'Invalid date';
            }
        }

        // Modal functions
        let currentDeviceId = null;

        function openDeviceHistoryModal(deviceId, assetTag) {
            currentDeviceId = deviceId;
            const modal = document.getElementById('deviceHistoryModal');
            const subtitle = document.getElementById('modalSubtitle');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const modalContent = document.getElementById('modalContent');
            const noHistoryMessage = document.getElementById('noHistoryMessage');

            subtitle.textContent = 'Loading history for ' + assetTag;

            modal.classList.remove('hidden');
            loadingSpinner.classList.remove('hidden');
            modalContent.classList.add('hidden');
            noHistoryMessage.classList.add('hidden');

            fetchDeviceHistory(deviceId, assetTag);
        }

        function closeDeviceHistoryModal() {
            const modal = document.getElementById('deviceHistoryModal');
            modal.classList.add('hidden');
        }

        function fetchDeviceHistory(deviceId, assetTag) {
            const xhr = new XMLHttpRequest();
            const url = `device_history.php?get_device_details=${deviceId}&ajax=1&_=${Date.now()}`;

            xhr.open('GET', url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    document.getElementById('loadingSpinner').classList.add('hidden');
                    
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);

                            if (data.success === false || data.error) {
                                showToast(data.error || 'Error loading device history', 'error');
                                showNoHistory();
                                return;
                            }

                            if (data.device && Object.keys(data.device).length > 0) {
                                populateModal(data.device, data.history || []);
                                showToast(`Loaded history for ${assetTag}`, 'success');
                            } else {
                                showNoHistory();
                            }
                        } catch (e) {
                            showToast('Invalid response from server', 'error');
                            showNoHistory();
                        }
                    } else {
                        showToast('Server error: ' + xhr.status, 'error');
                        showNoHistory();
                    }
                }
            };

            xhr.onerror = function () {
                document.getElementById('loadingSpinner').classList.add('hidden');
                showToast('Network error. Please check your connection.', 'error');
                showNoHistory();
            };

            xhr.send();
        }

        function populateModal(device, history) {
            // Update basic device info
            document.getElementById('modalAssetTag').textContent = device.asset_tag || 'N/A';
            document.getElementById('modalBrand').textContent = device.brand_name || 'N/A';
            document.getElementById('modalModel').textContent = device.model || 'N/A';
            document.getElementById('modalSerial').textContent = device.serial_number || 'N/A';
            document.getElementById('modalCategory').textContent = device.category_name || 'N/A';
            document.getElementById('modalTotalAssignments').textContent = device.total_assignments || 0;
            document.getElementById('modalUniqueUsers').textContent = (device.unique_users || 0) + ' unique users';

            // Update status with proper formatting
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
                <span class="badge ${statusClass}">${statusText}</span>
                <span class="badge ${conditionClass}">${conditionText}</span>
            `;

            // Find current assignment
            const currentAssignment = Array.isArray(history) ?
                history.find(a => a.status === 'assigned') : null;

            if (currentAssignment) {
                document.getElementById('modalCurrentAssignment').textContent =
                    (currentAssignment.firstname || '') + ' ' + (currentAssignment.lastname || '');
                document.getElementById('modalAssignmentDate').textContent =
                    'Since ' + formatDate(currentAssignment.assigned_at);
            } else {
                document.getElementById('modalCurrentAssignment').textContent = 'Not assigned';
                document.getElementById('modalAssignmentDate').textContent = 'Available for assignment';
            }

            // Update timeline description
            document.getElementById('modalSubtitle').textContent = `History for ${device.asset_tag}`;
            document.getElementById('timelineDescription').textContent =
                `Showing ${history.length} assignment${history.length !== 1 ? 's' : ''} for ${device.asset_tag}`;

            // Populate assignment history
            populateAssignmentHistory(history || []);

            // Populate statistics
            populateStatistics(history || []);

            // Hide loading, show content
            document.getElementById('modalContent').classList.remove('hidden');
        }

        function populateAssignmentHistory(history) {
            const historyContainer = document.getElementById('assignmentHistory');

            if (!Array.isArray(history) || history.length === 0) {
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
                const isActive = assignment.status === 'assigned';
                const assignedDate = new Date(assignment.assigned_at);
                const returnedDate = assignment.returned_at ? new Date(assignment.returned_at) : null;

                let assignedDateStr = 'Invalid date';
                let returnedDateStr = 'N/A';

                try {
                    if (!isNaN(assignedDate.getTime())) {
                        assignedDateStr = assignedDate.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }

                    if (returnedDate && !isNaN(returnedDate.getTime())) {
                        returnedDateStr = returnedDate.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                } catch (e) {
                    console.error('Date formatting error:', e);
                }

                const daysAssigned = assignment.days_assigned || 0;
                const durationText = daysAssigned > 0 ?
                    `${daysAssigned} day${daysAssigned > 1 ? 's' : ''}` :
                    'Less than a day';

                historyHTML += `
                    <div class="assignment-card">
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-start gap-4">
                                    <div class="user-avatar flex-shrink-0">
                                        ${assignment.firstname ? assignment.firstname.charAt(0).toUpperCase() : '?'}
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="flex flex-wrap items-center gap-3 mb-3">
                                            <span class="assignment-status ${isActive ? 'status-active' : 'status-completed'}">
                                                <i class="fas ${isActive ? 'fa-user-check' : 'fa-user-times'} mr-1"></i>
                                                ${isActive ? 'ACTIVE' : 'COMPLETED'}
                                            </span>
                                            <span class="text-xs text-[#64748b]">
                                                <i class="far fa-calendar mr-1"></i>${assignedDateStr}
                                            </span>
                                            <span class="text-xs text-[#64748b]">
                                                <i class="far fa-clock mr-1"></i>${durationText}
                                            </span>
                                        </div>
                                        
                                        <h4 class="font-medium text-[#0f172a] mb-2">
                                            ${assignment.firstname || ''} ${assignment.lastname || ''}
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
                                                    Assigned: ${assignedDateStr}
                                                </p>
                                                ${returnedDate ? `
                                                    <p class="text-[#64748b] mt-1">
                                                        <i class="fas fa-calendar-times mr-2 text-xs"></i>
                                                        Returned: ${returnedDateStr}
                                                    </p>
                                                ` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:text-right">
                                <p class="text-xs text-[#64748b] mb-1">
                                    <i class="fas fa-hashtag"></i> Assignment #${index + 1}
                                </p>
                                <span class="badge ${isActive ? 'badge-status-active' : 'bg-[#f1f5f9] text-[#475569] border-[#cbd5e1]'}">
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

            if (!Array.isArray(history) || history.length === 0) {
                statsContainer.innerHTML = '<p class="text-[#64748b] text-center">No statistics available</p>';
                return;
            }

            // Calculate statistics
            let totalDays = 0;
            let activeAssignments = 0;
            let completedAssignments = 0;
            let uniqueUserIds = [];
            let userCounts = {};
            let totalActiveDays = 0;
            let longestAssignment = 0;
            let shortestAssignment = Infinity;

            history.forEach(assignment => {
                if (assignment.status === 'assigned') {
                    activeAssignments++;
                    totalActiveDays += parseInt(assignment.days_assigned) || 0;
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
                            name: `${assignment.firstname || ''} ${assignment.lastname || ''}`.trim() || 'Unknown User',
                            count: 0,
                            totalDays: 0
                        };
                    }
                    userCounts[assignment.user_id].count++;
                    userCounts[assignment.user_id].totalDays += daysAssigned;
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

                    statsHTML += `
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="user-avatar w-8 h-8 text-xs">
                                        ${user.name.charAt(0).toUpperCase()}
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

            if (history.length > 0) {
                const firstAssignment = history[history.length - 1];
                const lastAssignment = history[0];

                statsHTML += `
                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-[#0f172a] mb-3">Assignment Timeline</h4>
                        <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded p-4">
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-[#64748b]">First Assignment:</span>
                                <span class="font-medium text-[#0f172a]">${formatDate(firstAssignment.assigned_at)}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm mb-2">
                                <span class="text-[#64748b]">Last Assignment:</span>
                                <span class="font-medium text-[#0f172a]">${formatDate(lastAssignment.assigned_at)}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-[#64748b]">Total Time Period:</span>
                                <span class="font-medium text-[#0f172a]">${Math.round(totalDays)} days</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            statsContainer.innerHTML = statsHTML;
        }

        function showNoHistory() {
            document.getElementById('loadingSpinner').classList.add('hidden');
            document.getElementById('noHistoryMessage').classList.remove('hidden');
        }

        function printModalContent() {
            showConfirmation(
                'Print Device History',
                'Do you want to print the device assignment history?',
                () => {
                    const printContent = document.getElementById('modalContent').innerHTML;
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Device History - Print</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; color: #0f172a; }
                                .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
                                .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; border: 1px solid #e2e8f0; }
                                @media print {
                                    body { font-size: 12px; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="print-header">
                                <h1>Device Assignment History</h1>
                                <p>Generated on ${new Date().toLocaleDateString()}</p>
                            </div>
                            ${printContent}
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
        document.getElementById('deviceHistoryModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeDeviceHistoryModal();
            }
        });

        // Close confirmation modal when clicking outside
        document.getElementById('confirmationModal').addEventListener('click', function (e) {
            if (e.target.classList.contains('confirmation-backdrop')) {
                closeConfirmation();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDeviceHistoryModal();
                closeConfirmation();
            }
        });

        // Fix for loading spinner initially showing
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
            }

            // Hide loading spinner initially
            document.getElementById('loadingSpinner').classList.add('hidden');

            setTimeout(() => {
                showToast('Welcome to Device Assignment History', 'info', 3000);
            }, 1000);
        });
    </script>
</body>

</html>