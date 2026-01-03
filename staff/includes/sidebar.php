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
    <!-- staff/includes/sidebar.php -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="header-content">
                <h2>Staff Panel</h2>
                <p>Welcome,
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </p>
            </div>
            <button class="toggle-btn" id="toggleSidebar" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active" data-tooltip="Dashboard">
                <i class="fas fa-dashboard"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="view-inventory.php" class="nav-item" data-tooltip="View Inventory">
                <i class="fas fa-boxes"></i>
                <span class="nav-text">View Inventory</span>
            </a>
            <a href="request-items.php" class="nav-item" data-tooltip="Request Items">
                <i class="fas fa-shopping-cart"></i>
                <span class="nav-text">Request Items</span>
            </a>
            <a href="my-requests.php" class="nav-item" data-tooltip="My Requests">
                <i class="fas fa-list"></i>
                <span class="nav-text">My Requests</span>
            </a>
            <a href="profile.php" class="nav-item" data-tooltip="My Profile">
                <i class="fas fa-user"></i>
                <span class="nav-text">My Profile</span>
            </a>
            <a href="../logout.php" class="nav-item logout" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </aside>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-btn i');
            sidebar.classList.toggle('collapsed');

            // Change icon based on state
            if (sidebar.classList.contains('collapsed')) {
                toggleBtn.classList.remove('fa-bars');
                toggleBtn.classList.add('fa-arrow-right');
            } else {
                toggleBtn.classList.remove('fa-arrow-right');
                toggleBtn.classList.add('fa-bars');
            }

            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        // Load saved state on page load
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.toggle-btn i');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                toggleBtn.classList.remove('fa-bars');
                toggleBtn.classList.add('fa-arrow-right');
            }
        });
    </script>
</body>

</html>