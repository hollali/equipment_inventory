<?php
session_start();
require_once "./config/database.php";

/* ================== ERROR REPORTING ================== */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* Database */
$db = new Database();
$conn = $db->getConnection();

/* Handle POST for Add/Edit */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['user_id'] ?? null;
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    if ($id) {
        // Edit user
        $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, status=? WHERE id=?");
        $stmt->bind_param("sssssi", $username, $full_name, $email, $role, $status, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Add user
        $dummy_password = '';
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, role, status, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $full_name, $email, $role, $status, $dummy_password);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: users.php");
    exit();
}

/* Handle GET for Delete */
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: users.php");
    exit();
}

/* Search and Filter */
$search = trim($_GET['search'] ?? '');
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT id, username, full_name, email, role, status, last_login FROM users WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
    $types .= "sss";
}

if ($filterRole !== '') {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
    $types .= "s";
}

if ($filterStatus !== '') {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get counts for stats
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE status='active'")->fetch_assoc()['count'];
$adminUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='admin'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management - Admin Dashboard</title>
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
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main id="mainContent" class="p-4 md:p-8 max-w-7xl mx-auto">

        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">User Management</h1>
                    <p class="text-gray-600">Manage system users, roles, and permissions</p>
                </div>
                <button onclick="openUserModal()"
                    class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <i class="fas fa-user-plus mr-2"></i>
                    Add New User
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Users</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $totalUsers ?></p>
                    </div>
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Active Users</p>
                        <p class="text-3xl font-bold text-green-600"><?= $activeUsers ?></p>
                    </div>
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-check text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Administrators</p>
                        <p class="text-3xl font-bold text-purple-600"><?= $adminUsers ?></p>
                    </div>
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-shield text-2xl text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by username, name, or email..." autocomplete="off"
                            class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="w-full md:w-48">
                    <select name="role"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="w-full md:w-48">
                    <select name="status"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="">All Status</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex gap-2">
                    <button type="submit"
                        class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="users.php"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors inline-flex items-center">
                        <i class="fas fa-redo mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                User</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Contact</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Role</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Last Login</th>
                            <th
                                class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                <?= strtoupper(substr($row['username'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($row['username']) ?>
                                                </p>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($row['full_name']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-envelope text-gray-400 mr-2 text-sm"></i>
                                            <span class="text-sm"><?= htmlspecialchars($row['email']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($row['role'] === 'admin'): ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                                <i class="fas fa-shield-alt mr-1.5"></i>Admin
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                <i class="fas fa-user mr-1.5"></i>Staff
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($row['status'] === 'active'): ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Active
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-600">
                                            <?= $row['last_login'] ? date("M d, Y", strtotime($row['last_login'])) : 'Never' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick='openViewModal(<?= json_encode([
                                                "username" => $row['username'],
                                                "full_name" => $row['full_name'],
                                                "email" => $row['email'],
                                                "role" => ucfirst($row['role']),
                                                "status" => ucfirst($row['status']),
                                                "last_login" => $row['last_login'] ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never'
                                            ]) ?>)'
                                                class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                                title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick='openUserModal(<?= json_encode([
                                                "id" => $row['id'],
                                                "username" => $row['username'],
                                                "full_name" => $row['full_name'],
                                                "email" => $row['email'],
                                                "role" => $row['role'],
                                                "status" => $row['status']
                                            ]) ?>)'
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="users.php?delete_id=<?= $row['id'] ?>"
                                                onclick="return confirm('Are you sure you want to delete this user?')"
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-users text-3xl text-gray-400"></i>
                                        </div>
                                        <p class="text-lg font-medium text-gray-900 mb-1">No users found</p>
                                        <p class="text-sm text-gray-500">Try adjusting your search or filters</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Add/Edit User Modal -->
    <div id="userModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in"
        onclick="closeModalOnBackdrop(event, 'userModal')">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 id="userModalTitle" class="text-2xl font-bold mb-1">Add New User</h2>
                        <p class="text-blue-100 text-sm">Fill in the user information below</p>
                    </div>
                    <button onclick="closeUserModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <form method="POST" id="userForm" class="p-8">
                <input type="hidden" name="user_id" id="user_id">

                <div class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="username" id="username" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="full_name" id="full_name" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" id="email" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Role <span class="text-red-500">*</span>
                            </label>
                            <select name="role" id="role" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                <option value="admin">Administrator</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status" id="status" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeUserModal()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                        Cancel
                    </button>
                    <button type="submit" id="userModalBtn"
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Save User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in"
        onclick="closeModalOnBackdrop(event, 'viewModal')">
        <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-1">User Details</h2>
                        <p class="text-blue-100 text-sm">View complete user information</p>
                    </div>
                    <button onclick="closeViewModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Username</p>
                        <p class="text-lg font-bold text-gray-900" id="view_username"></p>
                    </div>

                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Full Name</p>
                        <p class="text-lg font-bold text-gray-900" id="view_full_name"></p>
                    </div>

                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5 md:col-span-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Email Address</p>
                        <p class="text-lg font-bold text-gray-900" id="view_email"></p>
                    </div>

                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Role</p>
                        <p class="text-lg font-bold text-gray-900" id="view_role"></p>
                    </div>

                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Status</p>
                        <p class="text-lg font-bold text-gray-900" id="view_status"></p>
                    </div>

                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5 md:col-span-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Last Login</p>
                        <p class="text-lg font-bold text-gray-900" id="view_last_login"></p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end mt-8 pt-6 border-t border-gray-200">
                    <button onclick="closeViewModal()"
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg font-medium">
                        <i class="fas fa-check mr-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const userModal = document.getElementById('userModal');
        const viewModal = document.getElementById('viewModal');

        function openUserModal(data = null) {
            userModal.classList.remove('hidden');
            const title = document.getElementById('userModalTitle');
            const btn = document.getElementById('userModalBtn');

            if (data) {
                title.textContent = 'Edit User';
                btn.innerHTML = '<i class="fas fa-save mr-2"></i>Update User';
                document.getElementById('user_id').value = data.id;
                document.getElementById('username').value = data.username;
                document.getElementById('full_name').value = data.full_name;
                document.getElementById('email').value = data.email;
                document.getElementById('role').value = data.role;
                document.getElementById('status').value = data.status;
            } else {
                title.textContent = 'Add New User';
                btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save User';
                document.getElementById('userForm').reset();
                document.getElementById('user_id').value = '';
            }
        }

        function closeUserModal() {
            userModal.classList.add('hidden');
        }

        function openViewModal(data) {
            document.getElementById('view_username').textContent = data.username;
            document.getElementById('view_full_name').textContent = data.full_name;
            document.getElementById('view_email').textContent = data.email;
            document.getElementById('view_role').textContent = data.role;
            document.getElementById('view_status').textContent = data.status;
            document.getElementById('view_last_login').textContent = data.last_login;
            viewModal.classList.remove('hidden');
        }

        function closeViewModal() {
            viewModal.classList.add('hidden');
        }

        function closeModalOnBackdrop(event, modalId) {
            if (event.target === event.currentTarget) {
                if (modalId === 'userModal') closeUserModal();
                else if (modalId === 'viewModal') closeViewModal();
            }
        }

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeUserModal();
                closeViewModal();
            }
        });
    </script>

</body>

</html>