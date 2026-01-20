<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="images/logo.png" rel="icon" type="image/x-icon">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<body>
    <!-- Sidebar Component -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="header-content">
                <h2>Admin Panel</h2>
                <p>Welcome,
                    <?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin' ?>
                </p>
            </div>
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-dashboard"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="inventory.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : '' ?>">
                <i class="fas fa-boxes"></i>
                <span class="nav-text">Inventory Management</span>
            </a>
            <a href="users.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span class="nav-text">User Management</span>
            </a>
            <a href="reports.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Reports</span>
            </a>
            <a href="categories.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                <span class="nav-text">Categories</span>
            </a>
            <!--<a href="suppliers.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : '' ?>">
                <i class="fas fa-truck"></i>
                <span class="nav-text">Suppliers</span>
            </a>
            <a href="settings.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span class="nav-text">Settings</span>
            </a>
            <a href="../logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>-->
        </nav>
    </aside>

    <script>
        // Toggle Sidebar
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');

        // Check if user preference exists in localStorage
        const sidebarState = localStorage.getItem('sidebarCollapsed');
        if (sidebarState === 'true') {
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.classList.add('expanded');
            }
        }

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            if (mainContent) {
                mainContent.classList.toggle('expanded');
            }

            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
    </script>
</body>

</html>