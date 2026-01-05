<?php
// staff/dashboard.php
/*session_start();
require_once '../classes/Auth.php';

$auth = new Auth();
$auth->requireStaff();
*/ ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Parliament Inventory</title>
    <link href="./images/logo.png" rel="icon" type="image/x-icon">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include './includes/sidebar.php'; ?>
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1>Staff Dashboard</h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <span class="badge badge-staff">STAFF</span>
                </div>
            </header>

            <div class="dashboard-grid">
                <!-- Statistics Cards -->
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Available Items</h3>
                        <p class="stat-number">856</p>
                        <span class="stat-change">Items you can request</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Approved Requests</h3>
                        <p class="stat-number">24</p>
                        <span class="stat-change positive">This month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon bg-orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Pending Requests</h3>
                        <p class="stat-number">5</p>
                        <span class="stat-change">Awaiting approval</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon bg-red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Rejected Requests</h3>
                        <p class="stat-number">2</p>
                        <span class="stat-change negative">This month</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="request-items.php" class="action-btn">
                        <i class="fas fa-plus-circle"></i>
                        <span>New Request</span>
                    </a>
                    <a href="view-inventory.php" class="action-btn">
                        <i class="fas fa-search"></i>
                        <span>Search Inventory</span>
                    </a>
                    <a href="my-requests.php" class="action-btn">
                        <i class="fas fa-history"></i>
                        <span>Request History</span>
                    </a>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="content-section">
                <h2>My Recent Requests</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#REQ-001</td>
                                <td>2024-12-21</td>
                                <td>Office Chair</td>
                                <td>2</td>
                                <td><span class="badge badge-warning">Pending</span></td>
                                <td><button class="btn-view">View</button></td>
                            </tr>
                            <tr>
                                <td>#REQ-002</td>
                                <td>2024-12-20</td>
                                <td>Printer Paper (A4)</td>
                                <td>10</td>
                                <td><span class="badge badge-success">Approved</span></td>
                                <td><button class="btn-view">View</button></td>
                            </tr>
                            <tr>
                                <td>#REQ-003</td>
                                <td>2024-12-19</td>
                                <td>Laptop</td>
                                <td>1</td>
                                <td><span class="badge badge-danger">Rejected</span></td>
                                <td><button class="btn-view">View</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Announcements -->
            <div class="content-section">
                <h2>Announcements</h2>
                <div class="announcements">
                    <div class="announcement-item">
                        <i class="fas fa-bullhorn"></i>
                        <div>
                            <h4>System Maintenance</h4>
                            <p>The system will be under maintenance on Dec 25, 2024 from 10:00 PM to 2:00 AM.</p>
                            <small>Posted: Dec 20, 2024</small>
                        </div>
                    </div>
                    <div class="announcement-item">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <h4>New Items Added</h4>
                            <p>20 new office equipment items have been added to the inventory. Check them out!</p>
                            <small>Posted: Dec 18, 2024</small>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>