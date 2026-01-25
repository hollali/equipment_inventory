<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/config/database.php";

$db = new Database();
$conn = $db->getConnection();

/* Stats */
$totalItems = $conn->query("SELECT COUNT(*) total FROM inventory_items")->fetch_assoc()['total'] ?? 0;
$totalUsers = $conn->query("SELECT COUNT(*) total FROM users")->fetch_assoc()['total'] ?? 0;
$inStorage = $conn->query("SELECT COUNT(*) total FROM inventory_items WHERE status='in_storage'")->fetch_assoc()['total'] ?? 0;
$retiredDevices = $conn->query("SELECT COUNT(*) total FROM inventory_items WHERE status='retired'")->fetch_assoc()['total'] ?? 0;

/* Recent Assignments with Pagination */
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

const STATUS_LABELS = [
    'active' => 'Active',
    'retired' => 'Retired',
    'in_storage' => 'In Storage',
    'repairing' => 'Repairing',
    'in_use' => 'In Use',
    'faulty' => 'Faulty'
];

$totalAssignments = $conn->query("
    SELECT COUNT(*) total 
    FROM inventory_items 
    WHERE assigned_user IS NOT NULL AND assigned_user != ''
")->fetch_assoc()['total'] ?? 0;

$totalPages = ceil($totalAssignments / $perPage);

$recentAssignments = [];
$result = $conn->query(" 
    SELECT 
        i.*,
        b.brand_name AS brand_name,
        d.department_name AS department_name,
        l.location_name AS location_name
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    WHERE i.assigned_user IS NOT NULL
      AND i.assigned_user != ''
    ORDER BY i.updated_at DESC
    LIMIT $perPage OFFSET $offset
");
while ($row = $result->fetch_assoc()) {
    $recentAssignments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-gray-100 flex min-h-screen">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-6">

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Dashboard Overview</h1>
                <p class="text-gray-500 text-sm mt-1">Welcome back! Here's what's happening today.</p>
            </div>
            <span
                class="px-4 py-2 text-xs font-semibold rounded-full bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md">ADMIN</span>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <?php
            $stats = [
                ['Total Devices', $totalItems, 'fa-boxes', 'blue', 'from-blue-500 to-blue-600'],
                ['Assigned Users', $totalUsers, 'fa-users', 'green', 'from-green-500 to-green-600'],
                ['In Storage', $inStorage, 'fa-warehouse', 'orange', 'from-orange-500 to-orange-600'],
                ['Retired Devices', $retiredDevices, 'fa-trash', 'red', 'from-red-500 to-red-600'],
            ];
            foreach ($stats as [$title, $value, $icon, $color, $gradient]):
                ?>
                <div
                    class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-300 p-6 border-l-4 border-<?= $color ?>-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium"><?= $title ?></p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?= number_format($value) ?></p>
                            <p class="text-xs text-gray-400 mt-1">
                                <i class="fas fa-arrow-up text-green-500"></i> Active
                            </p>
                        </div>
                        <div
                            class="w-16 h-16 flex items-center justify-center rounded-full bg-gradient-to-br <?= $gradient ?> text-white shadow-lg">
                            <i class="fas <?= $icon ?> text-2xl"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="filterPanel"
            class="hidden bg-white rounded-xl shadow-sm p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">

            <select id="filterStatus" class="border p-2 rounded-lg">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="in_storage">In Storage</option>
                <option value="under_repair">Under Repair</option>
                <option value="retired">Retired</option>
            </select>

            <select id="filterDepartment" class="border p-2 rounded-lg">
                <option value="">All Departments</option>
                <?php foreach ($departmentsArr as $d): ?>
                    <option value="<?= $d['id'] ?>">
                        <?= htmlspecialchars($d['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="filterLocation" class="border p-2 rounded-lg">
                <option value="">All Locations</option>
                <?php foreach ($locationsArr as $l): ?>
                    <option value="<?= $l['id'] ?>">
                        <?= htmlspecialchars($l['location_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" id="filterDate" class="border p-2 rounded-lg">
        </div>


        <!-- Search and Filter Card -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-gray-800">Recently Assigned Devices</h2>
                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-600 rounded-full font-medium">
                        <?= number_format($totalAssignments) ?> total
                    </span>
                </div>
                <div class="flex gap-2">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input id="searchInput" type="text" placeholder="Search devices, users..."
                            class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none focus:border-blue-500 w-full md:w-64">
                    </div>
                    <button onclick="toggleFilters()"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>

                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table id="assignmentTable" class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-gray-600">
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-gray-100"
                                data-sort="number">
                                # <i class="fas fa-sort ml-1 text-gray-400"></i>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-gray-100"
                                data-sort="string">
                                Asset Tag <i class="fas fa-sort ml-1 text-gray-400"></i>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-gray-100"
                                data-sort="string">
                                Device <i class="fas fa-sort ml-1 text-gray-400"></i>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-gray-100"
                                data-sort="string">
                                User <i class="fas fa-sort ml-1 text-gray-400"></i>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-gray-100"
                                data-sort="string">
                                Department <i class="fas fa-sort ml-1 text-gray-400"></i>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-gray-100"
                                data-sort="string">
                                Location <i class="fas fa-sort ml-1 text-gray-400"></i>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-gray-100"
                                data-sort="string">
                                Status <i class="fas fa-sort ml-1 text-gray-400"></i>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php if (empty($recentAssignments)): ?>
                            <tr>
                                <td colspan="8" class="py-8 text-center text-gray-400">
                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                    <p>No recent assignments</p>
                                </td>
                            </tr>
                        <?php else:
                            $sn = $offset + 1;
                            foreach ($recentAssignments as $item):
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-700 border-green-200',
                                    'retired' => 'bg-red-100 text-red-700 border-red-200',
                                    'in_storage' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                    'repairing' => 'bg-gray-100 text-gray-700 border-gray-200',
                                    'in_use' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                    'faulty' => 'bg-pink-100 text-pink-700 border-pink-200'
                                ];
                                $statusClass = $statusColors[$item['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-4 text-gray-600"><?= $sn++ ?></td>
                                    <td class="py-4 px-4">
                                        <span
                                            class="font-medium text-blue-600"><?= htmlspecialchars($item['asset_tag'] ?? '') ?></span>
                                    </td>
                                    <td class="py-4 px-4 text-gray-800">
                                        <?= htmlspecialchars(($item['brand_name'] ?? '') . ' , ' . ($item['model'] ?? '')) ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                                                <?= strtoupper(substr($item['assigned_user'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <span
                                                class="text-gray-700"><?= htmlspecialchars($item['assigned_user'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-gray-600"><?= htmlspecialchars($item['department_name'] ?? '') ?>
                                    </td>
                                    <td class="py-4 px-4 text-gray-600">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                        <?= htmlspecialchars($item['location_name'] ?? '') ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-3 py-1 text-xs rounded-full font-medium <?= $statusClass ?>">
                                            <?= ucfirst(str_replace('_', ' ', htmlspecialchars($item['status']))) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <button
                                            class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-lg transition-colors viewBtn"
                                            data-item='<?= json_encode($item) ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            Showing <span class="font-semibold"><?= $offset + 1 ?></span> to
                            <span class="font-semibold"><?= min($offset + $perPage, $totalAssignments) ?></span> of
                            <span class="font-semibold"><?= number_format($totalAssignments) ?></span> results
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>"
                                    class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++):
                                $activeClass = $i === $page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50';
                                ?>
                                <a href="?page=<?= $i ?>"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-colors <?= $activeClass ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>"
                                    class="px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-2xl w-11/12 md:w-2/3 lg:w-1/2 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-t-xl">
                <button id="closeModal" class="absolute top-4 right-4 text-white hover:text-gray-200 text-xl">
                    <i class="fas fa-times"></i>
                </button>
                <h2 class="text-2xl font-bold">Device Details</h2>
                <p class="text-blue-100 text-sm mt-1">Complete information</p>
            </div>
            <div id="modalContent" class="p-6 space-y-4">
                <!-- Dynamic content goes here -->
            </div>
        </div>
    </div>

    <!-- JS -->
    <script>
        // Live search
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            Array.from(tableBody.rows).forEach(row => {
                row.style.display = Array.from(row.cells)
                    .slice(1, 7)
                    .some(td => td.textContent.toLowerCase().includes(query)) ? '' : 'none';
            });
        });

        function debounce(fn, delay = 400) {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn(...args), delay);
            };
        }

        const fetchAssignments = debounce(() => {
            const params = new URLSearchParams({
                q: document.getElementById('searchInput').value,
                status: document.getElementById('filterStatus').value,
                department_id: document.getElementById('filterDepartment').value,
                location_id: document.getElementById('filterLocation').value,
                date: document.getElementById('filterDate').value,
                page: window.currentPage || 1
            });

            fetch(`ajax/assignments.php?${params}`)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('assignmentTableBody').innerHTML = html;
                });
        }, 400);

        document.querySelectorAll(
            '#searchInput, #filterStatus, #filterDepartment, #filterLocation, #filterDate'
        ).forEach(el => el.addEventListener('input', fetchAssignments));

        function toggleFilters() {
            document.getElementById('filterPanel').classList.toggle('hidden');
        }

        // Table sorting
        document.querySelectorAll('#assignmentTable thead th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const index = Array.from(th.parentNode.children).indexOf(th);
                const type = th.dataset.sort;
                const rows = Array.from(tableBody.rows).filter(r => r.style.display !== 'none');
                const asc = !th.asc;
                rows.sort((a, b) => {
                    let aVal = a.cells[index].textContent.trim();
                    let bVal = b.cells[index].textContent.trim();
                    if (type === 'number') {
                        aVal = parseFloat(aVal) || 0;
                        bVal = parseFloat(bVal) || 0;
                    }
                    return asc ? aVal.localeCompare(bVal, undefined, { numeric: true }) : bVal.localeCompare(aVal, undefined, { numeric: true });
                });
                rows.forEach(r => tableBody.appendChild(r));
                th.asc = asc;

                // Update sort icons
                document.querySelectorAll('#assignmentTable thead th[data-sort] i').forEach(icon => {
                    icon.className = 'fas fa-sort ml-1 text-gray-400';
                });
                th.querySelector('i').className = `fas fa-sort-${asc ? 'up' : 'down'} ml-1 text-blue-500`;
            });
        });

        // Modal logic
        const modal = document.getElementById('viewModal');
        const modalContent = document.getElementById('modalContent');
        const closeModal = document.getElementById('closeModal');

        document.querySelectorAll('.viewBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const item = JSON.parse(btn.dataset.item);
                const statusColors = {
                    'in_storage': 'bg-yellow-100 text-yellow-700',
                    'assigned': 'bg-green-100 text-green-700',
                    'retired': 'bg-red-100 text-red-700',
                    'maintenance': 'bg-blue-100 text-blue-700'
                };
                const statusClass = statusColors[item.status] || 'bg-gray-100 text-gray-700';

                modalContent.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase mb-1">Asset Tag</p>
                            <p class="font-semibold text-lg text-blue-600">${item.asset_tag}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase mb-1">Device</p>
                            <p class="font-semibold text-lg">${item.brand_name} , ${item.model}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase mb-1">Assigned User</p>
                            <p class="font-semibold">${item.assigned_user}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase mb-1">Department</p>
                            <p class="font-semibold">${item.department_name || 'N/A'}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase mb-1">Location</p>
                            <p class="font-semibold"><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>${item.location_name || 'N/A'}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-xs text-gray-500 uppercase mb-1">Status</p>
                            <span class="inline-block px-3 py-1 text-sm rounded-full font-medium ${statusClass}">
                                ${item.status.replace('_', ' ').toUpperCase()}
                            </span>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg md:col-span-2">
                            <p class="text-xs text-gray-500 uppercase mb-1">Last Updated</p>
                            <p class="font-semibold"><i class="fas fa-clock text-gray-400 mr-1"></i>${item.updated_at}</p>
                        </div>
                    </div>
                `;
                modal.classList.remove('hidden');
            });
        });

        closeModal.addEventListener('click', () => modal.classList.add('hidden'));
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });
    </script>

</body>

</html>