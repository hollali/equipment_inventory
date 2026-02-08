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

    // Store toast in session to display after redirect
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
    unset($_SESSION['toast']); // Clear toast after displaying
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        },
                        gray: {
                            50: '#f9fafb',
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            300: '#d1d5db',
                            400: '#9ca3af',
                            500: '#6b7280',
                            600: '#4b5563',
                            700: '#374151',
                            800: '#1f2937',
                            900: '#111827',
                        }
                    },
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .animate-slideIn {
            animation: slideIn 0.3s ease-out;
        }

        .animate-fadeIn {
            animation: fadeIn 0.2s ease-out;
        }

        .animate-scaleIn {
            animation: scaleIn 0.2s ease-out;
        }

        .animate-slideInRight {
            animation: slideInRight 0.3s ease-out;
        }

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

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <?php include 'sidebar.php'; ?>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 flex flex-col gap-3 w-96">
        <?php if ($toast['type'] && $toast['message']): ?>
            <div id="toast" class="toast-<?= $toast['type'] ?> animate-slideInRight">
                <div
                    class="flex items-center justify-between p-4 rounded-lg shadow-lg border <?= $toast['type'] === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <?php if ($toast['type'] === 'success'): ?>
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p
                                class="text-sm font-medium <?= $toast['type'] === 'success' ? 'text-green-800' : 'text-red-800' ?>">
                                <?= htmlspecialchars($toast['message']) ?>
                            </p>
                        </div>
                    </div>
                    <button onclick="hideToast()"
                        class="ml-4 flex-shrink-0 <?= $toast['type'] === 'success' ? 'text-green-400 hover:text-green-600' : 'text-red-400 hover:text-red-600' ?>">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <main id="mainContent" class="p-6 lg:p-8 ml-0 lg:ml-64 transition-all duration-300">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">User Management</h1>
                    <p class="text-gray-600">Manage system users, roles, and permissions efficiently</p>
                </div>
                <button onclick="openAddModal()"
                    class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm hover:shadow-md">
                    <i class="fas fa-user-plus mr-2"></i>
                    Add New User
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
                <div class="glass-card rounded-xl p-6 hover-lift">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Total Users</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $totalUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-lg text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card rounded-xl p-6 hover-lift">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Active</p>
                            <p class="text-2xl font-semibold text-green-600"><?= $activeUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-check text-lg text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card rounded-xl p-6 hover-lift">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Inactive</p>
                            <p class="text-2xl font-semibold text-red-600"><?= $totalUsers - $activeUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-slash text-lg text-red-600"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card rounded-xl p-6 hover-lift">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Admins</p>
                            <p class="text-2xl font-semibold text-purple-600"><?= $adminUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-shield text-lg text-purple-600"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card rounded-xl p-6 hover-lift">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">MPs</p>
                            <p class="text-2xl font-semibold text-blue-600"><?= $mpUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                            <i class="fas fa-landmark text-lg text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card rounded-xl p-6 hover-lift">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Staff</p>
                            <p class="text-2xl font-semibold text-gray-600"><?= $staffUsers ?></p>
                        </div>
                        <div class="w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user text-lg text-gray-600"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <!-- Search -->
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search users..."
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="mp" <?= $filterRole === 'mp' ? 'selected' : '' ?>>MP</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                        <option value="">All Status</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="users.php"
                        class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors inline-flex items-center font-medium">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contact
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div
                                                class="flex-shrink-0 h-10 w-10 bg-primary-100 text-primary-700 rounded-lg flex items-center justify-center font-medium">
                                                <?= strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                <i class="fas fa-shield-alt mr-1.5"></i>Admin
                                            </span>
                                        <?php elseif ($user['role'] === 'mp'): ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-landmark mr-1.5"></i>MP
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <i class="fas fa-user mr-1.5"></i>Staff
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Active
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick='openViewModal(<?= json_encode([
                                                "firstname" => $user['firstname'],
                                                "lastname" => $user['lastname'],
                                                "email" => $user['email'],
                                                "role" => ucfirst($user['role']),
                                                "status" => ucfirst($user['status']),
                                            ]) ?>)'
                                                class="text-gray-400 hover:text-gray-600 transition-colors p-1.5 rounded hover:bg-gray-100"
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
                                                class="text-blue-400 hover:text-blue-600 transition-colors p-1.5 rounded hover:bg-blue-50"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button
                                                onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>')"
                                                class="text-red-400 hover:text-red-600 transition-colors p-1.5 rounded hover:bg-red-50"
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
                                            <i class="fas fa-users text-2xl text-gray-400"></i>
                                        </div>
                                        <p class="text-gray-900 font-medium mb-1">No users found</p>
                                        <p class="text-gray-500 text-sm">Try adjusting your search or filters</p>
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

                <div class="bg-white px-6 py-4 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="text-sm text-gray-700">
                            Showing <span
                                class="font-medium"><?= min($limit, $totalUsers - (($page - 1) * $limit)) ?></span>
                            of <span class="font-medium"><?= $totalUsers ?></span> results
                        </div>

                        <div class="flex items-center space-x-2">
                            <!-- Items per page -->
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-700">Show:</span>
                                <select onchange="changeItemsPerPage(this)"
                                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>

                            <!-- Page navigation -->
                            <nav class="flex items-center space-x-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"
                                        class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                    <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                        class="px-3 py-1.5 border <?= $i == $page ? 'bg-primary-50 border-primary-500 text-primary-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-colors">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"
                                        class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Add/Edit Modal -->
    <div id="modal"
        class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 animate-fadeIn">
        <div class="bg-white w-full max-w-md rounded-xl shadow-xl animate-scaleIn" onclick="event.stopPropagation()">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 id="modalTitle" class="text-lg font-semibold text-gray-900"></h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <form method="POST" id="userForm" class="p-6">
                <input type="hidden" name="user_id" id="user_id">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name *</label>
                        <input type="text" name="firstname" id="firstname" required placeholder="Enter first name"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name *</label>
                        <input type="text" name="lastname" id="lastname" required placeholder="Enter last name"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email *</label>
                        <input type="email" name="email" id="email" required placeholder="Enter email address"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Role *</label>
                        <select name="role" id="role" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="mp">MP</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Status *</label>
                        <select name="status" id="status" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                    <button type="button" onclick="closeModal()"
                        class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                        Cancel
                    </button>
                    <button id="modalBtn" type="submit"
                        class="px-4 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium">
                        Save User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal"
        class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 animate-fadeIn">
        <div class="bg-white w-full max-w-md rounded-xl shadow-xl animate-scaleIn" onclick="event.stopPropagation()">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">User Details</h2>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="flex justify-center mb-6">
                    <div
                        class="w-20 h-20 bg-primary-100 text-primary-700 rounded-xl flex items-center justify-center text-2xl font-semibold">
                        <span id="view_avatar"></span>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</p>
                        <p class="text-lg font-semibold text-gray-900" id="view_name"></p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email Address</p>
                        <p class="text-gray-900" id="view_email"></p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Role</p>
                        <p id="view_role_badge" class="inline-block"></p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Status</p>
                        <p id="view_status_badge" class="inline-block"></p>
                    </div>
                </div>

                <div class="flex justify-end pt-6 mt-6 border-t border-gray-200">
                    <button onclick="closeViewModal()"
                        class="px-4 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal"
        class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 animate-fadeIn">
        <div class="bg-white w-full max-w-md rounded-xl shadow-xl animate-scaleIn">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 h-12 w-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-exclamation-triangle text-lg text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Delete User</h3>
                        <p class="text-gray-600 mt-1">
                            Are you sure you want to delete <span id="deleteUserName"
                                class="font-medium text-gray-900"></span>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button onclick="closeDeleteModal()"
                        class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                        Cancel
                    </button>
                    <form method="POST" action="users.php" class="inline">
                        <input type="hidden" name="delete_id" id="deleteUserId">
                        <button type="submit"
                            class="px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">
                            Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        const modal = document.getElementById('modal');
        const viewModal = document.getElementById('viewModal');
        const deleteModal = document.getElementById('deleteModal');

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('modalBtn').textContent = 'Save User';
            document.getElementById('modalBtn').name = 'add_user';
            document.getElementById('user_id').value = '';
            document.getElementById('userForm').reset();
            modal.classList.remove('hidden');
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
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        function openViewModal(data) {
            document.getElementById('view_avatar').textContent =
                data.firstname.substring(0, 1).toUpperCase() + data.lastname.substring(0, 1).toUpperCase();
            document.getElementById('view_name').textContent = data.firstname + ' ' + data.lastname;
            document.getElementById('view_email').textContent = data.email;

            // Set role badge
            const roleBadge = document.getElementById('view_role_badge');
            roleBadge.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ';
            if (data.role === 'Admin') {
                roleBadge.className += 'bg-purple-100 text-purple-800';
                roleBadge.innerHTML = '<i class="fas fa-shield-alt mr-1.5"></i>Admin';
            } else if (data.role === 'MP') {
                roleBadge.className += 'bg-blue-100 text-blue-800';
                roleBadge.innerHTML = '<i class="fas fa-landmark mr-1.5"></i>MP';
            } else {
                roleBadge.className += 'bg-gray-100 text-gray-800';
                roleBadge.innerHTML = '<i class="fas fa-user mr-1.5"></i>Staff';
            }

            // Set status badge
            const statusBadge = document.getElementById('view_status_badge');
            statusBadge.className = 'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ';
            if (data.status === 'Active') {
                statusBadge.className += 'bg-green-100 text-green-800';
                statusBadge.innerHTML = '<span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Active';
            } else {
                statusBadge.className += 'bg-red-100 text-red-800';
                statusBadge.innerHTML = '<span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>Inactive';
            }

            viewModal.classList.remove('hidden');
        }

        function closeViewModal() {
            viewModal.classList.add('hidden');
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').textContent = name;
            deleteModal.classList.remove('hidden');
        }

        function closeDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        function hideToast() {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }
        }

        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        // Auto-hide toast after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const toast = document.getElementById('toast');
            if (toast) {
                setTimeout(hideToast, 5000);
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
                closeDeleteModal();
            }
        });

        // Close modal on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        viewModal.addEventListener('click', (e) => {
            if (e.target === viewModal) closeViewModal();
        });

        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) closeDeleteModal();
        });

        // Toast notification utility functions
        function showToast(type, message) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();

            const toastHTML = `
                <div id="${toastId}" class="animate-slideInRight">
                    <div class="flex items-center justify-between p-4 rounded-lg shadow-lg border ${type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                ${type === 'success' ? '<i class="fas fa-check-circle text-green-500 text-xl"></i>' : '<i class="fas fa-exclamation-circle text-red-500 text-xl"></i>'}
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium ${type === 'success' ? 'text-green-800' : 'text-red-800'}">
                                    ${message}
                                </p>
                            </div>
                        </div>
                        <button onclick="removeToast('${toastId}')" class="ml-4 flex-shrink-0 ${type === 'success' ? 'text-green-400 hover:text-green-600' : 'text-red-400 hover:text-red-600'}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;

            toastContainer.insertAdjacentHTML('beforeend', toastHTML);

            // Auto remove after 5 seconds
            setTimeout(() => removeToast(toastId), 5000);
        }

        function removeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }
        }
    </script>
</body>

</html>