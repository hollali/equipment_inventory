<?php
session_start();
require_once "../config/database.php";
require_once __DIR__ . "/includes/sidebar.php";

// 🔐 Protect page: redirect if not logged in
/*if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}*/

/* ================== DB ================== */
$db = new Database();
$conn = $db->getConnection();
if (!$conn) {
    die("Database connection failed");
}

/* ================== FETCH USER INFO ================== */
$userId = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT full_name, email, role, status, created_at, last_login FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    die("User profile not found.");
}

$user = mysqli_fetch_assoc($result);

/* ================== FETCH USER REQUESTS ================== */
$limit = 10; // requests per page
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total requests
$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM requests WHERE user_id = ?");
mysqli_stmt_bind_param($countStmt, "i", $userId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRequests = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRequests / $limit);

// Fetch requests
$reqStmt = mysqli_prepare($conn, "SELECT request_id, title, status, created_at FROM requests WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($reqStmt, "iii", $userId, $limit, $offset);
mysqli_stmt_execute($reqStmt);
$reqResult = mysqli_stmt_get_result($reqStmt);
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

    <!-- Sidebar -->
    <?php echo $sidebar; ?>

    <div class="container">
        <div class="page-header">
            <h2>👤 My Profile</h2>
            <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <!-- User Profile -->
        <div class="profile-card">
            <div class="profile-field">
                <span class="label">Full Name:</span>
                <span class="value"><?= htmlspecialchars($user['full_name']) ?></span>
            </div>
            <div class="profile-field">
                <span class="label">Email:</span>
                <span class="value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="profile-field">
                <span class="label">Role:</span>
                <span class="value"><?= htmlspecialchars($user['role']) ?></span>
            </div>
            <div class="profile-field">
                <span class="label">Status:</span>
                <span class="value"><?= htmlspecialchars($user['status']) ?></span>
            </div>
            <div class="profile-field">
                <span class="label">Joined On:</span>
                <span class="value"><?= date("d M Y", strtotime($user['created_at'])) ?></span>
            </div>
            <div class="profile-field">
                <span class="label">Last Login:</span>
                <span class="value"><?= date("d M Y H:i", strtotime($user['last_login'])) ?></span>
            </div>

            <a href="edit-profile.php" class="btn-edit">Edit Profile</a>
        </div>

        <!-- User Requests -->
        <h3>📄 My Requests</h3>

        <?php if (mysqli_num_rows($reqResult) > 0): ?>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = $offset + 1; ?>
                    <?php while ($request = mysqli_fetch_assoc($reqResult)): ?>
                        <tr>
                            <td><?= $count++ ?></td>
                            <td><?= htmlspecialchars($request['title']) ?></td>
                            <td><?= htmlspecialchars($request['status']) ?></td>
                            <td><?= date("d M Y H:i", strtotime($request['created_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?= $p ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php else: ?>
            <p>No requests found.</p>
        <?php endif; ?>

    </div>

</body>

</html>