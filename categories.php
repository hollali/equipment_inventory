<?php
session_start();
require_once "./config/database.php";

/* Database */
$db = new Database();
$conn = $db->getConnection();

// Toast messages
$toast = ['type' => '', 'message' => ''];

/* ➕ Add Category */
if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Category added successfully!'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to add category.'];
        }
        $stmt->close();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Category name cannot be empty.'];
    }
    header("Location: categories.php");
    exit();
}

/* ✏️ Update Category */
if (isset($_POST['update_category'])) {
    $id = (int) $_POST['category_id'];
    $name = trim($_POST['category_name']);

    if ($name !== '') {
        $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Category updated successfully!'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to update category.'];
        }
        $stmt->close();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Category name cannot be empty.'];
    }
    header("Location: categories.php");
    exit();
}

/* Delete */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    // Check if category is used in devices
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM devices WHERE category_id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkStmt->bind_result($deviceCount);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($deviceCount > 0) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Cannot delete category. It is used by ' . $deviceCount . ' device(s).'];
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Category deleted successfully!'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to delete category.'];
        }
        $stmt->close();
    }
    header("Location: categories.php");
    exit();
}

// Get toast message from session
if (isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

/* Search & Pagination */
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

/* Count */
$countSql = "SELECT COUNT(*) FROM categories";
$params = [];
$types = "";

if ($search !== '') {
    $countSql .= " WHERE category_name LIKE ?";
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
$sql = "SELECT * FROM categories";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE category_name LIKE ? ";
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
$categories = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get category statistics for view modal
if (isset($_GET['view'])) {
    $viewId = (int) $_GET['view'];
    $statsStmt = $conn->prepare("
        SELECT 
            c.*,
            COUNT(d.id) as device_count,
            COUNT(CASE WHEN d.status = 'available' THEN 1 END) as available_devices
        FROM categories c
        LEFT JOIN devices d ON c.id = d.category_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $statsStmt->bind_param("i", $viewId);
    $statsStmt->execute();
    $categoryDetails = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categories Management - Admin Dashboard</title>
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

        /* Toast Styles */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            padding: 1rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(400px);
            transition: transform 0.3s ease-out;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast-success {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .toast-error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #7f1d1d;
        }

        .toast-info {
            background-color: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }

        .toast-icon {
            font-size: 1.25rem;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">

    <?php include 'sidebar.php'; ?>

    <!-- Toast Notification -->
    <?php if ($toast['type'] && $toast['message']): ?>
        <div id="toast" class="toast toast-<?= $toast['type'] ?>">
            <i
                class="toast-icon fas fa-<?= $toast['type'] == 'success' ? 'check-circle' : ($toast['type'] == 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <span><?= htmlspecialchars($toast['message']) ?></span>
        </div>
    <?php endif; ?>

    <main id="mainContent" class="p-4 md:p-8 max-w-7xl mx-auto">

        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow">
                            <i class="fas fa-layer-group text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Categories</h1>
                            <p class="text-gray-600 text-sm">Organize and manage inventory categories</p>
                        </div>
                    </div>
                </div>
                <button onclick="openAddModal()"
                    class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow hover:shadow-md">
                    <i class="fas fa-plus mr-2"></i>
                    Add Category
                </button>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="mb-8">
            <div class="bg-white rounded-xl shadow-sm hover:shadow transition-shadow p-6 border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Categories</p>
                        <p class="text-4xl font-bold text-gray-900"><?= $totalRecords ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?= count($categories) ?> shown on this page
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-layer-group text-3xl text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="flex gap-3">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search categories by name..."
                        class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
                <button type="submit"
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <?php if ($search): ?>
                    <a href="categories.php"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors inline-flex items-center">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Categories Grid/Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

            <!-- Mobile View: Cards -->
            <div class="md:hidden divide-y divide-gray-200">
                <?php if ($categories): ?>
                    <?php foreach ($categories as $cat): ?>
                        <div class="p-5 hover:bg-gray-50 transition-colors scale-in">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3 flex-1">
                                    <div
                                        class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow">
                                        <?= strtoupper(substr($cat['category_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 text-lg">
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </p>
                                        <p class="text-sm text-gray-500">ID: #<?= $cat['id'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button onclick='openViewModal(<?= json_encode($cat) ?>)'
                                    class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium">
                                    <i class="fas fa-eye mr-2"></i>View
                                </button>
                                <button onclick='openEditModal(<?= json_encode($cat) ?>)'
                                    class="flex-1 px-4 py-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors font-medium">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </button>
                                <button
                                    onclick='confirmDelete(<?= $cat['id'] ?>, "<?= htmlspecialchars(addslashes($cat['category_name'])) ?>")'
                                    class="flex-1 px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors font-medium">
                                    <i class="fas fa-trash mr-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="py-16 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-layer-group text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-lg font-medium text-gray-900 mb-1">No categories found</p>
                        <p class="text-sm text-gray-500">Try adjusting your search or add a new category</p>
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
                                Category Name</th>
                            <th
                                class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($categories): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="text-sm font-medium text-gray-600">#<?= $cat['id'] ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold shadow">
                                                <?= strtoupper(substr($cat['category_name'], 0, 2)) ?>
                                            </div>
                                            <span
                                                class="font-semibold text-gray-900 text-base"><?= htmlspecialchars($cat['category_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick='openViewModal(<?= json_encode($cat) ?>)'
                                                class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick='openEditModal(<?= json_encode($cat) ?>)'
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="Edit Category">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button
                                                onclick='confirmDelete(<?= $cat['id'] ?>, "<?= htmlspecialchars(addslashes($cat['category_name'])) ?>")'
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete Category">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
                                            <i class="fas fa-layer-group text-3xl text-gray-400"></i>
                                        </div>
                                        <p class="text-lg font-medium text-gray-900 mb-1">No categories found</p>
                                        <p class="text-sm text-gray-500">Try adjusting your search or add a new category</p>
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
                                ? 'bg-blue-600 text-white shadow'
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
                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total categories)
            </p>
        <?php endif; ?>

    </main>

    <!-- Add/Edit Modal -->
    <div id="addEditModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-blue-600 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 id="modalTitle" class="text-2xl font-bold mb-1"></h2>
                        <p class="text-blue-100 text-sm">Enter category information below</p>
                    </div>
                    <button onclick="closeAddEditModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <form method="POST" id="categoryForm" class="p-8">
                <input type="hidden" name="category_id" id="category_id">

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-tag absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="category_name" id="category_name" required
                            placeholder="e.g., Laptops, Smartphones, Accessories"
                            class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Choose a descriptive name for this category</p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeAddEditModal()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button id="modalBtn" type="submit"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow font-medium">
                        <i class="fas fa-save mr-2"></i>Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gray-700 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-1">Category Details</h2>
                        <p class="text-gray-300 text-sm">View category information and statistics</p>
                    </div>
                    <button onclick="closeViewModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div id="viewCategoryIcon"
                        class="w-16 h-16 bg-blue-600 rounded-xl flex items-center justify-center text-white font-bold text-2xl shadow">
                    </div>
                    <div>
                        <h3 id="viewCategoryName" class="text-xl font-bold text-gray-900"></h3>
                        <p id="viewCategoryId" class="text-sm text-gray-500"></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-blue-700 mb-1">Total Devices</p>
                        <p id="viewDeviceCount" class="text-2xl font-bold text-blue-900">0</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-green-700 mb-1">Available Devices</p>
                        <p id="viewAvailableDevices" class="text-2xl font-bold text-green-900">0</p>
                    </div>
                </div>

                <div class="text-sm text-gray-600">
                    <p class="mb-2"><i class="fas fa-calendar-alt mr-2 text-gray-400"></i>Created: <span
                            id="viewCreatedAt"></span></p>
                    <p><i class="fas fa-history mr-2 text-gray-400"></i>Last Updated: <span id="viewUpdatedAt"></span>
                    </p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200 mt-6">
                    <button type="button" onclick="closeViewModal()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                    <button id="editFromViewBtn" type="button"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        <i class="fas fa-edit mr-2"></i>Edit Category
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in">
        <div class="bg-white w-full max-w-md rounded-xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-red-600 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-1">Confirm Deletion</h2>
                        <p class="text-red-100 text-sm">This action cannot be undone</p>
                    </div>
                    <button onclick="closeDeleteModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="p-8">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Delete Category</h3>
                        <p class="text-sm text-gray-500">You are about to delete:</p>
                    </div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="font-semibold text-red-800 text-lg" id="deleteCategoryName"></p>
                    <p class="text-sm text-red-600 mt-1" id="deleteCategoryId"></p>
                </div>

                <p class="text-gray-600 mb-6">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                    All associated devices will remain but will no longer have a category association.
                </p>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeDeleteModal()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <a id="confirmDeleteBtn" href="#"
                        class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">
                        <i class="fas fa-trash mr-2"></i>Delete Category
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // Toast notification
        <?php if ($toast['type'] && $toast['message']): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const toast = document.getElementById('toast');
                if (toast) {
                    setTimeout(() => {
                        toast.classList.add('show');
                    }, 100);

                    setTimeout(() => {
                        toast.classList.remove('show');
                        setTimeout(() => {
                            toast.remove();
                        }, 300);
                    }, 5000);
                }
            });
        <?php endif; ?>

        // Modal elements
        const addEditModal = document.getElementById('addEditModal');
        const viewModal = document.getElementById('viewModal');
        const deleteModal = document.getElementById('deleteModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBtn = document.getElementById('modalBtn');
        const category_id = document.getElementById('category_id');
        const category_name = document.getElementById('category_name');

        // Add/Edit Modal Functions
        function openAddModal() {
            modalTitle.textContent = 'Add New Category';
            modalBtn.name = 'add_category';
            modalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Category';
            category_id.value = '';
            category_name.value = '';
            category_name.focus();
            addEditModal.classList.remove('hidden');
        }

        function openEditModal(data) {
            closeViewModal();
            modalTitle.textContent = 'Edit Category';
            modalBtn.name = 'update_category';
            modalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Category';
            category_id.value = data.id;
            category_name.value = data.category_name;
            category_name.focus();
            category_name.select();
            addEditModal.classList.remove('hidden');
        }

        function closeAddEditModal() {
            addEditModal.classList.add('hidden');
        }

        // View Modal Functions
        function openViewModal(data) {
            document.getElementById('viewCategoryName').textContent = data.category_name;
            document.getElementById('viewCategoryId').textContent = 'ID: #' + data.id;
            document.getElementById('viewCategoryIcon').textContent = data.category_name.substring(0, 2).toUpperCase();

            // Set dates (you might need to adjust based on your data structure)
            document.getElementById('viewCreatedAt').textContent = 'N/A';
            document.getElementById('viewUpdatedAt').textContent = 'N/A';

            // Load additional stats via AJAX
            loadCategoryStats(data.id);

            // Set edit button
            document.getElementById('editFromViewBtn').onclick = function () {
                openEditModal(data);
            };

            viewModal.classList.remove('hidden');
        }

        function closeViewModal() {
            viewModal.classList.add('hidden');
        }

        // Delete Modal Functions
        function confirmDelete(id, name) {
            document.getElementById('deleteCategoryName').textContent = name;
            document.getElementById('deleteCategoryId').textContent = 'ID: #' + id;
            document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
            deleteModal.classList.remove('hidden');
        }

        function closeDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        // Load category statistics
        function loadCategoryStats(categoryId) {
            // You can implement AJAX here to load real statistics
            // For now, using placeholders
            document.getElementById('viewDeviceCount').textContent = 'Loading...';
            document.getElementById('viewAvailableDevices').textContent = 'Loading...';

            // Simulate loading
            setTimeout(() => {
                document.getElementById('viewDeviceCount').textContent = '0';
                document.getElementById('viewAvailableDevices').textContent = '0';
            }, 300);
        }

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAddEditModal();
                closeViewModal();
                closeDeleteModal();
            }
        });

        // Close modals on backdrop click
        [addEditModal, viewModal, deleteModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        if (modal === addEditModal) closeAddEditModal();
                        if (modal === viewModal) closeViewModal();
                        if (modal === deleteModal) closeDeleteModal();
                    }
                });
            }
        });
    </script>

</body>

</html>