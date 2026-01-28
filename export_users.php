<?php
require_once "./config/database.php";

$db = new Database();
$conn = $db->getConnection();

// Force download as CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// CSV headers
fputcsv($output, [
    'ID',
    'First Name',
    'Last Name',
    'Email',
    'Role',
    'Status',
    'Created At',
    'Updated At'
]);

$query = "
    SELECT id, firstname, lastname, email, role, status, created_at, updated_at
    FROM users
    ORDER BY id DESC
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;
