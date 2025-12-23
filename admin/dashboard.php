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
                <!-- Statistics Cards -->
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Items</h3>
                        <p class="stat-number">1,234</p>
                        <span class="stat-change positive">+12% from last month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Users</h3>
                        <p class="stat-number">45</p>
                        <span class="stat-change positive">+3 new this week</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon bg-orange">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Low Stock Items</h3>
                        <p class="stat-number">18</p>
                        <span class="stat-change negative">Requires attention</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Value</h3>
                        <p class="stat-number">GH₵ 2.5M</p>
                        <span class="stat-change positive">+8% from last month</span>
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
                                <th>Item</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>2024-12-21</td>
                                <td>Item Added</td>
                                <td>John Doe</td>
                                <td>Office Chair</td>
                                <td><span class="badge badge-success">Completed</span></td>
                            </tr>
                            <tr>
                                <td>2024-12-21</td>
                                <td>Stock Update</td>
                                <td>Jane Smith</td>
                                <td>Printer Paper</td>
                                <td><span class="badge badge-success">Completed</span></td>
                            </tr>
                            <tr>
                                <td>2024-12-20</td>
                                <td>Item Removed</td>
                                <td>Admin</td>
                                <td>Old Laptop</td>
                                <td><span class="badge badge-warning">Pending</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>