<?php
session_start();
require_once "../config/database.php";
require_once __DIR__ . "/includes/sidebar.php";

/* 🔐 Auth guard */
/*if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}*/

/* ================= DB ================= */
$db = new Database();
$conn = $db->getConnection();
if (!$conn) {
    die("Database connection failed");
}

$userId = (int) $_SESSION['user_id'];

/* ================= USER INFO ================= */
$userStmt = mysqli_prepare(
    $conn,
    "SELECT full_name, email, role, status, created_at, last_login
     FROM users
     WHERE id = ?"
);
mysqli_stmt_bind_param($userStmt, "i", $userId);
mysqli_stmt_execute($userStmt);
$userRes = mysqli_stmt_get_result($userStmt);

if (mysqli_num_rows($userRes) === 0) {
    die("User not found");
}
$user = mysqli_fetch_assoc($userRes);

/* ================= PAGINATION ================= */
$limit = 10;
$page = isset($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

/* ================= COUNT REQUESTS ================= */
$countStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM item_requests
     WHERE user_id = ?"
);
mysqli_stmt_bind_param($countStmt, "i", $userId);
mysqli_stmt_execute($countStmt);
$countRes = mysqli_stmt_get_result($countStmt);
$total = (int) mysqli_fetch_assoc($countRes)['total'];
$totalPages = max(1, ceil($total / $limit));

/* ================= FETCH REQUESTS ================= */
$requestStmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        request_code,
        quantity,
        reason,
        status,
        request_at
     FROM item_requests
     WHERE user_id = ?
     ORDER BY request_at DESC
     LIMIT ? OFFSET ?"
);
mysqli_stmt_bind_param($requestStmt, "iii", $userId, $limit, $offset);
mysqli_stmt_execute($requestStmt);
$requestRes = mysqli_stmt_get_result($requestStmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Requests</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/sidebar.css">
</head>

<body>

    <?php echo $sidebar; ?>

    <div class="container">

        <div class="page-header">
            <h2>📄 My Item Requests</h2>
            <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <!-- PROFILE -->
        <div class="profile-card">
            <div class="profile-field"><b>Name:</b> <?= htmlspecialchars($user['full_name']) ?></div>
            <div class="profile-field"><b>Email:</b> <?= htmlspecialchars($user['email']) ?></div>
            <div class="profile-field"><b>Role:</b> <?= htmlspecialchars($user['role']) ?></div>
            <div class="profile-field"><b>Status:</b> <?= htmlspecialchars($user['status']) ?></div>
        </div>

        <!-- REQUESTS -->
        <?php if (mysqli_num_rows($requestRes) > 0): ?>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Qty</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = $offset + 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($requestRes)): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['request_code']) ?></td>
                            <td><?= (int) $row['quantity'] ?></td>
                            <td><?= htmlspecialchars($row['reason']) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td><?= date("d M Y H:i", strtotime($row['request_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- PAGINATION -->
            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <p>No requests found.</p>
        <?php endif; ?>

    </div>

</body>

</html>