<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page (optional)
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}
*/

/* 🔌 Database */
$db = new Database();
$conn = $db->getConnection();

/* 📦 Total Inventory Items */
$totalItems = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM inventory_items");
if ($row = $result->fetch_assoc()) {
    $totalItems = $row["total"];
}

/* 👤 Total Users */
$totalUsers = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($row = $result->fetch_assoc()) {
    $totalUsers = $row["total"];
}

/* ⚠️ Low Stock Items */
$lowStock = 0;
$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM inventory_items 
    WHERE quantity <= min_quantity
");
if ($row = $result->fetch_assoc()) {
    $lowStock = $row["total"];
}

/* 💰 Total Inventory Value */
$totalValue = 0;
$result = $conn->query("
    SELECT SUM(quantity * unit_price) AS total_value 
    FROM inventory_items
");
if ($row = $result->fetch_assoc()) {
    $totalValue = $row["total_value"] ?? 0;
}

/* 🕒 Recent Activity from activity_log */
$recentActivities = [];
$sql = "
    SELECT a.id, a.user_id, a.action, a.description, a.ip_address, a.created_at, u.full_name AS user_name
    FROM activity_log a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 10
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Parliament Inventory</title>
    <link href="images/logo.png" rel="icon" type="image/x-icon">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<body>
    <div class="dashboard-container">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
                <p>Welcome, Admin</p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-dashboard"></i> Dashboard
                </a>
                <a href="inventory.php" class="nav-item">
                    <i class="fas fa-boxes"></i> Inventory Management
                </a>
                <a href="users.php" class="nav-item">
                    <i class="fas fa-users"></i> User Management
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="categories.php" class="nav-item">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="suppliers.php" class="nav-item">
                    <i class="fas fa-truck"></i> Suppliers
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">

            <header class="content-header">
                <h1>Dashboard Overview</h1>
                <div class="user-info">
                    <span></span>
                    <span class="badge badge-admin">ADMIN</span>
                </div>
            </header>

            <div class="dashboard-grid">

                <!-- Total Items -->
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Items</h3>
                        <p class="stat-number"><?= number_format($totalItems) ?></p>
                        <span class="stat-change positive">Live inventory count</span>
                    </div>
                </div>

                <!-- Total Users -->
                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Users</h3>
                        <p class="stat-number"><?= number_format($totalUsers) ?></p>
                        <span class="stat-change positive">Registered users</span>
                    </div>
                </div>

                <!-- Low Stock -->
                <div class="stat-card">
                    <div class="stat-icon bg-orange">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Low Stock Items</h3>
                        <p class="stat-number"><?= number_format($lowStock) ?></p>
                        <span class="stat-change negative">Requires attention</span>
                    </div>
                </div>

                <!-- Total Value -->
                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Value</h3>
                        <p class="stat-number">GH₵ <?= number_format($totalValue, 2) ?></p>
                        <span class="stat-change positive">Inventory worth</span>
                    </div>
                </div>

            </div>

            <!-- Recent Activity -->
            <div class="content-section">
                <h2>Recent Activity</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>User</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td><?= htmlspecialchars($activity["created_at"]) ?></td>
                                    <td><?= htmlspecialchars($activity["action"]) ?></td>
                                    <td><?= htmlspecialchars($activity["user_name"] ?? "Unknown") ?></td>
                                    <td><?= htmlspecialchars($activity["description"] ?? "—") ?></td>
                                    <td><span
                                            class="badge badge-info"><?= htmlspecialchars($activity["ip_address"] ?? "N/A") ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</body>

</html>