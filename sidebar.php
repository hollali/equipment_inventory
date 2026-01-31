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
        transition: width 0.3s ease;
    }

    #sidebar.collapsed {
        width: 5rem;
    }

    #sidebar.collapsed .nav-text,
    #sidebar.collapsed .header-text,
    #sidebar.collapsed #logo {
        display: none;
    }

    #sidebar.collapsed .badge {
        display: none;
    }

    #mainContent {
        margin-left: 16rem;
        transition: margin-left 0.3s ease;
    }

    #mainContent.collapsed {
        margin-left: 5rem;
    }

    /* Navigation link styles */
    .nav-link {
        position: relative;
        transition: all 0.2s ease;
    }

    .nav-link:hover {
        transform: translateX(2px);
    }

    .nav-link.active {
        box-shadow: 0 1px 3px rgba(37, 99, 235, 0.1);
    }

    /* Badge positioning - FIXED */
    .badge {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        transition: opacity 0.2s ease;
    }

    /* Smooth icon transitions */
    .nav-link i {
        transition: transform 0.2s ease;
    }

    .nav-link:hover i {
        transform: scale(1.1);
    }

    /* Divider style */
    .nav-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e5e7eb, transparent);
        margin: 0.5rem 1rem;
    }

    /* Fix for links with badges to maintain alignment */
    .nav-link.with-badge {
        position: relative;
        padding-right: 3rem;
        /* Extra space for badge */
    }

    .nav-text {
        flex: 1;
        min-width: 0;
        /* Prevent text from expanding */
    }
</style>

<aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white border-r border-gray-200 shadow-sm z-40">

    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-white">
        <div class="flex items-center gap-3 overflow-hidden">
            <img id="logo" src="./images/logo.png" class="w-10 h-10 rounded transition-all duration-300" alt="Logo">
            <div class="header-text transition-all duration-300">
                <h2 class="text-lg font-semibold text-gray-800">Admin Panel</h2>
                <p class="text-sm text-gray-500">
                    Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>
                </p>
            </div>
        </div>

        <button id="toggleSidebar"
            class="flex items-center gap-2 text-gray-600 hover:text-gray-900 focus:outline-none p-2 rounded-lg hover:bg-gray-100 transition-all">
            <i class="fas fa-bars text-lg"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="p-3 space-y-1 overflow-y-auto h-[calc(100vh-80px)]">
        <!-- Dashboard -->
        <a href="dashboard.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('dashboard.php', $current) ?>">
            <i class="fas fa-chart-line w-5 text-center"></i>
            <span class="nav-text">Dashboard</span>
        </a>

        <div class="nav-divider"></div>

        <!-- Inventory Section -->
        <div class="nav-text px-4 py-2">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Inventory</span>
        </div>

        <a href="inventory.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('inventory.php', $current) ?>">
            <i class="fas fa-boxes-stacked w-5 text-center"></i>
            <span class="nav-text">All Devices</span>
        </a>

        <a href="unassigned_devices.php"
            class="nav-link with-badge flex items-center gap-3 px-4 py-3 rounded-lg <?= active('unassigned_devices.php', $current) ?>">
            <i class="fas fa-box-open w-5 text-center"></i>
            <span class="nav-text">Unassigned</span>
            <?php
            // Get unassigned AND stored devices count for badge - UPDATED LOGIC
            if (file_exists(__DIR__ . "/config/database.php")) {
                require_once __DIR__ . "/config/database.php";
                $db = new Database();
                $conn = $db->getConnection();

                // Updated query to match unassigned_devices.php logic
                $unassignedQuery = "SELECT COUNT(*) as count FROM inventory_items 
                                    WHERE ((assigned_user IS NULL OR assigned_user = '') OR status = 'in_storage') 
                                    AND status != 'retired'";
                $unassignedResult = $conn->query($unassignedQuery);
                if ($unassignedResult) {
                    $unassignedCount = $unassignedResult->fetch_assoc()['count'];
                    if ($unassignedCount > 0) {
                        echo '<span class="badge bg-red-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full shadow-sm">' . $unassignedCount . '</span>';
                    }
                }
            }
            ?>
        </a>

        <!-- ADDED: Retired Devices Link -->
        <a href="retired_devices.php"
            class="nav-link with-badge flex items-center gap-3 px-4 py-3 rounded-lg <?= active('retired_devices.php', $current) ?>">
            <i class="fas fa-recycle w-5 text-center"></i>
            <span class="nav-text">Retired Devices</span>
            <?php
            // Get retired devices count for badge
            if (file_exists(__DIR__ . "/config/database.php")) {
                require_once __DIR__ . "/config/database.php";
                $db = new Database();
                $conn = $db->getConnection();
                $retiredQuery = "SELECT COUNT(*) as count FROM inventory_items WHERE status = 'retired'";
                $retiredResult = $conn->query($retiredQuery);
                if ($retiredResult) {
                    $retiredCount = $retiredResult->fetch_assoc()['count'];
                    if ($retiredCount > 0) {
                        echo '<span class="badge bg-orange-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full shadow-sm">' . $retiredCount . '</span>';
                    }
                }
            }
            ?>
        </a>

        <div class="nav-divider"></div>

        <!-- Management Section -->
        <div class="nav-text px-4 py-2">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</span>
        </div>

        <a href="users.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('users.php', $current) ?>">
            <i class="fas fa-users w-5 text-center"></i>
            <span class="nav-text">Users</span>
        </a>

        <a href="brands.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('brands.php', $current) ?>">
            <i class="fa-solid fa-laptop w-5 text-center"></i>
            <span class="nav-text">Brands</span>
        </a>

        <a href="categories.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('categories.php', $current) ?>">
            <i class="fas fa-tags w-5 text-center"></i>
            <span class="nav-text">Categories</span>
        </a>

        <a href="departments.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('departments.php', $current) ?>">
            <i class="fa-regular fa-building w-5 text-center"></i>
            <span class="nav-text">Departments</span>
        </a>

        <a href="locations.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('locations.php', $current) ?>">
            <i class="fa-solid fa-location-dot w-5 text-center"></i>
            <span class="nav-text">Locations</span>
        </a>

        <div class="nav-divider"></div>

        <!-- Analytics & Settings Section -->
        <div class="nav-text px-4 py-2">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">System</span>
        </div>

        <a href="reports.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('reports.php', $current) ?>">
            <i class="fas fa-chart-bar w-5 text-center"></i>
            <span class="nav-text">Reports</span>
        </a>

        <a href="settings.php"
            class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg <?= active('settings.php', $current) ?>">
            <i class="fas fa-gear w-5 text-center"></i>
            <span class="nav-text">Settings</span>
        </a>
    </nav>

</aside>

<script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('toggleSidebar');
    const toggleText = document.querySelector('#toggleSidebar .toggle-text');

    // Restore sidebar state from localStorage on page load
    function restoreSidebarState() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.classList.add('collapsed');
            }
            // Update toggle button text when collapsed
            if (toggleText) {
                toggleText.textContent = 'Expand';
            }
        }
    }

    // Call on page load
    document.addEventListener('DOMContentLoaded', restoreSidebarState);

    // Toggle sidebar
    toggleBtn.addEventListener('click', () => {
        const isCollapsing = !sidebar.classList.contains('collapsed');

        sidebar.classList.toggle('collapsed');

        if (mainContent) {
            mainContent.classList.toggle('collapsed');
        }

        // Update toggle button text
        if (toggleText) {
            if (sidebar.classList.contains('collapsed')) {
                toggleText.textContent = 'Expand';
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                toggleText.textContent = 'Collapse';
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
    });

    // Adjust main content height on resize
    window.addEventListener('resize', () => {
        if (mainContent) {
            mainContent.style.minHeight = window.innerHeight + 'px';
        }
    });

    // Set initial min-height for main content
    if (mainContent) {
        mainContent.style.minHeight = window.innerHeight + 'px';
    }
</script>