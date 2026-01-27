<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Parliament ICT</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-slate-100 min-h-screen flex">

    <!-- SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main id="mainContent" class="ml-64 flex-1 p-8">

        <!-- HEADER -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">
                <i class="fas fa-chart-bar mr-2"></i>System Reports
            </h1>
            <p class="text-slate-600 mt-1">Summary of users and recent activities</p>
        </header>

        <!-- STATISTICS CARDS -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-blue-600 rounded-xl p-6 text-white shadow hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Total Users</p>
                        <p class="text-3xl font-bold mt-2" id="totalUsers">0</p>
                    </div>
                    <i class="fas fa-users text-4xl opacity-20"></i>
                </div>
            </div>
            <div class="bg-blue-600 rounded-xl p-6 text-white shadow hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Active Users</p>
                        <p class="text-3xl font-bold mt-2" id="activeUsers">0</p>
                    </div>
                    <i class="fas fa-user-check text-4xl opacity-20"></i>
                </div>
            </div>
            <div class="bg-blue-600 rounded-xl p-6 text-white shadow hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Inactive Users</p>
                        <p class="text-3xl font-bold mt-2" id="inactiveUsers">0</p>
                    </div>
                    <i class="fas fa-user-slash text-4xl opacity-20"></i>
                </div>
            </div>
            <div class="bg-blue-600 rounded-xl p-6 text-white shadow hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Administrators</p>
                        <p class="text-3xl font-bold mt-2" id="adminUsers">0</p>
                    </div>
                    <i class="fas fa-user-shield text-4xl opacity-20"></i>
                </div>
            </div>
        </section>

        <!-- USER METRICS TABLE -->
        <section class="bg-white rounded-xl shadow mb-8 overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-slate-800">
                    <i class="fas fa-chart-line mr-2 text-blue-600"></i>User Metrics
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">Metric</th>
                            <th class="px-6 py-4 text-right font-semibold text-slate-700">Count</th>
                        </tr>
                    </thead>
                    <tbody id="metricsTableBody" class="divide-y">
                        <tr>
                            <td class="px-6 py-3">
                                <i class="fas fa-briefcase mr-2 text-slate-500"></i>Staff Users
                            </td>
                            <td class="px-6 py-3 text-right" id="staffUsers">0</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-3">
                                <i class="fas fa-landmark mr-2 text-slate-500"></i>MP Users
                            </td>
                            <td class="px-6 py-3 text-right" id="mpUsers">0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ACTIVITY LOGS TABLE -->
        <section class="bg-white rounded-xl shadow overflow-hidden">
            <div class="p-6 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="text-xl font-semibold text-slate-800">
                    <i class="fas fa-history mr-2 text-blue-600"></i>Recent Activity Logs
                </h2>
                <div class="flex gap-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        <input id="searchInput" type="text" placeholder="Search logs..."
                            class="border border-slate-300 pl-10 pr-4 py-2 rounded-lg focus:ring-2 focus:ring-blue-600 focus:outline-none">
                    </div>
                    <button id="exportBtn"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition flex items-center gap-2">
                        <i class="fas fa-download"></i>Export CSV
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-user mr-2"></i>User
                            </th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-bolt mr-2"></i>Action
                            </th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-network-wired mr-2"></i>IP Address
                            </th>
                            <th class="px-6 py-4 text-left font-semibold text-slate-700">
                                <i class="fas fa-calendar mr-2"></i>Date
                            </th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody" class="divide-y">
                        <!-- Sample row -->
                        <tr>
                            <td class="px-6 py-3">
                                <i class="fas fa-user-circle mr-2 text-slate-400"></i>John Doe
                            </td>
                            <td class="px-6 py-3">
                                <span
                                    class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs">
                                    <i class="fas fa-sign-in-alt"></i>Logged In
                                </span>
                            </td>
                            <td class="px-6 py-3">192.168.1.10</td>
                            <td class="px-6 py-3">2026-01-23 12:34</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- JS -->
    <script>
        // Demo system metrics
        const systemData = {
            metrics: {
                totalUsers: 247,
                activeUsers: 198,
                inactiveUsers: 49,
                adminUsers: 12,
                staffUsers: 145,
                mpUsers: 90
            }
        };

        // Fill dashboard numbers
        document.getElementById('totalUsers').textContent = systemData.metrics.totalUsers;
        document.getElementById('activeUsers').textContent = systemData.metrics.activeUsers;
        document.getElementById('inactiveUsers').textContent = systemData.metrics.inactiveUsers;
        document.getElementById('adminUsers').textContent = systemData.metrics.adminUsers;
        document.getElementById('staffUsers').textContent = systemData.metrics.staffUsers;
        document.getElementById('mpUsers').textContent = systemData.metrics.mpUsers;

        // Search functionality (basic)
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', () => {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#logsTableBody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>

</body>

</html>