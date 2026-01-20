<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect admin page (optional)
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}
*/

$db = new Database();
$conn = $db->getConnection();

/* 📌 Get report type */
$type = $_GET['type'] ?? '';

if (!in_array($type, ['users', 'inventory'])) {
    die("Invalid report type");
}

/* 🧾 CSV Headers */
$filename = $type . "_report_" . date("Y-m-d") . ".csv";
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=$filename");

$output = fopen("php://output", "w");

/* ================= USERS REPORT ================= */
if ($type === 'users') {

    fputcsv($output, [
        'ID',
        'Username',
        'Full Name',
        'Email',
        'Role',
        'Status',
        'Last Login',
        'Created At'
    ]);

    $sql = "SELECT id, username, full_name, email, role, status, last_login, created_at
            FROM users WHERE 1=1";

    $types = "";
    $params = [];

    if (!empty($_GET['role'])) {
        $sql .= " AND role = ?";
        $types .= "s";
        $params[] = $_GET['role'];
    }

    if (!empty($_GET['status'])) {
        $sql .= " AND status = ?";
        $types .= "s";
        $params[] = $_GET['status'];
    }

    $sql .= " ORDER BY id DESC";

    $stmt = mysqli_prepare($conn, $sql);

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }

    mysqli_stmt_close($stmt);
}

/* ================= INVENTORY REPORT ================= */
if ($type === 'inventory') {

    fputcsv($output, [
        'ID',
        'Item Name',
        'Item Code',
        'Category',
        'Supplier',
        'Quantity',
        'Min Quantity',
        'Unit Price',
        'Total Value',
        'Location',
        'Status',
        'Created At'
    ]);

    $sql = "SELECT i.id, i.item_name, i.item_code,
                   c.category_name AS category,
                   s.supplier_name AS supplier,
                   i.quantity, i.min_quantity,
                   i.unit_price,
                   (i.quantity * i.unit_price) AS total_value,
                   i.location, i.status, i.created_at
            FROM inventory i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE 1=1";

    $types = "";
    $params = [];

    if (!empty($_GET['category_id'])) {
        $sql .= " AND i.category_id = ?";
        $types .= "i";
        $params[] = $_GET['category_id'];
    }

    if (!empty($_GET['status'])) {
        $sql .= " AND i.status = ?";
        $types .= "s";
        $params[] = $_GET['status'];
    }

    $sql .= " ORDER BY i.id DESC";

    $stmt = mysqli_prepare($conn, $sql);

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }

    mysqli_stmt_close($stmt);
}

fclose($output);
exit();
