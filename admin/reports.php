<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../config/database.php";

/* 🔌 DB */
$db = new Database();
$conn = $db->getConnection();

/* 📊 Total users */
$total_users_sql = "SELECT COUNT(*) as total FROM users";
$total_users_result = $conn->query($total_users_sql);
$total_users = $total_users_result->fetch_assoc()['total'];

/* 📈 Active & inactive users */
$status_sql = "SELECT status, COUNT(*) as count FROM users GROUP BY status";
$status_result = $conn->query($status_sql);
$status_counts = [];
while ($row = $status_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

/* 🧑‍💼 Users by role */
$role_sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$role_result = $conn->query($role_sql);
$role_counts = [];
while ($row = $role_result->fetch_assoc()) {
    $role_counts[$row['role']] = $row['count'];
}

/* 🔍 Recent activity logs (limit 20) */
$search = trim($_GET['search'] ?? '');
$logs_sql = "SELECT l.id, l.user_id, l.action, l.ip_address, l.created_at, u.username
             FROM activity_log l
             LEFT JOIN users u ON l.user_id = u.id";

if ($search) {
    $logs_sql .= " WHERE u.username LIKE ? OR l.action LIKE ?";
}

$logs_sql .= " ORDER BY l.created_at DESC LIMIT 20";

$stmt = $conn->prepare($logs_sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if ($search) {
    $term = "%$search%";
    $stmt->bind_param("ss", $term, $term);
}

$stmt->execute();
$stmt->store_result();

/* Bind results safely */
$stmt->bind_result($log_id, $log_user_id, $log_action, $log_ip, $log_created, $log_username);

/* Build logs array */
$logs = [];
while ($stmt->fetch()) {
    $logs[] = [
        'id' => $log_id,
        'user_id' => $log_user_id,
        'action' => $log_action,
        'ip_address' => $log_ip,
        'created_at' => $log_created,
        'username' => $log_username
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>System Reports</title>
    <link rel="stylesheet" href="../css/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1>System Reports</h1>
                <p>Summary of users and activities</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <!-- Report Summary Cards -->
        <div class="report-cards">
            <div class="report-card">
                <h2><?php echo $total_users; ?></h2>
                <p>Total Users</p>
            </div>
            <div class="report-card">
                <h2><?php echo $status_counts['active'] ?? 0; ?></h2>
                <p>Active Users</p>
            </div>
            <div class="report-card">
                <h2><?php echo $status_counts['inactive'] ?? 0; ?></h2>
                <p>Inactive Users</p>
            </div>
            <?php foreach ($role_counts as $role => $count): ?>
                <div class="report-card">
                    <h2><?php echo $count; ?></h2>
                    <p><?php echo ucfirst($role); ?>s</p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Logs -->
        <div class="logs-section">
            <div class="search-bar">
                <form method="GET">
                    <input type="text" name="search" placeholder="Search logs..." autocomplete="off"
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-edit"><i class="fa fa-search"></i> Search</button>
                </form>
                <a href="export_reports.php" class="btn-export"><i class="fa fa-file-csv"></i> Export CSV</a>
            </div>

            <table class="logs-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>IP Address</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $row): ?>
                            <tr>
                                <td><?php echo $row['username'] ?? 'System'; ?></td>
                                <td><?php echo htmlspecialchars($row['action']); ?></td>
                                <td><?php echo $row['ip_address']; ?></td>
                                <td><?php echo date("M d, Y H:i", strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">No activity found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>