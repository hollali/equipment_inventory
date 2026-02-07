<?php
/**
 * Footer Component - Parliamentary Service of Ghana
 * Updated to correctly adapt width based on sidebar state
 */

$currentYear = date('Y');

// Detect logo
$logoPath = __DIR__ . '/images/';
$logoFound = false;
$logoFile = '';

$possibleLogos = [
    'parliament-logo.png',
    'parliament_logo.png',
    'psg-logo.png',
    'ghana-parliament.png',
    'logo.png',
    'coat_of_arms.png'
];

foreach ($possibleLogos as $logo) {
    if (file_exists($logoPath . $logo)) {
        $logoFile = $logo;
        $logoFound = true;
        break;
    }
}
?>
<!-- Footer -->
<footer id="mainFooter" class="bg-white border-t border-gray-200 shadow-sm z-30">
    <div class="w-full px-6 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

            <!-- Parliamentary Service Info -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <?php if ($logoFound): ?>
                        <img src="./images/<?php echo $logoFile; ?>" alt="Parliamentary Service of Ghana Logo"
                            class="w-12 h-12 rounded object-contain">
                    <?php else: ?>
                        <div
                            class="w-12 h-12 rounded bg-gradient-to-r from-blue-100 to-blue-50 border border-blue-200 flex items-center justify-center">
                            <i class="fas fa-landmark text-blue-600 text-xl"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Parliamentary Service</h3>
                        <p class="text-sm text-gray-500">IT Asset Management System</p>
                        <p class="text-xs text-gray-400 mt-1">Internal Use Only</p>
                    </div>
                </div>

                <p class="text-gray-600 text-sm">
                    Official IT asset tracking and management system for the Parliamentary Service of Ghana.
                </p>

                <div class="flex items-center gap-2 text-sm">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        <span class="text-emerald-600 font-medium">System Online</span>
                    </span>

                    <?php if (isset($_SESSION['full_name'])): ?>
                        <span class="text-gray-400 mx-2">•</span>
                        <span class="text-gray-500 text-xs">
                            User: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Internal Links -->
            <div>
                <h4 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Quick Access</h4>
                <ul class="space-y-2">
                    <li><a href="dashboard.php"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                            <i class="fas fa-chart-line w-5 text-sm"></i> Dashboard</a></li>

                    <li><a href="inventory.php"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                            <i class="fas fa-boxes-stacked w-5 text-sm"></i> Asset Inventory</a></li>

                    <li><a href="reports.php"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                            <i class="fas fa-chart-bar w-5 text-sm"></i> Reports</a></li>

                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <li><a href="settings.php"
                                class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all">
                                <i class="fas fa-gear w-5 text-sm"></i> Admin Settings</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Internal Support</h4>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-building text-gray-400 mt-1 text-sm"></i>
                        <div>
                            <span class="text-gray-700 text-sm font-medium">Parliament House</span>
                            <p class="text-gray-500 text-xs">Accra, Ghana</p>
                        </div>
                    </li>

                    <li class="flex items-center gap-3">
                        <i class="fas fa-headset text-gray-400 text-sm"></i>
                        <div>
                            <span class="text-gray-700 text-sm font-medium">IT Help Desk</span>
                            <p class="text-gray-500 text-xs">Ext. 2001-2005</p>
                        </div>
                    </li>

                    <li class="flex items-center gap-3">
                        <i class="fas fa-envelope text-gray-400 text-sm"></i>
                        <div>
                            <span class="text-gray-700 text-sm font-medium">Email Support</span>
                            <a href="mailto:itsupport@parliament.gh" class="text-blue-500 text-xs hover:underline">
                                itsupport@parliament.gh
                            </a>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Quick Actions -->
            <div>
                <h4 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Quick Actions</h4>
                <div class="space-y-3">
                    <a href="#"
                        class="flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-600 transition-colors group">
                        <i class="fas fa-book text-gray-400 group-hover:text-blue-500"></i>
                        <span class="text-sm">User Guide</span>
                    </a>

                    <a href="#"
                        class="flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-600 transition-colors group">
                        <i class="fas fa-question-circle text-gray-400 group-hover:text-blue-500"></i>
                        <span class="text-sm">FAQ</span>
                    </a>

                    <button onclick="printPage()"
                        class="w-full flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-600 transition-colors group">
                        <i class="fas fa-print text-gray-400 group-hover:text-blue-500"></i>
                        <span class="text-sm">Print Page</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-gray-200 mt-8 pt-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-gray-500 text-xs">
                    <p>&copy; <?php echo $currentYear; ?> Parliamentary Service of Ghana - IT Department</p>
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-1">
                            <i class="fas fa-shield-alt text-emerald-400"></i> Secure Network
                        </span>
                        <span class="text-gray-300">•</span>
                        <span class="inline-flex items-center gap-1">
                            <i class="fas fa-user-lock text-blue-400"></i> Authorized Access Only
                        </span>
                        <span class="text-gray-300">•</span>
                        <span class="inline-flex items-center gap-1">
                            <i class="fas fa-clock text-gray-400"></i>
                            <?php echo date('d/m/Y H:i'); ?>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs px-3 py-1 bg-blue-100 text-blue-600 rounded-full font-medium">
                        <i class="fas fa-check-circle mr-1"></i> Data Protection
                    </span>
                    <span class="text-xs px-3 py-1 bg-emerald-100 text-emerald-600 rounded-full font-medium">
                        <i class="fas fa-check-circle mr-1"></i> E-Government
                    </span>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button onclick="scrollToTop()"
    class="fixed bottom-6 right-6 w-12 h-12 bg-white border border-gray-200 rounded-full shadow-lg hover:shadow-xl transition-all flex items-center justify-center z-50 group opacity-0 scale-90">
    <i class="fas fa-chevron-up text-gray-600 group-hover:text-blue-600 transition-colors"></i>
</button>

<style>
    /* Footer styling */
    #mainFooter {
        width: 100%;
        margin-left: 0;
        transition: all 0.3s ease;
    }

    @media (min-width: 768px) {

        /* Default expanded state */
        #mainFooter {
            margin-left: 16rem;
            width: calc(100% - 16rem);
        }

        /* Collapsed state - matches sidebar collapsed class */
        body.sidebar-collapsed #mainFooter {
            margin-left: 5rem;
            width: calc(100% - 5rem);
        }

        /* When mainContent has collapsed class, also adjust footer */
        #mainContent.collapsed~#mainFooter {
            margin-left: 5rem;
            width: calc(100% - 5rem);
        }
    }
</style>

<script>
    // Scroll to top function
    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Print function
    function printPage() {
        window.print();
    }

    // Back to top button visibility
    window.addEventListener('scroll', () => {
        const button = document.querySelector('button[onclick="scrollToTop()"]');
        if (window.scrollY > 300) {
            button.classList.remove('opacity-0', 'scale-90');
            button.classList.add('opacity-100', 'scale-100');
        } else {
            button.classList.remove('opacity-100', 'scale-100');
            button.classList.add('opacity-0', 'scale-90');
        }
    });

    // Update footer position based on sidebar state
    function updateFooterPosition() {
        const footer = document.getElementById('mainFooter');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (window.innerWidth >= 768) {
            // Check if sidebar is collapsed (either by class or localStorage)
            const isSidebarCollapsed = sidebar ? sidebar.classList.contains('collapsed') :
                (localStorage.getItem('sidebarCollapsed') === 'true' ||
                    document.body.classList.contains('sidebar-collapsed'));

            if (isSidebarCollapsed) {
                footer.style.marginLeft = '5rem';
                footer.style.width = 'calc(100% - 5rem)';
                document.body.classList.add('sidebar-collapsed');

                // Also ensure mainContent has collapsed class
                if (mainContent && !mainContent.classList.contains('collapsed')) {
                    mainContent.classList.add('collapsed');
                }
            } else {
                footer.style.marginLeft = '16rem';
                footer.style.width = 'calc(100% - 16rem)';
                document.body.classList.remove('sidebar-collapsed');

                // Remove collapsed class from mainContent
                if (mainContent && mainContent.classList.contains('collapsed')) {
                    mainContent.classList.remove('collapsed');
                }
            }
        } else {
            // Mobile view - full width
            footer.style.marginLeft = '0';
            footer.style.width = '100%';
            document.body.classList.remove('sidebar-collapsed');
        }
    }

    // Initialize on DOM load
    document.addEventListener('DOMContentLoaded', () => {
        // Apply saved sidebar state immediately
        const savedState = localStorage.getItem('sidebarCollapsed');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (savedState === 'true') {
            document.body.classList.add('sidebar-collapsed');
            if (sidebar) sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('collapsed');
        }

        // Initial position update
        updateFooterPosition();

        // Listen for sidebar toggle
        const sidebarToggle = document.getElementById('toggleSidebar');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                // Small delay to ensure sidebar classes are updated
                setTimeout(updateFooterPosition, 50);
            });
        }

        // Listen for window resize
        window.addEventListener('resize', updateFooterPosition);

        // Also update on animation end (for smoother transition)
        if (sidebar) {
            sidebar.addEventListener('transitionend', updateFooterPosition);
        }
    });

    // Additional safety: update on page load completion
    window.addEventListener('load', () => {
        setTimeout(updateFooterPosition, 100);
    });
</script>