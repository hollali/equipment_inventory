<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect admin page */
/*if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}*/

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

    $params = [];

    if (!empty($_GET['role'])) {
        $sql .= " AND role = ?";
        $params[] = $_GET['role'];
    }

    if (!empty($_GET['status'])) {
        $sql .= " AND status = ?";
        $params[] = $_GET['status'];
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
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
                   c.category_name, s.supplier_name,
                   i.quantity, i.min_quantity,
                   i.unit_price,
                   (i.quantity * i.unit_price) AS total_value,
                   i.location, i.status, i.created_at
            FROM inventory i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE 1=1";

    $params = [];

    if (!empty($_GET['category_id'])) {
        $sql .= " AND i.category_id = ?";
        $params[] = $_GET['category_id'];
    }

    if (!empty($_GET['status'])) {
        $sql .= " AND i.status = ?";
        $params[] = $_GET['status'];
    }

    $sql .= " ORDER BY i.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
