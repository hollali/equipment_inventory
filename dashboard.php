<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . '/vendor/autoload.php';


$db = new Database();
$conn = $db->getConnection();

/* Fetch Departments and Locations for Filters */
$departmentsArr = [];
$deptResult = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name");
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departmentsArr[] = $row;
    }
}

$locationsArr = [];
$locResult = $conn->query("SELECT id, location_name FROM locations ORDER BY location_name");
if ($locResult) {
    while ($row = $locResult->fetch_assoc()) {
        $locationsArr[] = $row;
    }
}

/* Stats - Optimized Single Query */
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM inventory_items) as total_items,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM inventory_items WHERE status='in_storage') as in_storage,
        (SELECT COUNT(*) FROM inventory_items WHERE status='retired') as retired_devices
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult ? $statsResult->fetch_assoc() : [
    'total_items' => 0,
    'total_users' => 0,
    'in_storage' => 0,
    'retired_devices' => 0
];

$totalItems = $stats['total_items'];
$totalUsers = $stats['total_users'];
$inStorage = $stats['in_storage'];
$retiredDevices = $stats['retired_devices'];

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

$totalAssignmentsResult = $conn->query("
    SELECT COUNT(*) total 
    FROM inventory_items 
    WHERE assigned_user IS NOT NULL AND assigned_user != ''
");
$totalAssignments = $totalAssignmentsResult ? $totalAssignmentsResult->fetch_assoc()['total'] : 0;
$totalPages = ceil($totalAssignments / $perPage);

$recentAssignments = [];
$stmt = $conn->prepare(" 
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
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentAssignments[] = $row;
}

/* ================== FILTER INPUTS ================== */
$filterStatus = $_GET['status'] ?? '';
$filterDepartment = $_GET['department'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterDate = $_GET['date'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">


    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="flex-1 p-4 md:p-8 ml-0 md:ml-64">

        <!-- Header -->
        <div class="mb-8 animate-fade-in-up">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1
                        class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                        Dashboard Overview
                    </h1>
                    <p class="text-gray-600 text-sm mt-2 flex items-center gap-2">
                        <i class="fas fa-calendar-day text-blue-500"></i>
                        <?= date('l, F j, Y') ?> • Welcome back!
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg flex items-center gap-2">
                        <i class="fas fa-shield-alt"></i>
                        <span class="font-semibold text-sm">ADMIN</span>
                    </div>
                    <button
                        class="w-10 h-10 rounded-xl bg-white shadow-md flex items-center justify-center hover:shadow-lg transition-shadow">
                        <i class="fas fa-bell text-gray-600"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <?php
            $statsData = [
                [
                    'title' => 'Total Devices',
                    'value' => $totalItems,
                    'icon' => 'fa-boxes-stacked',
                    'gradient' => 'from-blue-500 to-blue-600',
                    'change' => 12,
                ],
                [
                    'title' => 'Assigned Users',
                    'value' => $totalUsers,
                    'icon' => 'fa-users',
                    'gradient' => 'from-green-500 to-green-600',
                    'change' => -5,
                ],
                [
                    'title' => 'In Storage',
                    'value' => $inStorage,
                    'icon' => 'fa-warehouse',
                    'gradient' => 'from-amber-500 to-orange-600',
                    'change' => 3,
                ],
                [
                    'title' => 'Retired Devices',
                    'value' => $retiredDevices,
                    'icon' => 'fas fa-archive',
                    'gradient' => 'from-red-500 to-red-600',
                    'change' => 0,
                ],
            ];
            ?>

            <?php foreach ($statsData as $index => $stat):
                $isPositive = $stat['change'] > 0;
                $isNegative = $stat['change'] < 0;

                $trendColor = $isPositive
                    ? 'text-green-600'
                    : ($isNegative ? 'text-red-600' : 'text-gray-400');

                $trendIcon = $isPositive
                    ? 'fa-arrow-up'
                    : ($isNegative ? 'fa-arrow-down' : 'fa-minus');
                ?>
                <div class="stat-card glass-effect rounded-2xl shadow-lg hover:shadow-2xl p-6 border border-gray-100 animate-fade-in-up"
                    style="animation-delay: <?= $index * 0.1 ?>s;">

                    <div class="flex items-start justify-between">
                        <div class="flex-1">

                            <div class="flex items-center gap-2 mb-3">
                                <div
                                    class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $stat['gradient'] ?> flex items-center justify-center shadow-lg">
                                    <i class="fas <?= $stat['icon'] ?> text-white text-xl"></i>
                                </div>
                            </div>

                            <p class="text-sm text-gray-500 font-medium mb-1">
                                <?= $stat['title'] ?>
                            </p>

                            <p class="text-3xl font-bold text-gray-800">
                                <?= number_format($stat['value']) ?>
                            </p>
                            <?php if ($stat['change'] !== 0): ?>
                                <div class="mt-3 flex items-center gap-1">
                                    <span class="text-xs font-semibold flex items-center gap-1 <?= $trendColor ?>">
                                        <i class="fas <?= $trendIcon ?>"></i>
                                        <?= abs($stat['change']) ?>%
                                    </span>
                                    <span class="text-xs text-gray-400">vs last month</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Filter Panel (Hidden by default) -->
        <form method="GET">
            <div id="filterPanel" class="hidden glass-effect rounded-2xl shadow-lg p-6 mb-6 border border-gray-100">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-filter text-blue-500"></i>
                        Advanced Filters
                    </h3>

                    <button type="button" onclick="clearFilters()" class="text-sm text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times-circle mr-1"></i>Clear All
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Status</label>
                        <select id="filterStatus" name="status"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                            <option value="">All Status</option>

                            <?php foreach (STATUS_LABELS as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($filterStatus === $value) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Department -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Department</label>
                        <select id="filterDepartment" name="department"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Departments</option>
                            <?php foreach ($departmentsArr as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Location</label>
                        <select id="filterLocation" name="location"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Locations</option>
                            <?php foreach ($locationsArr as $l): ?>
                                <option value="<?= $l['id'] ?>">
                                    <?= htmlspecialchars($l['location_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" id="filterDate" name="date"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                </div>

                <!-- Apply Button -->
                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition">
                        Apply Filters
                    </button>
                </div>

            </div>
        </form>


        <!-- Search and Actions Card -->
        <div class="glass-effect rounded-2xl shadow-lg p-6 mb-6 border border-gray-100">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-clipboard-list text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Recently Assigned Devices</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <span class="font-semibold text-blue-600"><?= number_format($totalAssignments) ?></span>
                            total assignments
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div class="relative flex-1 lg:flex-initial">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input id="searchInput" type="text" placeholder="Search devices, users, tags..."
                            class="w-full lg:w-80 pl-11 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <button onclick="toggleFilters()"
                        class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl text-sm font-medium hover:shadow-lg transition-all flex items-center gap-2">
                        <i class="fas fa-sliders"></i> Filter
                    </button>
                    <form method="GET" action="export_assignments.php">
                        <button type="submit"
                            class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:shadow-md transition-all flex items-center gap-2">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="glass-effect rounded-2xl shadow-lg overflow-hidden border border-gray-100">
            <div class="overflow-x-auto">
                <table id="assignmentTable" class="w-full text-sm">
                    <thead class="bg-gradient-to-r from-gray-50 to-blue-50">
                        <tr class="text-gray-700">
                            <th class="py-4 px-4 text-left font-semibold">
                                #
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-blue-100 transition-colors rounded-lg"
                                data-sort="string">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-tag text-blue-500"></i>
                                    Asset Tag
                                    <i class="fas fa-sort ml-1 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-blue-100 transition-colors rounded-lg"
                                data-sort="string">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-laptop text-purple-500"></i>
                                    Device
                                    <i class="fas fa-sort ml-1 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-blue-100 transition-colors rounded-lg"
                                data-sort="string">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-user text-green-500"></i>
                                    User
                                    <i class="fas fa-sort ml-1 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-blue-100 transition-colors rounded-lg"
                                data-sort="string">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-building text-amber-500"></i>
                                    Department
                                    <i class="fas fa-sort ml-1 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold cursor-pointer hover:bg-blue-100 transition-colors rounded-lg"
                                data-sort="string">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-location-dot text-red-500"></i>
                                    Location
                                    <i class="fas fa-sort ml-1 text-gray-400"></i>
                                </div>
                            </th>
                            <th class="py-4 px-4 text-left font-semibold">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-circle-info text-blue-500"></i>
                                    Status
                                </div>
                            </th>
                            <th class="py-4 px-4 text-center font-semibold">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php if (empty($recentAssignments)): ?>
                            <tr>
                                <td colspan="8" class="py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-inbox text-4xl text-gray-300"></i>
                                        </div>
                                        <p class="text-gray-400 font-medium">No recent assignments found</p>
                                        <p class="text-xs text-gray-400">Devices will appear here once assigned</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else:
                            $sn = $offset + 1;
                            foreach ($recentAssignments as $item):
                                $statusColors = [
                                    'active' => 'bg-green-50 text-green-700 border-green-200',
                                    'retired' => 'bg-red-50 text-red-700 border-red-200',
                                    'in_storage' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                    'repairing' => 'bg-gray-50 text-gray-700 border-gray-200',
                                    'in_use' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                    'faulty' => 'bg-pink-50 text-pink-700 border-pink-200'
                                ];
                                $statusClass = $statusColors[$item['status']] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                                ?>
                                <tr class="hover:bg-blue-50/50 transition-colors">
                                    <td class="py-4 px-4">
                                        <span class="text-gray-500 font-medium"><?= $sn++ ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-lg">
                                            <?= htmlspecialchars($item['asset_tag'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center">
                                                <i class="fas fa-laptop text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800">
                                                    <?= htmlspecialchars($item['brand_name'] ?? 'Unknown') ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($item['model'] ?? 'N/A') ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-9 h-9 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white text-sm font-bold shadow-md">
                                                <?= strtoupper(substr($item['assigned_user'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <span class="text-gray-700 font-medium">
                                                <?= htmlspecialchars($item['assigned_user'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="text-gray-700">
                                            <?= htmlspecialchars($item['department_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <i class="fas fa-map-marker-alt text-gray-400"></i>
                                            <?= htmlspecialchars($item['location_name'] ?? 'N/A') ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-3 py-1.5 text-xs rounded-full font-semibold border <?= $statusClass ?>">
                                            <?= ucfirst(str_replace('_', ' ', htmlspecialchars($item['status']))) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <button
                                            class="w-9 h-9 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-all viewBtn"
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
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-blue-50 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            Showing <span class="font-semibold text-blue-600"><?= $offset + 1 ?></span> to
                            <span
                                class="font-semibold text-blue-600"><?= min($offset + $perPage, $totalAssignments) ?></span>
                            of
                            <span class="font-semibold text-blue-600"><?= number_format($totalAssignments) ?></span> results
                        </div>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 hover:shadow-md transition-all font-medium">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++):
                                $activeClass = $i === $page
                                    ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-lg'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 hover:shadow-md';
                                ?>
                                <a href="?page=<?= $i ?>"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm transition-all font-medium <?= $activeClass ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 hover:shadow-md transition-all font-medium">
                                    Next <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal -->
    <div id="viewModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden animate-fade-in-up">
            <div class="sticky top-0 bg-gradient-to-r from-blue-500 via-purple-500 to-blue-600 text-white p-6">
                <button id="closeModal"
                    class="absolute top-6 right-6 text-white hover:text-gray-200 text-xl w-10 h-10 rounded-full hover:bg-white/20 transition-all">
                    <i class="fas fa-times"></i>
                </button>
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                        <i class="fas fa-info-circle text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold">Device Details</h2>
                        <p class="text-blue-100 text-sm mt-1">Complete information and specifications</p>
                    </div>
                </div>
            </div>
            <div id="modalContent" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 120px);">
                <!-- Dynamic content goes here -->
            </div>
        </div>
    </div>

    <!-- JS -->
    <script>
        // HTML Escape Function
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Live search
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            Array.from(tableBody.rows).forEach(row => {
                const text = Array.from(row.cells).slice(1, 7)
                    .map(td => td.textContent.toLowerCase())
                    .join(' ');
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });

        function toggleFilters() {
            const panel = document.getElementById('filterPanel');
            panel.classList.toggle('hidden');
        }

        function clearFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDepartment').value = '';
            document.getElementById('filterLocation').value = '';
            document.getElementById('filterDate').value = '';
        }

        // Table sorting
        document.querySelectorAll('#assignmentTable thead th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const index = Array.from(th.parentNode.children).indexOf(th);
                const type = th.dataset.sort;
                const rows = Array.from(tableBody.rows).filter(r => r.cells.length > 1);
                const asc = !th.asc;

                rows.sort((a, b) => {
                    let aVal = a.cells[index].textContent.trim();
                    let bVal = b.cells[index].textContent.trim();
                    if (type === 'number') {
                        aVal = parseFloat(aVal) || 0;
                        bVal = parseFloat(bVal) || 0;
                    }
                    return asc
                        ? aVal.toString().localeCompare(bVal.toString(), undefined, { numeric: true })
                        : bVal.toString().localeCompare(aVal.toString(), undefined, { numeric: true });
                });

                rows.forEach(r => tableBody.appendChild(r));
                th.asc = asc;

                // Update sort icons
                document.querySelectorAll('#assignmentTable thead th[data-sort] i.fa-sort, #assignmentTable thead th[data-sort] i.fa-sort-up, #assignmentTable thead th[data-sort] i.fa-sort-down').forEach(icon => {
                    icon.className = 'fas fa-sort ml-1 text-gray-400';
                });
                th.querySelector('i.fa-sort, i.fa-sort-up, i.fa-sort-down').className = `fas fa-sort-${asc ? 'up' : 'down'} ml-1 text-blue-500`;
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
                    'active': 'bg-green-50 text-green-700 border border-green-200',
                    'retired': 'bg-red-50 text-red-700 border border-red-200',
                    'in_storage': 'bg-yellow-50 text-yellow-700 border border-yellow-200',
                    'repairing': 'bg-gray-50 text-gray-700 border border-gray-200',
                    'in_use': 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                    'faulty': 'bg-pink-50 text-pink-700 border border-pink-200'
                };
                const statusClass = statusColors[item.status] || 'bg-gray-50 text-gray-700 border border-gray-200';

                modalContent.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-5 rounded-xl border border-blue-200">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-tag text-blue-500"></i>
                                <p class="text-xs text-blue-600 uppercase font-semibold">Asset Tag</p>
                            </div>
                            <p class="font-bold text-xl text-blue-700">${escapeHtml(item.asset_tag)}</p>
                        </div>
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-5 rounded-xl border border-purple-200">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-laptop text-purple-500"></i>
                                <p class="text-xs text-purple-600 uppercase font-semibold">Device</p>
                            </div>
                            <p class="font-bold text-lg text-purple-700">${escapeHtml(item.brand_name)}</p>
                            <p class="text-sm text-purple-600 mt-1">${escapeHtml(item.model)}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-5 rounded-xl border border-green-200">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-user text-green-500"></i>
                                <p class="text-xs text-green-600 uppercase font-semibold">Assigned User</p>
                            </div>
                            <div class="flex items-center gap-3 mt-2">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-bold shadow-md">
                                    ${escapeHtml(item.assigned_user.charAt(0).toUpperCase())}
                                </div>
                                <p class="font-bold text-green-700">${escapeHtml(item.assigned_user)}</p>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-5 rounded-xl border border-amber-200">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-building text-amber-500"></i>
                                <p class="text-xs text-amber-600 uppercase font-semibold">Department</p>
                            </div>
                            <p class="font-bold text-lg text-amber-700">${escapeHtml(item.department_name || 'N/A')}</p>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 p-5 rounded-xl border border-red-200">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-location-dot text-red-500"></i>
                                <p class="text-xs text-red-600 uppercase font-semibold">Location</p>
                            </div>
                            <p class="font-bold text-lg text-red-700">
                                <i class="fas fa-map-marker-alt mr-2"></i>${escapeHtml(item.location_name || 'N/A')}
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 p-5 rounded-xl border border-indigo-200">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fas fa-circle-info text-indigo-500"></i>
                                <p class="text-xs text-indigo-600 uppercase font-semibold">Status</p>
                            </div>
                            <span class="inline-block px-4 py-2 text-sm rounded-full font-bold ${statusClass}">
                                ${escapeHtml(item.status.replace('_', ' ').toUpperCase())}
                            </span>
                        </div>
                    </div>
                    <div class="mt-6 bg-gradient-to-br from-gray-50 to-gray-100 p-5 rounded-xl border border-gray-200">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fas fa-clock text-gray-500"></i>
                            <p class="text-xs text-gray-600 uppercase font-semibold">Timeline</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Last Updated</p>
                                <p class="font-semibold text-gray-700">
                                    <i class="fas fa-calendar-alt text-gray-400 mr-2"></i>${escapeHtml(item.updated_at || 'N/A')}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Serial Number</p>
                                <p class="font-semibold text-gray-700">
                                    <i class="fas fa-hashtag text-gray-400 mr-2"></i>${escapeHtml(item.serial_number || 'N/A')}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                            <i class="fas fa-edit mr-2"></i>Edit Device
                        </button>
                        <button class="flex-1 px-4 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition-all">
                            <i class="fas fa-history mr-2"></i>View History
                        </button>
                    </div>
                `;
                modal.classList.remove('hidden');
            });
        });

        closeModal.addEventListener('click', () => modal.classList.add('hidden'));
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.add('hidden');
        });
    </script>

</body>

</html>