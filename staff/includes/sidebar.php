<?php
// staff/includes/sidebar.php

// Security check - ensure user is logged in
/*if (!isset($_SESSION['staff_id']) || !isset($_SESSION['username'])) {
    header("Location: ../staff-login.php");
    exit();
}*/

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="header-content">
                <h2>Staff Panel</h2>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>"
                data-tooltip="Dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-text">Dashboard</span>
            </a>

            <a href="view-inventory.php"
                class="nav-item <?= ($current_page === 'view-inventory.php') ? 'active' : '' ?>"
                data-tooltip="View Inventory">
                <i class="fas fa-boxes"></i>
                <span class="nav-text">View Inventory</span>
            </a>

            <a href="request-items.php" class="nav-item <?= ($current_page === 'request-items.php') ? 'active' : '' ?>"
                data-tooltip="Request Items">
                <i class="fas fa-shopping-cart"></i>
                <span class="nav-text">Request Items</span>
            </a>

            <a href="my-requests.php" class="nav-item <?= ($current_page === 'my-requests.php') ? 'active' : '' ?>"
                data-tooltip="My Requests">
                <i class="fas fa-list"></i>
                <span class="nav-text">My Requests</span>
            </a>

            <a href="profile.php" class="nav-item <?= ($current_page === 'profile.php') ? 'active' : '' ?>"
                data-tooltip="My Profile">
                <i class="fas fa-user"></i>
                <span class="nav-text">My Profile</span>
            </a>

            <a href="../logout.php" class="nav-item logout" data-tooltip="Logout"
                onclick="return confirm('Are you sure you want to logout?');">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </aside>

    <script>
        // Sidebar toggle functionality
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const toggleIcon = toggleBtn.querySelector('i');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                document.body.classList.toggle('sidebar-collapsed');

                // Change icon based on state
                if (sidebar.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-bars');
                    toggleIcon.classList.add('fa-arrow-right');
                } else {
                    toggleIcon.classList.remove('fa-arrow-right');
                    toggleIcon.classList.add('fa-bars');
                }

                // Save state to localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });

            // Load saved state on page load
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-arrow-right');
            }
        }
    </script>
</body>

</html>