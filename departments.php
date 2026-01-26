<?php
session_start();
require_once "./config/database.php";

$db = new Database();
$conn = $db->getConnection();

/* Add Department */
if (isset($_POST['add_department'])) {
    $name = trim($_POST['department_name']);
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO departments (department_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: departments.php");
    exit();
}

/* Update Department */
if (isset($_POST['update_department'])) {
    $id = (int) $_POST['department_id'];
    $name = trim($_POST['department_name']);
    $stmt = $conn->prepare("UPDATE departments SET department_name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: departments.php");
    exit();
}

/* Delete Department */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: departments.php");
    exit();
}

/* Search & Pagination */
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

/* Count */
$countSql = "SELECT COUNT(*) FROM departments";
$params = [];
$types = "";
if ($search !== '') {
    $countSql .= " WHERE department_name LIKE ?";
    $term = "%$search%";
    $params = [$term];
    $types = "s";
}
$stmt = $conn->prepare($countSql);
if ($params)
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($totalRecords);
$stmt->fetch();
$stmt->close();

$totalPages = ceil($totalRecords / $perPage);

/* Fetch */
$sql = "SELECT * FROM departments";
$params = [];
$types = "";
if ($search !== '') {
    $sql .= " WHERE department_name LIKE ?";
    $term = "%$search%";
    $params = [$term];
    $types = "s";
}
$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$departments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Departments Management - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .fade-in {
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .scale-in {
            animation: scaleIn 0.2s ease-out;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main id="mainContent" class="p-4 md:p-8 max-w-7xl mx-auto">

        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-sitemap text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Departments</h1>
                            <p class="text-gray-600 text-sm">Organize and manage company departments</p>
                        </div>
                    </div>
                </div>
                <button onclick="openAddModal()"
                    class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl hover:from-orange-700 hover:to-red-700 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <i class="fas fa-plus mr-2"></i>
                    Add Department
                </button>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="mb-8">
            <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Departments</p>
                        <p class="text-4xl font-bold text-gray-900"><?= $totalRecords ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?= count($departments) ?> shown on this page
                        </p>
                    </div>
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-orange-100 to-red-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-building text-3xl text-orange-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <form method="GET" class="flex gap-3">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search departments by name..."
                        class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                </div>
                <button type="submit"
                    class="px-6 py-3 bg-orange-600 text-white rounded-xl hover:bg-orange-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <?php if ($search): ?>
                    <a href="departments.php"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors inline-flex items-center">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Departments Grid/Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            <!-- Mobile View: Cards -->
            <div class="md:hidden divide-y divide-gray-200">
                <?php if ($departments): ?>
                    <?php foreach ($departments as $dep): ?>
                        <div class="p-5 hover:bg-gray-50 transition-colors scale-in">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3 flex-1">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow">
                                        <?= strtoupper(substr($dep['department_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 text-lg">
                                            <?= htmlspecialchars($dep['department_name']) ?>
                                        </p>
                                        <p class="text-sm text-gray-500">ID: #<?= $dep['id'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick='openViewModal(<?= json_encode($dep) ?>)'
                                    class="flex-1 px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors font-medium">
                                    <i class="fas fa-eye mr-2"></i>View
                                </button>
                                <button onclick='openEditModal(<?= json_encode($dep) ?>)'
                                    class="flex-1 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors font-medium">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </button>
                                <a href="?delete=<?= $dep['id'] ?>"
                                    onclick="return confirm('Delete this department? This action cannot be undone.')"
                                    class="flex-1 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors font-medium text-center">
                                    <i class="fas fa-trash mr-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="py-16 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-sitemap text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-lg font-medium text-gray-900 mb-1">No departments found</p>
                        <p class="text-sm text-gray-500">Try adjusting your search or add a new department</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Desktop View: Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                ID</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Department Name</th>
                            <th
                                class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($departments): ?>
                            <?php foreach ($departments as $dep): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="text-sm font-medium text-gray-600">#<?= $dep['id'] ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center text-white font-bold shadow">
                                                <?= strtoupper(substr($dep['department_name'], 0, 2)) ?>
                                            </div>
                                            <span
                                                class="font-semibold text-gray-900 text-base"><?= htmlspecialchars($dep['department_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick='openViewModal(<?= json_encode($dep) ?>)'
                                                class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick='openEditModal(<?= json_encode($dep) ?>)'
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="Edit Department">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete=<?= $dep['id'] ?>"
                                                onclick="return confirm('Delete this department? This action cannot be undone.')"
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete Department">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-sitemap text-3xl text-gray-400"></i>
                                        </div>
                                        <p class="text-lg font-medium text-gray-900 mb-1">No departments found</p>
                                        <p class="text-sm text-gray-500">Try adjusting your search or add a new department
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-lg transition-colors font-medium <?= $i == $page
                                ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-lg'
                                : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php elseif (abs($i - $page) == 3): ?>
                        <span class="px-2 text-gray-400">...</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>"
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>

            <p class="text-center text-sm text-gray-500 mt-4">
                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total departments)
            </p>
        <?php endif; ?>

    </main>

    <!-- Add/Edit Modal -->
    <div id="modal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-orange-600 to-red-600 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 id="modalTitle" class="text-2xl font-bold mb-1"></h2>
                        <p class="text-orange-100 text-sm">Enter department information below</p>
                    </div>
                    <button onclick="closeModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <form method="POST" id="departmentForm" class="p-8">
                <input type="hidden" name="department_id" id="department_id">

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Department Name <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-building absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="department_name" id="department_name" required
                            placeholder="e.g., Human Resources, IT, Sales"
                            class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Enter the official department name</p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeModal()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button id="modalBtn" type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl hover:from-orange-700 hover:to-red-700 transition-all shadow-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in"
        onclick="closeModalOnBackdrop(event, 'viewModal')">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-700 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-1">Department Details</h2>
                        <p class="text-green-100 text-sm">View department information</p>
                    </div>
                    <button onclick="closeViewModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-8">
                <div class="space-y-4">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Department ID</p>
                        <p class="text-lg font-bold text-gray-900" id="view_id"></p>
                    </div>

                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Department Name</p>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center text-white font-bold shadow"
                                id="view_avatar">
                            </div>
                            <p class="text-lg font-bold text-gray-900" id="view_name"></p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end mt-8 pt-6 border-t border-gray-200">
                    <button onclick="closeViewModal()"
                        class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-700 text-white rounded-xl hover:from-green-700 hover:to-emerald-800 transition-all shadow-lg font-medium">
                        <i class="fas fa-check mr-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modal');
        const viewModal = document.getElementById('viewModal');

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Department';
            const btn = document.getElementById('modalBtn');
            btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Department';
            btn.name = 'add_department';
            document.getElementById('department_id').value = '';
            document.getElementById('department_name').value = '';
            document.getElementById('department_name').focus();
            modal.classList.remove('hidden');
        }

        function openEditModal(data) {
            document.getElementById('modalTitle').textContent = 'Edit Department';
            const btn = document.getElementById('modalBtn');
            btn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Department';
            btn.name = 'update_department';
            document.getElementById('department_id').value = data.id;
            document.getElementById('department_name').value = data.department_name;
            const input = document.getElementById('department_name');
            input.focus();
            input.select();
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        function openViewModal(data) {
            document.getElementById('view_id').textContent = '#' + data.id;
            document.getElementById('view_name').textContent = data.department_name;
            document.getElementById('view_avatar').textContent = data.department_name.substring(0, 2).toUpperCase();
            viewModal.classList.remove('hidden');
        }

        function closeViewModal() {
            viewModal.classList.add('hidden');
        }

        function closeModalOnBackdrop(event, modalId) {
            if (event.target === event.currentTarget) {
                if (modalId === 'modal') closeModal();
                else if (modalId === 'viewModal') closeViewModal();
            }
        }

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
            }
        });

        // Close modal on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    </script>

</body>

</html>