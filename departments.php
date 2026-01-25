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
    <title>Departments Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-gray-100">

    <?php include 'sidebar.php'; ?>

    <main id="mainContent" class="p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Departments Management</h1>
                <p class="text-gray-500 text-sm">Manage company departments</p>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
            <form class="flex gap-2" method="GET">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search departments..."
                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Search
                </button>
            </form>

            <button onclick="openAddModal()"
                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fa fa-plus text-xs mr-1"></i> Add Department
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-600">
                            <th class="px-6 py-4 font-semibold">ID</th>
                            <th class="px-6 py-4 font-semibold">Department Name</th>
                            <th class="px-6 py-4 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($departments): ?>
                            <?php foreach ($departments as $dep): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-gray-600">#<?= $dep['id'] ?></td>
                                    <td class="px-6 py-4 font-medium text-gray-800">
                                        <i
                                            class="fas fa-building text-gray-400 mr-2"></i><?= htmlspecialchars($dep['department_name']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-3">
                                            <button onclick='openViewModal(<?= json_encode($dep) ?>)'
                                                class="text-green-600 hover:text-green-800 transition">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <button onclick='openEditModal(<?= json_encode($dep) ?>)'
                                                class="text-blue-600 hover:text-blue-800 transition">
                                                <i class="fa fa-pen"></i>
                                            </button>
                                            <a href="?delete=<?= $dep['id'] ?>"
                                                onclick="return confirm('Delete this department?')"
                                                class="text-red-600 hover:text-red-800 transition">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-12 text-gray-500">
                                    <i class="fa fa-building text-4xl text-gray-300 mb-3"></i>
                                    <p>No departments found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-between items-center mt-6">
                <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
                <div class="flex gap-2">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                            class="px-4 py-2 text-sm rounded-lg transition <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modals (Add/Edit) -->
    <div id="modal" class="fixed inset-0 flex items-center justify-center bg-black/50 hidden z-50 p-4"
        onclick="closeModalOnBackdrop(event, 'modal')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden" onclick="event.stopPropagation()">
            <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-building text-blue-600"></i>
                    </div>
                    <div>
                        <h2 id="modalTitle" class="text-xl font-bold text-gray-800"></h2>
                        <p class="text-sm text-gray-500">Department information</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <form method="POST" id="departmentForm">
                    <input type="hidden" name="department_id" id="department_id">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Department Name <span class="text-red-500">*</span>
                        </label>
                        <input name="department_name" id="department_name" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter department name">
                    </div>
                </form>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                <button type="button" onclick="closeModal()"
                    class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 transition font-medium">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button type="submit" form="departmentForm" id="modalBtn"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    <i class="fas fa-save mr-2"></i>Save
                </button>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="fixed inset-0 flex items-center justify-center bg-black/50 hidden z-50 p-4"
        onclick="closeModalOnBackdrop(event, 'viewModal')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden" onclick="event.stopPropagation()">
            <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-eye text-green-600"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Department Details</h2>
                        <p class="text-sm text-gray-500">View department information</p>
                    </div>
                </div>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Department ID</p>
                    <p class="font-semibold text-gray-800" id="view_id"></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 mt-4">
                    <p class="text-xs text-gray-500 mb-1">Department Name</p>
                    <p class="font-semibold text-gray-800" id="view_name"></p>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end border-t">
                <button onclick="closeViewModal()"
                    class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                    <i class="fas fa-check mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBtn = document.getElementById('modalBtn');
        const department_id = document.getElementById('department_id');
        const department_name = document.getElementById('department_name');
        const viewModal = document.getElementById('viewModal');
        const view_id = document.getElementById('view_id');
        const view_name = document.getElementById('view_name');

        function openAddModal() {
            modalTitle.innerText = 'Add Department';
            modalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
            modalBtn.name = 'add_department';
            department_id.value = '';
            department_name.value = '';
            modal.classList.remove('hidden');
        }

        function openEditModal(data) {
            modalTitle.innerText = 'Edit Department';
            modalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update';
            modalBtn.name = 'update_department';
            department_id.value = data.id;
            department_name.value = data.department_name;
            modal.classList.remove('hidden');
        }

        function closeModal() { modal.classList.add('hidden'); }
        function openViewModal(data) {
            view_id.innerText = '#' + data.id;
            view_name.innerText = data.department_name;
            viewModal.classList.remove('hidden');
        }
        function closeViewModal() { viewModal.classList.add('hidden'); }
        function closeModalOnBackdrop(event, modalId) {
            if (event.target === event.currentTarget) {
                if (modalId === 'modal') closeModal();
                else if (modalId === 'viewModal') closeViewModal();
            }
        }
    </script>
</body>

</html>