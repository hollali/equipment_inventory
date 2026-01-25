<?php
require_once "./config/database.php";

$db = new Database();
$conn = $db->getConnection();

$q = $_GET['q'] ?? '';
$q = strtolower(trim($q));

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = "%$q%";

$stmt = $conn->prepare("
    SELECT DISTINCT department_name, department_code
    FROM departments
    WHERE LOWER(department_name) LIKE ?
       OR LOWER(department_code) LIKE ?
    LIMIT 5
");
$stmt->bind_param("ss", $like, $like);
$stmt->execute();

$data = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
