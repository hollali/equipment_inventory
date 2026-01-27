<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current = basename($_SERVER['PHP_SELF']);
function active($page, $current)
{
    return $page === $current
        ? 'bg-blue-100 text-blue-600 font-semibold'
        : 'text-gray-600 hover:bg-gray-100';
}
?>
<style>
    /* Sidebar collapsed styles */
    #sidebar {
        width: 16rem;
        /* 64 = w-64 */
        transition: width 0.3s ease;
    }

    #sidebar.collapsed {
        width: 5rem;
        /* 20 = w-20 */
    }

    #sidebar.collapsed .nav-text,
    #sidebar.collapsed .header-text,
    #sidebar.collapsed #logo {
        display: none;
    }

    #mainContent {
        margin-left: 16rem;
        transition: margin-left 0.3s ease;
    }

    #mainContent.collapsed {
        margin-left: 5rem;
    }
</style>

<aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white border-r shadow-sm">

    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b">
        <div class="flex items-center gap-3 overflow-hidden">
            <img id="logo" src="./images/logo.png" class="w-10 h-10 rounded transition-all duration-300" alt="Logo">
            <div class="header-text transition-all duration-300">
                <h2 class="text-lg font-semibold text-gray-800">Admin Panel</h2>
                <p class="text-sm text-gray-500">
                    Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>
                </p>
            </div>
        </div>

        <button id="toggleSidebar" class="text-gray-600 hover:text-gray-900 focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="p-3 space-y-1">
        <a href="dashboard.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('dashboard.php', $current) ?>">
            <i class="fas fa-chart-line w-5 text-center"></i>
            <span class="nav-text">Dashboard</span>
        </a>

        <a href="inventory.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('inventory.php', $current) ?>">
            <i class="fas fa-boxes-stacked w-5 text-center"></i>
            <span class="nav-text">Inventory</span>
        </a>

        <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('users.php', $current) ?>">
            <i class="fas fa-users w-5 text-center"></i>
            <span class="nav-text">Users</span>
        </a>

        <a href="brands.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('brands.php', $current) ?>">
            <i class="fa-solid fa-laptop w-5 text-center"></i>
            <span class="nav-text">Brands</span>
        </a>

        <a href="categories.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('categories.php', $current) ?>">
            <i class="fas fa-tags w-5 text-center"></i>
            <span class="nav-text">Categories</span>
        </a>

        <a href="departments.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('departments.php', $current) ?>">
            <i class="fa-regular fa-building w-5 text-center"></i>
            <span class="nav-text">Departments</span>
        </a>

        <a href="locations.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('locations.php', $current) ?>">
            <i class="fa-solid fa-location-dot w-5 text-center"></i>
            <span class="nav-text">Locations</span>
        </a>

        <a href="reports.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('reports.php', $current) ?>">
            <i class="fas fa-chart-bar w-5 text-center"></i>
            <span class="nav-text">Reports</span>
        </a>

        <a href="settings.php"
            class="flex items-center gap-3 px-4 py-3 rounded-lg <?= active('settings.php', $current) ?>">
            <i class="fas fa-gear w-5 text-center"></i>
            <span class="nav-text">Settings</span>
        </a>
    </nav>

    <!-- Logout -->
    <div class="absolute bottom-0 w-full p-3 border-t">
        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50">
            <i class="fas fa-right-from-bracket w-5 text-center"></i>
            <span class="nav-text">Logout</span>
        </a>
    </div>

</aside>

<script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('toggleSidebar');

    // Restore sidebar state from localStorage on page load
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
        if (mainContent) {
            mainContent.classList.add('collapsed');
        }
    }

    // Toggle sidebar
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');

        if (mainContent) {
            mainContent.classList.toggle('collapsed');
        }

        // Save state
        if (sidebar.classList.contains('collapsed')) {
            localStorage.setItem('sidebarCollapsed', 'true');
        } else {
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    });
</script>