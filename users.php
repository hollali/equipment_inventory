<?php
#session_start();
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
        // Add user (no password needed, provide dummy)
        $dummy_password = ''; // MySQL needs something, leave it empty
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
    header("Location: users.php"); // redirect to avoid resubmission
    exit();
}


/* Search */
$search = trim($_GET['search'] ?? '');
$sql = "SELECT id, username, full_name, email, role, status, last_login FROM users";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?";
    $term = "%$search%";
    $params = [$term, $term, $term];
    $types = "sss";
}

$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($params)
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
            // Add user (no password needed, provide dummy)
            $dummy_password = ''; // MySQL needs something, leave it empty
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
        header("Location: users.php"); // redirect to avoid resubmission
        exit();
    }


    /* Search */
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT id, username, full_name, email, role, status, last_login FROM users";
    $params = [];
    $types = "";

    if ($search !== '') {
        $sql .= " WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?";
        $term = "%$search%";
        $params = [$term, $term, $term];
        $types = "sss";
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    if ($params)
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>User Management</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    </head>

<body class="bg-gray-100">

    <?php include 'sidebar.php'; ?>

    <main id="mainContent" class="p-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
                <p class="text-gray-500 text-sm">Manage system users and permissions</p>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap gap-3 items-center justify-between mb-6">
            <form method="GET" class="flex gap-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search users..."
                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Search
                </button>
            </form>

            <button onclick="openUserModal()"
                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fa fa-user-plus text-xs mr-1"></i> Add User
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-600">
                            <th class="px-6 py-4 font-semibold">ID</th>
                            <th class="px-6 py-4 font-semibold">Username</th>
                            <th class="px-6 py-4 font-semibold">Full Name</th>
                            <th class="px-6 py-4 font-semibold">Email</th>
                            <th class="px-6 py-4 font-semibold">Role</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold">Last Login</th>
                            <th class="px-6 py-4 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-gray-600">#<?= $row['id'] ?></td>
                                    <td class="px-6 py-4 font-medium text-gray-800">
                                        <i
                                            class="fas fa-user-circle text-gray-400 mr-2"></i><?= htmlspecialchars($row['username']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-800"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                            <?= ucfirst($row['role']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-3 py-1 rounded-full text-xs font-medium <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?= $row['last_login'] ? date("M d, Y H:i", strtotime($row['last_login'])) : 'Never' ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-3">
                                            <button onclick='openViewModal(<?= json_encode([
                                                "username" => $row['username'],
                                                "full_name" => $row['full_name'],
                                                "email" => $row['email'],
                                                "role" => ucfirst($row['role']),
                                                "status" => ucfirst($row['status']),
                                                "last_login" => $row['last_login'] ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never'
                                            ]) ?>)' class="text-green-600 hover:text-green-800 transition">
                                                <i class="fa fa-eye"></i>
                                            </button>

                                            <button onclick='openUserModal(<?= json_encode([
                                                "id" => $row['id'],
                                                "username" => $row['username'],
                                                "full_name" => $row['full_name'],
                                                "email" => $row['email'],
                                                "role" => $row['role'],
                                                "status" => $row['status']
                                            ]) ?>)' class="text-blue-600 hover:text-blue-800 transition">
                                                <i class="fa fa-pen"></i>
                                            </button>
                                            <a href="users.php?delete_id=<?= $row['id'] ?>"
                                                onclick="return confirm('Delete this user?')"
                                                class="text-red-600 hover:text-red-800 transition">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                                    <p>No users found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="fixed inset-0 flex items-center justify-center bg-black/50 hidden z-50 p-4"
        onclick="closeModalOnBackdrop(event, 'userModal')">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user text-blue-600"></i>
                    </div>
                    <div>
                        <h2 id="userModalTitle" class="text-xl font-bold text-gray-800">Add User</h2>
                        <p class="text-sm text-gray-500">Fill in user information</p>
                    </div>
                </div>
                <button type="button" onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <form method="POST" id="userForm">
                    <input type="hidden" name="user_id" id="user_id">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Username <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="username" id="username" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="full_name" id="full_name" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email <span
                                    class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Role <span
                                        class="text-red-500">*</span></label>
                                <select name="role" id="role" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="admin">Admin</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status <span
                                        class="text-red-500">*</span></label>
                                <select name="status" id="status" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                <button type="button" onclick="closeUserModal()"
                    class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 transition font-medium">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button type="submit" form="userForm" id="userModalBtn"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    <i class="fas fa-save mr-2"></i>Save
                </button>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewModal" class="fixed inset-0 flex items-center justify-center bg-black/50 hidden z-50 p-4"
        onclick="closeModalOnBackdrop(event, 'viewModal')">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-eye text-green-600"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">User Details</h2>
                        <p class="text-sm text-gray-500">View user information</p>
                    </div>
                </div>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 mb-1">Username</p>
                        <p class="font-semibold text-gray-800" id="view_username"></p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 mb-1">Full Name</p>
                        <p class="font-semibold text-gray-800" id="view_full_name"></p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4 md:col-span-2">
                        <p class="text-xs text-gray-500 mb-1">Email</p>
                        <p class="font-semibold text-gray-800" id="view_email"></p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 mb-1">Role</p>
                        <p class="font-semibold text-gray-800" id="view_role"></p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <p class="font-semibold text-gray-800" id="view_status"></p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4 md:col-span-2">
                        <p class="text-xs text-gray-500 mb-1">Last Login</p>
                        <p class="font-semibold text-gray-800" id="view_last_login"></p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex justify-end border-t">
                <button onclick="closeViewModal()"
                    class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                    <i class="fas fa-check mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>

    <script>
        const userModal = document.getElementById('userModal');
        const userModalTitle = document.getElementById('userModalTitle');
        const userModalBtn = document.getElementById('userModalBtn');
        const user_id = document.getElementById('user_id');
        const username = document.getElementById('username');
        const full_name = document.getElementById('full_name');
        const email = document.getElementById('email');
        const role = document.getElementById('role');
        const status = document.getElementById('status');

        const viewModal = document.getElementById('viewModal');
        const view_username = document.getElementById('view_username');
        const view_full_name = document.getElementById('view_full_name');
        const view_email = document.getElementById('view_email');
        const view_role = document.getElementById('view_role');
        const view_status = document.getElementById('view_status');
        const view_last_login = document.getElementById('view_last_login');

        function openUserModal(data = null) {
            userModal.classList.remove('hidden');
            if (data) {
                userModalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit User';
                userModalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update';
                user_id.value = data.id;
                username.value = data.username;
                full_name.value = data.full_name;
                email.value = data.email;
                role.value = data.role;
                status.value = data.status;
            } else {
                userModalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add User';
                userModalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
                user_id.value = '';
                username.value = '';
                full_name.value = '';
                email.value = '';
                role.value = 'staff';
                status.value = 'active';
            }
        }

        function closeUserModal() {
            userModal.classList.add('hidden');
        }

        function openViewModal(data) {
            view_username.innerText = data.username;
            view_full_name.innerText = data.full_name;
            view_email.innerText = data.email;
            view_role.innerText = data.role;
            view_status.innerText = data.status;
            view_last_login.innerText = data.last_login;
            viewModal.classList.remove('hidden');
        }

        function closeViewModal() {
            viewModal.classList.add('hidden');
        }

        function closeModalOnBackdrop(event, modalId) {
            if (event.target === event.currentTarget) {
                if (modalId === 'userModal') {
                    closeUserModal();
                } else if (modalId === 'viewModal') {
                    closeViewModal();
                }
            }
        }
    </script>

</body>

</html>

<!-- Add/Edit User Modal -->
<div id="userModal" class="fixed inset-0 flex items-center justify-center bg-black/50 hidden z-50 p-4"
    onclick="closeModalOnBackdrop(event, 'userModal')">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user text-blue-600"></i>
                </div>
                <div>
                    <h2 id="userModalTitle" class="text-xl font-bold text-gray-800">Add User</h2>
                    <p class="text-sm text-gray-500">Fill in user information</p>
                </div>
            </div>
            <button type="button" onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <form method="POST" id="userForm">
                <input type="hidden" name="user_id" id="user_id">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="username" id="username" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="full_name" id="full_name" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email <span
                                class="text-red-500">*</span></label>
                        <input type="email" name="email" id="email" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role <span
                                    class="text-red-500">*</span></label>
                            <select name="role" id="role" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status <span
                                    class="text-red-500">*</span></label>
                            <select name="status" id="status" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
            <button type="button" onclick="closeUserModal()"
                class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 transition font-medium">
                <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <button type="submit" form="userForm" id="userModalBtn"
                class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                <i class="fas fa-save mr-2"></i>Save
            </button>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div id="viewModal" class="fixed inset-0 flex items-center justify-center bg-black/50 hidden z-50 p-4"
    onclick="closeModalOnBackdrop(event, 'viewModal')">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-eye text-green-600"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">User Details</h2>
                    <p class="text-sm text-gray-500">View user information</p>
                </div>
            </div>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Username</p>
                    <p class="font-semibold text-gray-800" id="view_username"></p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Full Name</p>
                    <p class="font-semibold text-gray-800" id="view_full_name"></p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 md:col-span-2">
                    <p class="text-xs text-gray-500 mb-1">Email</p>
                    <p class="font-semibold text-gray-800" id="view_email"></p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Role</p>
                    <p class="font-semibold text-gray-800" id="view_role"></p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Status</p>
                    <p class="font-semibold text-gray-800" id="view_status"></p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 md:col-span-2">
                    <p class="text-xs text-gray-500 mb-1">Last Login</p>
                    <p class="font-semibold text-gray-800" id="view_last_login"></p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end border-t">
            <button onclick="closeViewModal()"
                class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                <i class="fas fa-check mr-2"></i>Close
            </button>
        </div>
    </div>
</div>

<script>
    const userModal = document.getElementById('userModal');
    const userModalTitle = document.getElementById('userModalTitle');
    const userModalBtn = document.getElementById('userModalBtn');
    const user_id = document.getElementById('user_id');
    const username = document.getElementById('username');
    const full_name = document.getElementById('full_name');
    const email = document.getElementById('email');
    const role = document.getElementById('role');
    const status = document.getElementById('status');

    const viewModal = document.getElementById('viewModal');
    const view_username = document.getElementById('view_username');
    const view_full_name = document.getElementById('view_full_name');
    const view_email = document.getElementById('view_email');
    const view_role = document.getElementById('view_role');
    const view_status = document.getElementById('view_status');
    const view_last_login = document.getElementById('view_last_login');

    function openUserModal(data = null) {
        userModal.classList.remove('hidden');
        if (data) {
            userModalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit User';
            userModalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update';
            user_id.value = data.id;
            username.value = data.username;
            full_name.value = data.full_name;
            email.value = data.email;
            role.value = data.role;
            status.value = data.status;
        } else {
            userModalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add User';
            userModalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
            user_id.value = '';
            username.value = '';
            full_name.value = '';
            email.value = '';
            role.value = 'staff';
            status.value = 'active';
        }
    }

    function closeUserModal() {
        userModal.classList.add('hidden');
    }

    function openViewModal(data) {
        view_username.innerText = data.username;
        view_full_name.innerText = data.full_name;
        view_email.innerText = data.email;
        view_role.innerText = data.role;
        view_status.innerText = data.status;
        view_last_login.innerText = data.last_login;
        viewModal.classList.remove('hidden');
    }

    function closeViewModal() {
        viewModal.classList.add('hidden');
    }

    function closeModalOnBackdrop(event, modalId) {
        if (event.target === event.currentTarget) {
            if (modalId === 'userModal') {
                closeUserModal();
            } else if (modalId === 'viewModal') {
                closeViewModal();
            }
        }
    }
</script>

</body>

</html>