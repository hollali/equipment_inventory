<?php
session_start();
require_once "./config/database.php";
require_once __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* Database */
$db = new Database();
$conn = $db->getConnection();

// Initialize toast message
$toast = ['type' => '', 'message' => ''];

/* Add User */
if (isset($_POST['add_user'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);

    if ($firstname !== '' && $email !== '') {
        $stmt = $conn->prepare("
            INSERT INTO users (firstname, lastname, email, role, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssss",
            $firstname,
            $lastname,
            $email,
            $role,
            $status
        );

        if ($stmt->execute()) {
            $toast = ['type' => 'success', 'message' => 'User added successfully!'];
        } else {
            $toast = ['type' => 'error', 'message' => 'Failed to add user. Please try again.'];
        }
        $stmt->close();
    } else {
        $toast = ['type' => 'error', 'message' => 'First name and email are required.'];
    }

    $_SESSION['toast'] = $toast;
    header("Location: users.php");
    exit();
}

/* Update User */
if (isset($_POST['update_user'])) {
    $id = (int) $_POST['user_id'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $firstname, $lastname, $email, $role, $status, $id);

    if ($stmt->execute()) {
        $toast = ['type' => 'success', 'message' => 'User updated successfully!'];
    } else {
        $toast = ['type' => 'error', 'message' => 'Failed to update user. Please try again.'];
    }
    $stmt->close();

    $_SESSION['toast'] = $toast;
    header("Location: users.php");
    exit();
}

/* Delete User */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $toast = ['type' => 'success', 'message' => 'User deleted successfully!'];
    } else {
        $toast = ['type' => 'error', 'message' => 'Failed to delete user. Please try again.'];
    }
    $stmt->close();

    $_SESSION['toast'] = $toast;
    header("Location: users.php");
    exit();
}

// Check for toast message in session
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

/* Search & Filters & Pagination */
$search = trim($_GET['search'] ?? '');
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 25, 50, 100])
    ? (int) $_GET['limit']
    : 10;
$offset = ($page - 1) * $limit;

/* Build WHERE clause with filters */
$whereConditions = [];
$params = [];
$types = "";

if ($search !== '') {
    $whereConditions[] = "(firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if ($filterRole !== '') {
    $whereConditions[] = "role = ?";
    $params[] = $filterRole;
    $types .= "s";
}

if ($filterStatus !== '') {
    $whereConditions[] = "status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$whereClause = !empty($whereConditions)
    ? " WHERE " . implode(" AND ", $whereConditions)
    : "";

/* Count total users with filters */
$countSql = "SELECT COUNT(*) as total FROM users" . $whereClause;
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$totalUsers = $countResult->fetch_assoc()['total'];
$stmt->close();

$totalPages = ceil($totalUsers / $limit);

/* Count stats for cards */
// Get active users count
$activeSql = "SELECT COUNT(*) as count FROM users" . $whereClause . ($whereClause ? " AND " : " WHERE ") . "status = 'active'";
$stmt = $conn->prepare($activeSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$activeResult = $stmt->get_result();
$activeUsers = $activeResult->fetch_assoc()['count'];
$stmt->close();

// Get admin users count
$adminSql = "SELECT COUNT(*) as count FROM users" . $whereClause . ($whereClause ? " AND " : " WHERE ") . "role = 'admin'";
$stmt = $conn->prepare($adminSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$adminResult = $stmt->get_result();
$adminUsers = $adminResult->fetch_assoc()['count'];
$stmt->close();

// Get MP users count
$mpSql = "SELECT COUNT(*) as count FROM users" . $whereClause . ($whereClause ? " AND " : " WHERE ") . "role = 'mp'";
$stmt = $conn->prepare($mpSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$mpResult = $stmt->get_result();
$mpUsers = $mpResult->fetch_assoc()['count'];
$stmt->close();

// Get staff users count
$staffSql = "SELECT COUNT(*) as count FROM users" . $whereClause . ($whereClause ? " AND " : " WHERE ") . "role = 'staff'";
$stmt = $conn->prepare($staffSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$staffResult = $stmt->get_result();
$staffUsers = $staffResult->fetch_assoc()['count'];
$stmt->close();

/* Fetch users with pagination */
$sql = "SELECT * FROM users" . $whereClause . " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .table-row-hover {
            transition: background-color 0.15s ease-in-out;
        }

        .table-row-hover:hover {
            background-color: #f8fafc;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-hover), #2563eb);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .role-admin {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .role-mp {
            background-color: #e0f2fe;
            color: #0c4a6e;
        }

        .role-staff {
            background-color: #f1f5f9;
            color: #475569;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: #475569;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover {
            background-color: #f1f5f9;
            border-color: #cbd5e1;
        }

        .pagination-btn.active {
            background-color: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .input-field {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            width: 100%;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .select-field {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            width: 100%;
            background: white;
        }

        .select-field:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .toast {
            animation: toastSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .stats-card {
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #3b82f6);
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
        }

        /* Responsive adjustments for sidebar */
        main {
            transition: margin-left 0.3s ease;
        }

        @media (min-width: 1024px) {
            main {
                margin-left: 16rem;
            }

            body.sidebar-collapsed main {
                margin-left: 5rem;
            }
        }
    </style>
</head>

<body class="min-h-screen">

    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="p-4 md:p-6 lg:p-8">

        <!-- Toast Notification -->
        <?php if ($toast['type'] && $toast['message']): ?>
            <div class="toast fixed top-6 right-6 z-50 max-w-md">
                <div
                    class="glass-card rounded-xl p-4 flex items-center justify-between border-l-4 <?= $toast['type'] === 'success' ? 'border-green-500' : 'border-red-500' ?>">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <?php if ($toast['type'] === 'success'): ?>
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                            <?php else: ?>
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-exclamation text-red-600"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($toast['message']) ?></p>
                        </div>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">User Management</h1>
                    <p class="text-gray-600">Manage system users, roles, and permissions</p>
                </div>
                <button onclick="openAddModal()"
                    class="btn-primary px-6 py-3 rounded-xl inline-flex items-center space-x-2">
                    <i class="fas fa-user-plus"></i>
                    <span>Add New User</span>
                </button>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
                <div class="glass-card stats-card rounded-xl p-5 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $totalUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card stats-card rounded-xl p-5 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Active</p>
                            <p class="text-2xl font-bold text-green-600"><?= $activeUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-check text-green-600 text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card stats-card rounded-xl p-5 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Inactive</p>
                            <p class="text-2xl font-bold text-red-600"><?= $totalUsers - $activeUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-slash text-red-600 text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card stats-card rounded-xl p-5 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Admins</p>
                            <p class="text-2xl font-bold text-blue-600"><?= $adminUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-shield text-blue-600 text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card stats-card rounded-xl p-5 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">MPs</p>
                            <p class="text-2xl font-bold text-blue-600"><?= $mpUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-landmark text-blue-600 text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card stats-card rounded-xl p-5 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Staff</p>
                            <p class="text-2xl font-bold text-gray-600"><?= $staffUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user text-gray-600 text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="glass-card rounded-xl p-5 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by name or email..." class="input-field pl-10">
                    </div>
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" class="select-field">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="mp" <?= $filterRole === 'mp' ? 'selected' : '' ?>>MP</option>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="select-field">
                        <option value="">All Status</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="users.php" class="btn-secondary py-2.5 px-4 rounded-xl">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="glass-card rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                User
                            </th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Contact
                            </th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Role
                            </th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Status
                            </th>
                            <th
                                class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="table-row-hover">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="font-medium text-gray-900">
                                                    <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="role-badge role-admin">
                                                <i class="fas fa-shield-alt"></i>Admin
                                            </span>
                                        <?php elseif ($user['role'] === 'mp'): ?>
                                            <span class="role-badge role-mp">
                                                <i class="fas fa-landmark"></i>MP
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge role-staff">
                                                <i class="fas fa-user"></i>Staff
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-circle text-[10px]"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">
                                                <i class="fas fa-circle text-[10px]"></i>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick='openViewModal(<?= json_encode([
                                                "firstname" => $user['firstname'],
                                                "lastname" => $user['lastname'],
                                                "email" => $user['email'],
                                                "role" => ucfirst($user['role']),
                                                "status" => ucfirst($user['status']),
                                            ]) ?>)'
                                                class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                                title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick='openEditModal(<?= json_encode([
                                                "id" => $user['id'],
                                                "firstname" => $user['firstname'],
                                                "lastname" => $user['lastname'],
                                                "email" => $user['email'],
                                                "role" => $user['role'],
                                                "status" => $user['status']
                                            ]) ?>)'
                                                class="text-blue-400 hover:text-blue-600 p-2 rounded-lg hover:bg-blue-50 transition-colors"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button
                                                onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>')"
                                                class="text-red-400 hover:text-red-600 p-2 rounded-lg hover:bg-red-50 transition-colors"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-users text-gray-400 text-2xl"></i>
                                        </div>
                                        <p class="text-gray-900 font-medium mb-1">No users found</p>
                                        <p class="text-gray-500">Try adjusting your search or filters</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $baseUrl = '?' . (!empty($queryParams) ? http_build_query($queryParams) . '&' : '');
                ?>

                <div class="px-6 py-4 border-t border-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            Showing <span
                                class="font-semibold"><?= min($limit, $totalUsers - (($page - 1) * $limit)) ?></span>
                            of <span class="font-semibold"><?= $totalUsers ?></span> users
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">Show:</span>
                                <select onchange="changeItemsPerPage(this)" class="select-field text-sm py-1.5 px-3 w-20">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>

                            <div class="flex items-center space-x-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                        class="pagination-btn min-w-[2.5rem] <?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Add/Edit Modal -->
    <div id="modal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay h-full w-full">
            <div class="modal-content bg-white rounded-xl w-full max-w-md mx-4">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 id="modalTitle" class="text-xl font-bold text-gray-900"></h2>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>

                    <form method="POST" id="userForm">
                        <input type="hidden" name="user_id" id="user_id">

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                <input type="text" name="firstname" id="firstname" required class="input-field"
                                    placeholder="John">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                <input type="text" name="lastname" id="lastname" required class="input-field"
                                    placeholder="Doe">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" id="email" required class="input-field"
                                    placeholder="john@example.com">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                                <select name="role" id="role" required class="select-field">
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="staff">Staff</option>
                                    <option value="mp">MP</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                                <select name="status" id="status" required class="select-field">
                                    <option value="">Select Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
                            <button type="button" onclick="closeModal()" class="btn-secondary px-6 py-2.5 rounded-xl">
                                Cancel
                            </button>
                            <button id="modalBtn" type="submit" class="btn-primary px-6 py-2.5 rounded-xl">
                                Save User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay h-full w-full">
            <div class="modal-content bg-white rounded-xl w-full max-w-md mx-4">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">User Details</h2>
                        <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>

                    <div class="text-center mb-6">
                        <div class="user-avatar mx-auto w-20 h-20 text-2xl">
                            <span id="view_avatar"></span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</label>
                            <p id="view_name" class="text-lg font-semibold text-gray-900 mt-1"></p>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email</label>
                            <p id="view_email" class="text-gray-900 mt-1"></p>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Role</label>
                            <div id="view_role_badge" class="mt-2 inline-block"></div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Status</label>
                            <div id="view_status_badge" class="mt-2 inline-block"></div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-8 pt-6 border-t">
                        <button onclick="closeViewModal()" class="btn-primary px-6 py-2.5 rounded-xl">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-overlay h-full w-full">
            <div class="modal-content bg-white rounded-xl w-full max-w-md mx-4">
                <div class="p-6">
                    <div class="flex items-start mb-6">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-bold text-gray-900">Delete User</h3>
                            <p class="text-gray-600 mt-2">
                                Are you sure you want to delete <span id="deleteUserName"
                                    class="font-semibold text-gray-900"></span>?
                                This action cannot be undone.
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button onclick="closeDeleteModal()" class="btn-secondary px-6 py-2.5 rounded-xl">
                            Cancel
                        </button>
                        <form method="POST" action="users.php" class="inline">
                            <input type="hidden" name="delete_id" id="deleteUserId">
                            <button type="submit"
                                class="bg-red-600 hover:bg-red-700 text-white px-6 py-2.5 rounded-xl font-medium transition-colors">
                                Delete User
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // Modal Functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('modalBtn').textContent = 'Add User';
            document.getElementById('modalBtn').name = 'add_user';
            document.getElementById('user_id').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('modal').classList.remove('hidden');
        }

        function openEditModal(data) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('modalBtn').textContent = 'Update User';
            document.getElementById('modalBtn').name = 'update_user';
            document.getElementById('user_id').value = data.id;
            document.getElementById('firstname').value = data.firstname;
            document.getElementById('lastname').value = data.lastname;
            document.getElementById('email').value = data.email;
            document.getElementById('role').value = data.role;
            document.getElementById('status').value = data.status;
            document.getElementById('modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function openViewModal(data) {
            document.getElementById('view_avatar').textContent =
                data.firstname.substring(0, 1).toUpperCase() + data.lastname.substring(0, 1).toUpperCase();
            document.getElementById('view_name').textContent = data.firstname + ' ' + data.lastname;
            document.getElementById('view_email').textContent = data.email;

            const roleBadge = document.getElementById('view_role_badge');
            roleBadge.className = 'role-badge ';
            if (data.role === 'Admin') {
                roleBadge.className += 'role-admin';
                roleBadge.innerHTML = '<i class="fas fa-shield-alt"></i>Admin';
            } else if (data.role === 'MP') {
                roleBadge.className += 'role-mp';
                roleBadge.innerHTML = '<i class="fas fa-landmark"></i>MP';
            } else {
                roleBadge.className += 'role-staff';
                roleBadge.innerHTML = '<i class="fas fa-user"></i>Staff';
            }

            const statusBadge = document.getElementById('view_status_badge');
            statusBadge.className = 'status-badge ';
            if (data.status === 'Active') {
                statusBadge.className += 'status-active';
                statusBadge.innerHTML = '<i class="fas fa-circle text-[10px]"></i>Active';
            } else {
                statusBadge.className += 'status-inactive';
                statusBadge.innerHTML = '<i class="fas fa-circle text-[10px]"></i>Inactive';
            }

            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
                closeDeleteModal();
            }
        });

        // Close modals on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.parentElement.classList.add('hidden');
                }
            });
        });

        // Auto-remove toast after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const toast = document.querySelector('.toast');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            }

            // Ensure main content has proper margin on load
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');

            if (sidebar && mainContent) {
                const isCollapsed = sidebar.classList.contains('collapsed');
                if (isCollapsed) {
                    mainContent.classList.add('collapsed');
                } else {
                    mainContent.classList.remove('collapsed');
                }
            }
        });
    </script>
</body>

</html>