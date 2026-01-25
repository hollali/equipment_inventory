<?php
require_once "../config/database.php";
$db = new Database();
$conn = $db->getConnection();

$where = [];
$params = [];
$types = "";

/* Search */
if (!empty($_GET['q'])) {
    $where[] = "(i.asset_tag LIKE ? OR i.model LIKE ? OR i.assigned_user LIKE ?)";
    $q = "%" . $_GET['q'] . "%";
    $params = array_merge($params, [$q, $q, $q]);
    $types .= "sss";
}

/* Status */
if (!empty($_GET['status'])) {
    $where[] = "i.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

/* Department */
if (!empty($_GET['department_id'])) {
    $where[] = "i.department_id = ?";
    $params[] = $_GET['department_id'];
    $types .= "i";
}

/* Location */
if (!empty($_GET['location_id'])) {
    $where[] = "i.location_id = ?";
    $params[] = $_GET['location_id'];
    $types .= "i";
}

/* Date */
if (!empty($_GET['date'])) {
    $where[] = "DATE(i.updated_at) = ?";
    $params[] = $_GET['date'];
    $types .= "s";
}

/* Pagination */
$page = max((int) ($_GET['page'] ?? 1), 1);
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "
    SELECT i.*, d.department_name, l.location_name
    FROM inventory_items i
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY i.updated_at DESC LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
if ($params)
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* Render rows */
while ($row = $result->fetch_assoc()):
    ?>
    <tr class="hover:bg-gray-50">
        <td>
            <?= htmlspecialchars($row['asset_tag']) ?>
        </td>
        <td>
            <?= htmlspecialchars($row['assigned_user']) ?>
        </td>
        <td>
            <?= htmlspecialchars($row['department_name']) ?>
        </td>
        <td>
            <?= htmlspecialchars($row['location_name']) ?>
        </td>
        <td>
            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))) ?>
        </td>
    </tr>
<?php endwhile; ?>