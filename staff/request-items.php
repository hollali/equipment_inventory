<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "../config/database.php";

/* ================== DB ================== */
$db = new Database();
$conn = $db->getConnection();
if (!$conn) {
    die("Database connection failed");
}

/* ================== PAGINATION ================== */
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

/* ================== SEARCH ================== */
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$term = "%$search%";

/* ================== COUNT ================== */
if ($search !== "") {
    $countSql = "
        SELECT COUNT(*) AS total
        FROM inventory_items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.item_name LIKE ? OR i.item_code LIKE ? OR c.category_name LIKE ?
    ";
    $countStmt = mysqli_prepare($conn, $countSql);
    mysqli_stmt_bind_param($countStmt, "sss", $term, $term, $term);
} else {
    $countSql = "SELECT COUNT(*) AS total FROM inventory_items";
    $countStmt = mysqli_prepare($conn, $countSql);
}

mysqli_stmt_execute($countStmt);
$totalRows = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
$totalPages = ceil($totalRows / $limit);

/* ================== FETCH DATA ================== */
if ($search !== "") {
    $dataSql = "
        SELECT 
            i.item_name,
            i.item_code,
            i.quantity,
            i.status,
            i.created_at,
            c.category_name
        FROM inventory_items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.item_name LIKE ? OR i.item_code LIKE ? OR c.category_name LIKE ?
        ORDER BY i.created_at DESC
        LIMIT ?, ?
    ";
    $stmt = mysqli_prepare($conn, $dataSql);
    mysqli_stmt_bind_param($stmt, "sssii", $term, $term, $term, $offset, $limit);
} else {
    $dataSql = "
        SELECT 
            i.item_name,
            i.item_code,
            i.quantity,
            i.status,
            i.created_at,
            c.category_name
        FROM inventory_items i
        LEFT JOIN categories c ON i.category_id = c.id
        ORDER BY i.created_at DESC
        LIMIT ?, ?
    ";
    $stmt = mysqli_prepare($conn, $dataSql);
    mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Request Inventory Items</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/view-staff-inventory.css">
    <link rel="stylesheet" href="../css/sidebar.css">
</head>

<body>

    <!-- Sidebar -->
    <?php include './includes/sidebar.php'; ?>
    <!-- Main Container -->
    <div class="container">

        <div class="page-header">
            <h2>📥 Request Items</h2>
            <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <!-- Search Form -->
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search item, code, or category..."
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>

        <!-- Inventory Table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Added On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $i = $offset + 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <?= $i++ ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['item_name']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['item_code']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['category_name'] ?? '—') ?>
                                </td>
                                <td>
                                    <?= (int) $row['quantity'] ?>
                                </td>
                                <td>
                                    <span class="status <?= $row['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date("d M Y", strtotime($row['created_at'])) ?>
                                </td>
                                <td>
                                    <form method="POST" action="submit-request.php">
                                        <input type="hidden" name="item_code"
                                            value="<?= htmlspecialchars($row['item_code']) ?>">
                                        <button type="submit" class="btn-request">Request</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty">No inventory items found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>" class="<?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>