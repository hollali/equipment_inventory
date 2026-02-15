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
    <!-- Tailwind via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        /* Clean color palette - no gradients */
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-200: #bfdbfe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fed7aa;
            --error: #ef4444;
            --error-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
        }

        body {
            background-color: var(--gray-50);
            color: var(--gray-800);
        }

        /* Card styles - clean, no gradients */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-color: var(--gray-300);
        }

        /* Stat cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--primary-200);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-blue {
            background-color: var(--primary-100);
            color: var(--primary-dark);
        }

        .stat-icon-green {
            background-color: var(--success-light);
            color: #065f46;
        }

        .stat-icon-red {
            background-color: var(--error-light);
            color: #991b1b;
        }

        .stat-icon-purple {
            background-color: #ede9fe;
            color: #6d28d9;
        }

        .stat-icon-amber {
            background-color: var(--warning-light);
            color: #92400e;
        }

        .stat-icon-gray {
            background-color: var(--gray-100);
            color: var(--gray-700);
        }

        /* Filter panel */
        .filter-panel {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Form inputs */
        .form-input {
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: white;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Table styles */
        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead th {
            background-color: var(--gray-50);
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--gray-100);
            transition: background-color 0.15s ease;
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .data-table tbody td {
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            vertical-align: middle;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .status-active {
            background-color: var(--success-light);
            color: #065f46;
            border-color: #a7f3d0;
        }

        .status-inactive {
            background-color: var(--error-light);
            color: #991b1b;
            border-color: #fecaca;
        }

        /* Role badges */
        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .role-admin {
            background-color: var(--primary-100);
            color: #1e40af;
            border-color: var(--primary-200);
        }

        .role-mp {
            background-color: #e0f2fe;
            color: #0c4a6e;
            border-color: #bae6fd;
        }

        .role-staff {
            background-color: var(--gray-100);
            color: var(--gray-700);
            border-color: var(--gray-300);
        }

        /* User avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            background-color: var(--primary);
            color: white;
            flex-shrink: 0;
        }

        .user-avatar-lg {
            width: 80px;
            height: 80px;
            font-size: 2rem;
            border-radius: 16px;
        }

        /* Action buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            background: transparent;
        }

        .action-btn-view {
            color: var(--gray-500);
        }

        .action-btn-view:hover {
            background-color: var(--gray-100);
            color: var(--gray-700);
        }

        .action-btn-edit {
            color: var(--primary);
        }

        .action-btn-edit:hover {
            background-color: var(--primary-100);
            color: var(--primary-dark);
        }

        .action-btn-delete {
            color: var(--error);
        }

        .action-btn-delete:hover {
            background-color: var(--error-light);
            color: #b91c1c;
        }

        /* Button styles */
        .btn-primary {
            background-color: var(--primary);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: white;
            color: var(--gray-700);
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-danger {
            background-color: var(--error);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        /* Pagination */
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 0.5rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
        }

        .pagination-item:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
        }

        .pagination-item.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Toast notifications */
        #toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-width: 400px;
            width: 100%;
        }

        .toast {
            position: relative;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success {
            background-color: var(--success-light);
            border-left-color: var(--success);
            color: #065f46;
        }

        .toast-error {
            background-color: var(--error-light);
            border-left-color: var(--error);
            color: #991b1b;
        }

        .toast-warning {
            background-color: var(--warning-light);
            border-left-color: var(--warning);
            color: #92400e;
        }

        .toast-info {
            background-color: var(--info-light);
            border-left-color: var(--info);
            color: #1e40af;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(0, 0, 0, 0.1);
            width: 100%;
            transform-origin: left;
            animation: progress 5s linear forwards;
        }

        @keyframes progress {
            from {
                transform: scaleX(1);
            }

            to {
                transform: scaleX(0);
            }
        }

        /* Modal styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            background-color: var(--gray-50);
        }

        /* Text utilities */
        .text-ellipsis {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Sidebar adjustment */
        .ml-64 {
            margin-left: 16rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .ml-64 {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>

<body class="antialiased bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">
    <!-- Toast Container -->
    <div id="toast-container"></div>

    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="p-8 ml-64">

        <!-- Display PHP Session Messages as Toasts -->
        <?php if ($toast['type'] && $toast['message']): ?>
            <div id="toast-message" class="hidden" data-type="<?= $toast['type'] ?>"
                data-message="<?= htmlspecialchars($toast['message']) ?>"></div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center shadow-sm">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                </div>
                <p class="text-gray-500 ml-15">Manage system users, roles, and permissions</p>
            </div>
            <button onclick="openAddModal()" class="btn-primary">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $totalUsers ?></p>
                    </div>
                    <div class="stat-icon stat-icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Active</p>
                        <p class="text-2xl font-bold text-green-600"><?= $activeUsers ?></p>
                    </div>
                    <div class="stat-icon stat-icon-green">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Inactive</p>
                        <p class="text-2xl font-bold text-red-600"><?= $totalUsers - $activeUsers ?></p>
                    </div>
                    <div class="stat-icon stat-icon-red">
                        <i class="fas fa-user-slash"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Admins</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $adminUsers ?></p>
                    </div>
                    <div class="stat-icon stat-icon-purple">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">MPs</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $mpUsers ?></p>
                    </div>
                    <div class="stat-icon stat-icon-blue">
                        <i class="fas fa-landmark"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Staff</p>
                        <p class="text-2xl font-bold text-gray-600"><?= $staffUsers ?></p>
                    </div>
                    <div class="stat-icon stat-icon-gray">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-panel">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-4">
                    <label class="form-label">Search Users</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by name or email..." class="form-input pl-10">
                    </div>
                </div>

                <div class="md:col-span-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-input">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="mp" <?= $filterRole === 'mp' ? 'selected' : '' ?>>MP</option>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="">All Status</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="users.php" class="btn-secondary px-4" title="Reset filters">
                        <i class="fas fa-redo-alt"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="role-badge role-admin">
                                                <i class="fas fa-shield-alt mr-1"></i>Admin
                                            </span>
                                        <?php elseif ($user['role'] === 'mp'): ?>
                                            <span class="role-badge role-mp">
                                                <i class="fas fa-landmark mr-1"></i>MP
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge role-staff">
                                                <i class="fas fa-user mr-1"></i>Staff
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-circle text-[8px] mr-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">
                                                <i class="fas fa-circle text-[8px] mr-1"></i>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <button onclick='openViewModal(<?= json_encode([
                                                "firstname" => $user['firstname'],
                                                "lastname" => $user['lastname'],
                                                "email" => $user['email'],
                                                "role" => ucfirst($user['role']),
                                                "status" => ucfirst($user['status']),
                                            ]) ?>)' class="action-btn action-btn-view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick='openEditModal(<?= json_encode([
                                                "id" => $user['id'],
                                                "firstname" => $user['firstname'],
                                                "lastname" => $user['lastname'],
                                                "email" => $user['email'],
                                                "role" => $user['role'],
                                                "status" => $user['status']
                                            ]) ?>)' class="action-btn action-btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button
                                                onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>')"
                                                class="action-btn action-btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div
                                            class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-users text-gray-400 text-xl"></i>
                                        </div>
                                        <p class="text-gray-900 font-medium mb-1">No users found</p>
                                        <p class="text-gray-500">Try adjusting your search or filters</p>
                                        <a href="users.php" class="mt-4 btn-secondary text-sm">
                                            <i class="fas fa-times mr-2"></i>Clear Filters
                                        </a>
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

                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            Showing <span
                                class="font-semibold"><?= min($limit, $totalUsers - (($page - 1) * $limit)) ?></span>
                            of <span class="font-semibold"><?= $totalUsers ?></span> users
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">Show:</span>
                                <select onchange="changeItemsPerPage(this)" class="form-input text-sm py-1.5 w-20">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="pagination-item">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                        class="pagination-item <?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="pagination-item">
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
    <div id="modal" class="hidden modal-backdrop" onclick="closeModalOnBackdrop(event)">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" class="text-lg font-bold text-gray-900">Add New User</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body">
                <form method="POST" id="userForm">
                    <input type="hidden" name="user_id" id="user_id">

                    <div class="space-y-4">
                        <div>
                            <label class="form-label">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="firstname" id="firstname" required class="form-input"
                                placeholder="John">
                        </div>

                        <div>
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastname" id="lastname" class="form-input" placeholder="Doe">
                        </div>

                        <div>
                            <label class="form-label">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required class="form-input"
                                placeholder="john@example.com">
                        </div>

                        <div>
                            <label class="form-label">Role <span class="text-red-500">*</span></label>
                            <select name="role" id="role" required class="form-input">
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                                <option value="mp">MP</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Status <span class="text-red-500">*</span></label>
                            <select name="status" id="status" required class="form-input">
                                <option value="">Select Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">
                    Cancel
                </button>
                <button id="modalBtn" type="submit" form="userForm" class="btn-primary">
                    Save User
                </button>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="hidden modal-backdrop" onclick="closeViewModalOnBackdrop(event)">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-lg font-bold text-gray-900">User Details</h2>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="text-center mb-6">
                    <div class="user-avatar user-avatar-lg mx-auto mb-4">
                        <span id="view_avatar"></span>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Full Name</p>
                        <p id="view_name" class="text-lg font-semibold text-gray-900"></p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Email</p>
                        <p id="view_email" class="text-gray-900"></p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Role</p>
                        <div id="view_role_badge" class="inline-block"></div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Status</p>
                        <div id="view_status_badge" class="inline-block"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button onclick="closeViewModal()" class="btn-primary">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="hidden modal-backdrop" onclick="closeDeleteModalOnBackdrop(event)">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-bold text-gray-900">Delete User</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-700 mb-2">
                            Are you sure you want to delete <span id="deleteUserName"
                                class="font-semibold text-gray-900"></span>?
                        </p>
                        <p class="text-sm text-gray-500">This action cannot be undone. All data associated with this
                            user will be permanently removed.</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button onclick="closeDeleteModal()" class="btn-secondary">
                    Cancel
                </button>
                <form method="POST" action="users.php" class="inline">
                    <input type="hidden" name="delete_id" id="deleteUserId">
                    <button type="submit" class="btn-danger">
                        Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // ==================== TOAST NOTIFICATION SYSTEM ====================
        class Toast {
            constructor(type, title, message, duration = 5000) {
                this.type = type;
                this.title = title;
                this.message = message;
                this.duration = duration;
                this.id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                this.timeout = null;
            }

            show() {
                const container = document.getElementById('toast-container');
                if (!container) return;

                const toast = document.createElement('div');
                toast.id = this.id;
                toast.className = `toast toast-${this.type}`;

                const icons = {
                    'success': '<i class="fas fa-check-circle"></i>',
                    'error': '<i class="fas fa-exclamation-circle"></i>',
                    'warning': '<i class="fas fa-exclamation-triangle"></i>',
                    'info': '<i class="fas fa-info-circle"></i>'
                };

                toast.innerHTML = `
                    <div class="toast-icon text-xl">${icons[this.type]}</div>
                    <div class="toast-content flex-1">
                        <div class="toast-title font-semibold text-sm">${this.title}</div>
                        <div class="toast-message text-sm opacity-90">${this.message}</div>
                    </div>
                    <button class="toast-close w-8 h-8 rounded-lg hover:bg-black/5 transition-colors" onclick="this.closest('.toast').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="toast-progress" style="animation-duration: ${this.duration}ms"></div>
                `;

                container.appendChild(toast);

                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);

                this.timeout = setTimeout(() => {
                    if (toast.parentElement) {
                        toast.classList.remove('show');
                        setTimeout(() => toast.remove(), 300);
                    }
                }, this.duration);
            }

            static showSuccess(message, title = 'Success') {
                new Toast('success', title, message).show();
            }

            static showError(message, title = 'Error') {
                new Toast('error', title, message).show();
            }

            static showWarning(message, title = 'Warning') {
                new Toast('warning', title, message, 7000).show();
            }

            static showInfo(message, title = 'Info') {
                new Toast('info', title, message, 3000).show();
            }
        }

        // ==================== INITIALIZATION ====================
        document.addEventListener('DOMContentLoaded', function () {
            // Show PHP session toast if exists
            const toastMessage = document.getElementById('toast-message');
            if (toastMessage) {
                const type = toastMessage.dataset.type;
                const message = toastMessage.dataset.message;

                if (type === 'success') {
                    Toast.showSuccess(message, 'Success');
                } else if (type === 'error') {
                    Toast.showError(message, 'Error');
                }
            }
        });

        // ==================== MODAL FUNCTIONS ====================
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('modalBtn').innerHTML = 'Add User';
            document.getElementById('modalBtn').name = 'add_user';
            document.getElementById('user_id').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('modal').classList.remove('hidden');
        }

        function openEditModal(data) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('modalBtn').innerHTML = 'Update User';
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

        function closeModalOnBackdrop(event) {
            if (event.target === event.currentTarget) {
                closeModal();
            }
        }

        function openViewModal(data) {
            document.getElementById('view_avatar').textContent =
                (data.firstname.charAt(0) + (data.lastname ? data.lastname.charAt(0) : '')).toUpperCase();
            document.getElementById('view_name').textContent = data.firstname + ' ' + data.lastname;
            document.getElementById('view_email').textContent = data.email;

            const roleBadge = document.getElementById('view_role_badge');
            roleBadge.className = 'role-badge ';
            if (data.role === 'Admin') {
                roleBadge.className += 'role-admin';
                roleBadge.innerHTML = '<i class="fas fa-shield-alt mr-1"></i>Admin';
            } else if (data.role === 'Mp' || data.role === 'MP') {
                roleBadge.className += 'role-mp';
                roleBadge.innerHTML = '<i class="fas fa-landmark mr-1"></i>MP';
            } else {
                roleBadge.className += 'role-staff';
                roleBadge.innerHTML = '<i class="fas fa-user mr-1"></i>Staff';
            }

            const statusBadge = document.getElementById('view_status_badge');
            statusBadge.className = 'status-badge ';
            if (data.status === 'Active') {
                statusBadge.className += 'status-active';
                statusBadge.innerHTML = '<i class="fas fa-circle text-[8px] mr-1"></i>Active';
            } else {
                statusBadge.className += 'status-inactive';
                statusBadge.innerHTML = '<i class="fas fa-circle text-[8px] mr-1"></i>Inactive';
            }

            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function closeViewModalOnBackdrop(event) {
            if (event.target === event.currentTarget) {
                closeViewModal();
            }
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function closeDeleteModalOnBackdrop(event) {
            if (event.target === event.currentTarget) {
                closeDeleteModal();
            }
        }

        // ==================== PAGINATION ====================
        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        // ==================== KEYBOARD SHORTCUTS ====================
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>