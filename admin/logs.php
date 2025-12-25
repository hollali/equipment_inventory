<?php
require_once "../config/database.php";
$db = new Database();
$conn = $db->getConnection();

$search = $_GET["search"] ?? "";
$page = max(1, intval($_GET["page"] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "";
$params = [];

if ($search) {
    $where = "WHERE action LIKE ?";
    $params[] = "%$search%";
}

/* Fetch logs */
$sql = "
SELECT l.*, u.username 
FROM activity_logs l
LEFT JOIN users u ON l.user_id = u.id
$where
ORDER BY l.created_at DESC
LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param("s", ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* Count */
$countSql = "SELECT COUNT(*) total FROM activity_logs $where";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param("s", ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()["total"];
$pages = ceil($total / $limit);
?>