<?php
session_start();
require_once "./config/database.php";

/* ================== DATABASE ================== */
$db = new Database();
$conn = $db->getConnection();

/* ================== ADD BRAND ================== */
if (isset($_POST['add_brand'])) {
    $name = trim($_POST['brand_name']);

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO brands (brand_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: brands.php");
    exit();
}

/* ================== UPDATE BRAND ================== */
if (isset($_POST['update_brand'])) {
    $id = (int) $_POST['brand_id'];
    $name = trim($_POST['brand_name']);

    $stmt = $conn->prepare("UPDATE brands SET brand_name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: brands.php");
    exit();
}

/* ================== DELETE BRAND ================== */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM brands WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: brands.php");
    exit();
}

/* ================== SEARCH & PAGINATION ================== */
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

/* ================== COUNT ================== */
$countSql = "SELECT COUNT(*) FROM brands";
$params = [];
$types = "";

if ($search !== '') {
    $countSql .= " WHERE brand_name LIKE ?";
    $params[] = "%$search%";
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

/* ================== FETCH ================== */
$sql = "SELECT * FROM brands";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE brand_name LIKE ?";
    $params[] = "%$search%";
    $types = "s";
}

$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$brands = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brand Management - Admin Dashboard</title>
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
                            class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-tags text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Brand Management</h1>
                            <p class="text-gray-600 text-sm">Manage device brands and manufacturers</p>
                        </div>
                    </div>
                </div>
                <button onclick="openAddModal()"
                    class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:from-purple-700 hover:to-pink-700 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <i class="fas fa-plus mr-2"></i>
                    Add New Brand
                </button>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="mb-8">
            <div class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Brands</p>
                        <p class="text-4xl font-bold text-gray-900"><?= $totalRecords ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?= count($brands) ?> shown on this page
                        </p>
                    </div>
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-purple-100 to-pink-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-building text-3xl text-purple-600"></i>
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
                        placeholder="Search brands by name..."
                        class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                </div>
                <button type="submit"
                    class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <?php if ($search): ?>
                    <a href="brands.php"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors inline-flex items-center">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Brands Grid/Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            <!-- Mobile View: Cards -->
            <div class="md:hidden divide-y divide-gray-200">
                <?php if ($brands): ?>
                    <?php foreach ($brands as $b): ?>
                        <div class="p-5 hover:bg-gray-50 transition-colors scale-in">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3 flex-1">
                                    <div
                                        class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow">
                                        <?= strtoupper(substr($b['brand_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($b['brand_name']) ?></p>
                                        <p class="text-sm text-gray-500">ID: #<?= $b['id'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2 justify-end">
                                <button onclick='openEditModal(<?= json_encode($b) ?>)'
                                    class="flex-1 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors font-medium">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </button>
                                <a href="?delete=<?= $b['id'] ?>"
                                    onclick="return confirm('Delete this brand? This action cannot be undone.')"
                                    class="flex-1 px-4 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors font-medium text-center">
                                    <i class="fas fa-trash mr-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="py-16 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-tags text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-lg font-medium text-gray-900 mb-1">No brands found</p>
                        <p class="text-sm text-gray-500">Try adjusting your search or add a new brand</p>
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
                                Brand Name</th>
                            <th
                                class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($brands): ?>
                            <?php foreach ($brands as $b): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="text-sm font-medium text-gray-600">#<?= $b['id'] ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center text-white font-bold shadow">
                                                <?= strtoupper(substr($b['brand_name'], 0, 2)) ?>
                                            </div>
                                            <span
                                                class="font-semibold text-gray-900 text-base"><?= htmlspecialchars($b['brand_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick='openEditModal(<?= json_encode($b) ?>)'
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                title="Edit Brand">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete=<?= $b['id'] ?>"
                                                onclick="return confirm('Delete this brand? This action cannot be undone.')"
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete Brand">
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
                                            <i class="fas fa-tags text-3xl text-gray-400"></i>
                                        </div>
                                        <p class="text-lg font-medium text-gray-900 mb-1">No brands found</p>
                                        <p class="text-sm text-gray-500">Try adjusting your search or add a new brand</p>
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
                                ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow-lg'
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
                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total brands)
            </p>
        <?php endif; ?>

    </main>

    <!-- Modal -->
    <div id="modal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4 fade-in">
        <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden slide-in"
            onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-8 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 id="modalTitle" class="text-2xl font-bold mb-1"></h2>
                        <p class="text-purple-100 text-sm">Enter brand information below</p>
                    </div>
                    <button onclick="closeModal()" class="text-white/80 hover:text-white transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <form method="POST" class="p-8">
                <input type="hidden" name="brand_id" id="brand_id">

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Brand Name <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fas fa-tag absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="brand_name" id="brand_name" required
                            placeholder="e.g., Apple, Samsung, Dell"
                            class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Enter the official brand or manufacturer name</p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeModal()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button id="modalBtn" type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:from-purple-700 hover:to-pink-700 transition-all shadow-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Save Brand
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBtn = document.getElementById('modalBtn');
        const brand_id = document.getElementById('brand_id');
        const brand_name = document.getElementById('brand_name');

        function openAddModal() {
            modalTitle.textContent = 'Add New Brand';
            modalBtn.name = 'add_brand';
            modalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Brand';
            brand_id.value = '';
            brand_name.value = '';
            brand_name.focus();
            modal.classList.remove('hidden');
        }

        function openEditModal(data) {
            modalTitle.textContent = 'Edit Brand';
            modalBtn.name = 'update_brand';
            modalBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Brand';
            brand_id.value = data.id;
            brand_name.value = data.brand_name;
            brand_name.focus();
            brand_name.select();
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Close modal on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    </script>

</body>

</html>