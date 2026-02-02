<?php
session_start();
require_once "./config/database.php";

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("Database connection failed");
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$category_filter = isset($_GET['category']) ? (int) $_GET['category'] : 0;

// Build WHERE clause
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

// Fetch devices for export
$query = mysqli_prepare($conn, "
    SELECT 
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
");

if (!empty($params)) {
    mysqli_stmt_bind_param($query, $param_types, ...$params);
}

mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=device_history_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write headers
fputcsv($output, [
    'Asset Tag',
    'Device Type',
    'Brand',
    'Model',
    'Serial Number',
    'Category',
    'Status',
    'Condition',
    'Created Date',
    'Total Assignments',
    'Unique Users',
    'First Assignment',
    'Last Assignment'
]);

// Write data
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['asset_tag'],
        $row['device_type'],
        $row['brand_name'],
        $row['model'],
        $row['serial_number'],
        $row['category_name'],
        $row['status'],
        $row['condition'],
        $row['created_at'],
        $row['total_assignments'],
        $row['total_users'],
        $row['first_assigned'],
        $row['last_assigned']
    ]);
}

fclose($output);
mysqli_stmt_close($query);
exit;