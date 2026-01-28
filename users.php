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

/*  Add User */
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
        $stmt->execute();
        $stmt->close();
    }

    header("Location: users.php");
    exit();
}


/*  Update User */
if (isset($_POST['update_user'])) {
    $id = (int) $_POST['user_id'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $status = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $firstname, $lastname, $email, $role, $status, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: users.php");
    exit();
}

/* Delete User */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: users.php");
    exit();
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
// Get total users
//Already calculated above

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

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main id="mainContent" class="p-6 md:p-12 max-w-[1600px] mx-auto">

        <!-- Header Section -->
        <div class="mb-12">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h1 class="text-4xl md:text-5xl font-medium text-gray-900 mb-3">User Management</h1>
                    <p class="text-lg text-gray-600">Manage system users, roles, and permissions</p>
                </div>
                <button onclick="openAddModal()"
                    class="inline-flex items-center justify-center px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-lg rounded-2xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
                    <i class="fas fa-user-plus mr-3"></i>
                    Add New User
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-8 mb-12">
            <div class="bg-white rounded-3xl shadow-md hover:shadow-xl transition-shadow p-8 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base text-gray-600 mb-2">Total Users</p>
                        <p class="text-4xl font-medium text-gray-900"><?= $totalUsers ?></p>
                    </div>
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-users text-3xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-md hover:shadow-xl transition-shadow p-8 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base text-gray-600 mb-2">Active Users</p>
                        <p class="text-4xl font-medium text-green-600"><?= $activeUsers ?></p>
                    </div>
                    <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-user-check text-3xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-md hover:shadow-xl transition-shadow p-8 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base text-gray-600 mb-2">Inactive Users</p>
                        <p class="text-4xl font-medium text-red-600"><?= $totalUsers - $activeUsers ?></p>
                    </div>
                    <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-user-slash text-3xl text-red-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-md hover:shadow-xl transition-shadow p-8 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base text-gray-600 mb-2">Administrators</p>
                        <p class="text-4xl font-medium text-purple-600"><?= $adminUsers ?></p>
                    </div>
                    <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-user-shield text-3xl text-purple-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-md hover:shadow-xl transition-shadow p-8 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base text-gray-600 mb-2">MPs</p>
                        <p class="text-4xl font-medium text-blue-600"><?= $mpUsers ?></p>
                    </div>
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-landmark text-3xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-md hover:shadow-xl transition-shadow p-8 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-base text-gray-600 mb-2">Staff</p>
                        <p class="text-4xl font-medium text-gray-600"><?= $staffUsers ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-user text-3xl text-gray-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-3xl shadow-md border border-gray-100 p-8 mb-8">
            <form method="GET" class="flex flex-col md:flex-row gap-5">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <i
                            class="fas fa-search absolute left-5 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by username, name, or email..." autocomplete="off"
                            class="w-full pl-14 pr-5 py-4 text-base border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="w-full md:w-56">
                    <select name="role"
                        class="w-full px-5 py-4 text-base border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="mp" <?= $filterRole === 'mp' ? 'selected' : '' ?>>MP</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="w-full md:w-56">
                    <select name="status"
                        class="w-full px-5 py-4 text-base border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="">All Status</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 flex-wrap items-center">
                    <!-- Filter -->
                    <button type="submit"
                        class="px-8 py-4 text-base bg-blue-600 text-white rounded-2xl hover:bg-blue-700 transition-colors shadow-md hover:shadow-lg">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>

                    <!-- Reset -->
                    <a href="users.php"
                        class="px-8 py-4 text-base bg-gray-200 text-gray-700 rounded-2xl hover:bg-gray-300 transition-colors inline-flex items-center shadow-md hover:shadow-lg">
                        <i class="fas fa-redo mr-2"></i>Reset
                    </a>

                    <!-- Export -->
                    <a href="export_users.php?<?= http_build_query($_GET) ?>" class="px-8 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-700 rounded-2xl
               hover:from-green-100 hover:to-emerald-100 hover:border-green-300 transition-all duration-200
               inline-flex items-center gap-2 shadow-sm hover:shadow">
                        <i class="fas fa-download"></i>
                        <span>Export</span>
                    </a>
                </div>

            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-3xl shadow-md border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-8 py-6 text-left text-sm text-gray-600 uppercase tracking-wider">
                                User
                            </th>
                            <th class="px-8 py-6 text-left text-sm text-gray-600 uppercase tracking-wider">
                                Email
                            </th>
                            <th class="px-8 py-6 text-left text-sm text-gray-600 uppercase tracking-wider">
                                Role
                            </th>
                            <th class="px-8 py-6 text-left text-sm text-gray-600 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-8 py-6 text-right text-sm text-gray-600 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-4">
                                            <!--<div
                                                class="w-14 h-14 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-lg">
                                                <?/*= strtoupper(substr($user['firstname'], 0, 2)) */ ?>
                                            </div>-->
                                            <div>
                                                <p class="text-gray-900 text-base">
                                                    <?= htmlspecialchars($user['firstname']) ?>
                                                </p>
                                                <p class="text-base text-gray-500">
                                                    <?= htmlspecialchars($user['lastname']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center text-gray-600">
                                            <i class="fas fa-envelope text-gray-400 mr-3 text-base"></i>
                                            <span class="text-base"><?= htmlspecialchars($user['email']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span
                                                class="inline-flex items-center px-4 py-2 rounded-full text-sm bg-purple-100 text-purple-700">
                                                <i class="fas fa-shield-alt mr-2"></i>Admin
                                            </span>
                                        <?php elseif ($user['role'] === 'mp'): ?>
                                            <span
                                                class="inline-flex items-center px-4 py-2 rounded-full text-sm bg-blue-100 text-blue-700">
                                                <i class="fas fa-landmark mr-2"></i>MP
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-4 py-2 rounded-full text-sm bg-gray-100 text-gray-700">
                                                <i class="fas fa-user mr-2"></i>Staff
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span
                                                class="inline-flex items-center px-4 py-2 rounded-full text-sm bg-green-100 text-green-700">
                                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>Active
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-4 py-2 rounded-full text-sm bg-red-100 text-red-700">
                                                <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center justify-end gap-3">
                                            <button onclick='openViewModal(<?= json_encode([
                                                "firstname" => $user['firstname'],
                                                "lastname" => $user['lastname'],
                                                "email" => $user['email'],
                                                "role" => ucfirst($user['role']),
                                                "status" => ucfirst($user['status']),
                                            ]) ?>)'
                                                class="p-3 text-green-600 hover:bg-green-50 rounded-xl transition-colors"
                                                title="View">
                                                <i class="fas fa-eye text-lg"></i>
                                            </button>
                                            <button onclick='openEditModal(<?= json_encode([
                                                "id" => $user['id'],
                                                "firstname" => $user['firstname'],
                                                "lastname" => $user['lastname'],
                                                "email" => $user['email'],
                                                "role" => $user['role'],
                                                "status" => $user['status']
                                            ]) ?>)'
                                                class="p-3 text-blue-600 hover:bg-blue-50 rounded-xl transition-colors"
                                                title="Edit">
                                                <i class="fas fa-edit text-lg"></i>
                                            </button>
                                            <button
                                                onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>')"
                                                class="p-3 text-red-600 hover:bg-red-50 rounded-xl transition-colors"
                                                title="Delete">
                                                <i class="fas fa-trash text-lg"></i>
                                            </button>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-8 py-20 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                                            <i class="fas fa-users text-4xl text-gray-400"></i>
                                        </div>
                                        <p class="text-xl text-gray-900 mb-2">No users found</p>
                                        <p class="text-base text-gray-500">Try adjusting your search or filters</p>
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
                // Build query string with all current filters
                $queryParams = $_GET;
                unset($queryParams['page']); // Remove page from params to rebuild
            
                // Build the base URL with all parameters
                $baseUrl = '?' . (!empty($queryParams) ? http_build_query($queryParams) . '&' : '');
                ?>

                <div class="flex flex-col items-center justify-between px-8 py-6 border-t border-gray-200">
                    <div class="w-full">
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                            <?php if ($page > 1): ?>
                                <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm hover:shadow">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            // Smart pagination: Show first page, last page, and pages around current
                            $showDotsStart = false;
                            $showDotsEnd = false;

                            for ($i = 1; $i <= $totalPages; $i++):
                                // Show first page, last page, and pages around current (within 2 pages)
                                $shouldShow = false;

                                if ($i == 1 || $i == $totalPages) {
                                    $shouldShow = true;
                                } elseif ($i >= $page - 2 && $i <= $page + 2) {
                                    $shouldShow = true;
                                }

                                if ($shouldShow):
                                    if ($i == 1 && $page > 4):
                                        $showDotsStart = true;
                                        ?>
                                        <a href="<?= $baseUrl ?>page=1"
                                            class="px-4 py-2 rounded-lg transition-colors font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                            1
                                        </a>
                                        <?php if ($showDotsStart): ?>
                                            <span class="px-2 text-gray-400">...</span>
                                        <?php endif; ?>
                                    <?php elseif ($i == $totalPages && $page < $totalPages - 3):
                                        $showDotsEnd = true;
                                        if ($showDotsEnd): ?>
                                            <span class="px-2 text-gray-400">...</span>
                                        <?php endif; ?>
                                        <a href="<?= $baseUrl ?>page=<?= $totalPages ?>"
                                            class="px-4 py-2 rounded-lg transition-colors font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                            <?= $totalPages ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                            class="px-4 py-2 rounded-lg transition-colors font-medium <?= $i == $page
                                                ? 'bg-gradient-to-r from-blue-600 to-blue-600 text-white shadow-lg'
                                                : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm hover:shadow' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"
                                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm hover:shadow">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <p class="text-center text-sm text-gray-500 mt-4">
                            Page <?= $page ?> of <?= $totalPages ?> •
                            Showing <?= min($limit, $totalUsers - (($page - 1) * $limit)) ?> of <?= $totalUsers ?> users
                        </p>

                        <!-- Items per page selector -->
                        <div class="mt-4 flex justify-center">
                            <div class="inline-flex items-center gap-2 bg-gray-50 px-4 py-2 rounded-lg">
                                <span class="text-sm text-gray-600">Show:</span>
                                <select onchange="changeItemsPerPage(this)"
                                    class="text-sm bg-white border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <span class="text-sm text-gray-600">per page</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Add/Edit Modal -->
    <div id="modal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-600 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 id="modalTitle" class="text-2xl font-bold mb-1"></h2>
                        <p class="text-blue-100 text-sm">Enter user information below</p>
                    </div>
                    <button onclick="closeModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <form method="POST" id="userForm" class="p-8">
                <input type="hidden" name="user_id" id="user_id">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="firstname" id="firstname" required placeholder="Enter first name"
                                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="lastname" id="lastname" required placeholder="Enter last name"
                                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i
                                class="fas fa-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="email" name="email" id="email" required placeholder="Enter email address"
                                class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select name="role" id="role" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="mp">MP</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select name="status" id="status" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200 mt-6">
                    <button type="button" onclick="closeModal()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button id="modalBtn" type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-600 text-white rounded-xl hover:from-blue-700 hover:to-blue-700 transition-all shadow-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Save User
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
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-1">User Details</h2>
                        <p class="text-blue-100 text-sm">View user information</p>
                    </div>
                    <button onclick="closeViewModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-8">
                <div class="space-y-6">
                    <div class="flex items-center justify-center mb-6">
                        <div class="w-24 h-24 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-3xl font-bold shadow-lg"
                            id="view_avatar">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Full Name</p>
                            <p class="text-lg font-bold text-gray-900" id="view_name"></p>
                        </div>

                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Email Address
                            </p>
                            <p class="text-lg text-gray-900" id="view_email"></p>
                        </div>

                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Role</p>
                            <p class="text-lg">
                                <span id="view_role_badge"></span>
                            </p>
                        </div>

                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Status</p>
                            <p class="text-lg">
                                <span id="view_status_badge"></span>
                            </p>
                        </div>
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

    <!--// Delete Confirmation Modal --->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 animate-fade-in">
            <h3 class="text-xl font-semibold text-gray-900 mb-2">
                Delete User
            </h3>

            <p class="text-gray-600 mb-6">
                Are you sure you want to delete
                <span id="deleteUserName" class="font-medium text-gray-900"></span>?
                <br>This action cannot be undone.
            </p>

            <div class="flex justify-end gap-3">
                <button onclick="closeDeleteModal()"
                    class="px-5 py-2.5 rounded-xl bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
                    Cancel
                </button>

                <form method="POST" action="users.php">
                    <input type="hidden" name="delete_id" id="deleteUserId">
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-red-600 text-white hover:bg-red-700 transition">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>


    <script>
        const modal = document.getElementById('modal');
        const viewModal = document.getElementById('viewModal');

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New User';
            const btn = document.getElementById('modalBtn');
            btn.innerHTML = '<i class="fas fa-save mr-2"></i>Save User';
            btn.name = 'add_user';
            document.getElementById('user_id').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('firstname').focus();
            modal.classList.remove('hidden');
        }

        function openEditModal(data) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            const btn = document.getElementById('modalBtn');
            btn.innerHTML = '<i class="fas fa-save mr-2"></i>Update User';
            btn.name = 'update_user';
            document.getElementById('user_id').value = data.id;
            document.getElementById('firstname').value = data.firstname;
            document.getElementById('lastname').value = data.lastname;
            document.getElementById('email').value = data.email;
            document.getElementById('role').value = data.role;
            document.getElementById('status').value = data.status;
            document.getElementById('firstname').focus();
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
            roleBadge.className = 'inline-flex items-center px-4 py-2 rounded-full text-sm font-medium ';
            if (data.role === 'Admin') {
                roleBadge.className += 'bg-purple-100 text-purple-700';
                roleBadge.innerHTML = '<i class="fas fa-shield-alt mr-2"></i>Admin';
            } else if (data.role === 'MP') {
                roleBadge.className += 'bg-blue-100 text-blue-700';
                roleBadge.innerHTML = '<i class="fas fa-landmark mr-2"></i>MP';
            } else {
                roleBadge.className += 'bg-gray-100 text-gray-700';
                roleBadge.innerHTML = '<i class="fas fa-user mr-2"></i>Staff';
            }

            // Set status badge
            const statusBadge = document.getElementById('view_status_badge');
            statusBadge.className = 'inline-flex items-center px-4 py-2 rounded-full text-sm font-medium ';
            if (data.status === 'Active') {
                statusBadge.className += 'bg-green-100 text-green-700';
                statusBadge.innerHTML = '<span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>Active';
            } else {
                statusBadge.className += 'bg-red-100 text-red-700';
                statusBadge.innerHTML = '<span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>Inactive';
            }

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

        function openDeleteModal(id, name) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').innerText = name;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        function exportUsers() {
            // Build export URL with current filters
            const url = new URL(window.location.href);
            url.searchParams.set('export', 'csv');
            // You can implement export functionality here
            alert('Export functionality would be implemented here with current filters.');
        }
    </script>

</body>

</html>