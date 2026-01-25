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
    <title>Brands</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-slate-100 text-slate-800">

    <?php include 'sidebar.php'; ?>

    <main class="ml-64 p-6">

        <!-- HEADER -->
        <div class="sticky top-0 bg-slate-100 pb-4 z-10">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Brands</h1>
                    <p class="text-sm text-slate-500">Manage all device brands</p>
                </div>

                <button onclick="openAddModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 shadow">
                    <i class="fa fa-plus"></i>
                    Add Brand
                </button>
            </div>

            <!-- SEARCH -->
            <form method="GET" class="mt-4 max-w-sm">
                <div class="relative">
                    <i class="fa fa-search absolute left-3 top-3 text-slate-400"></i>
                    <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search brands..."
                        class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </form>
        </div>

        <!-- TABLE CARD -->
        <div class="mt-6 bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-6 py-3 text-left">#</th>
                        <th class="px-6 py-3 text-left">Brand Name</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($brands): ?>
                        <?php foreach ($brands as $b): ?>
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-6 py-3">#
                                    <?= $b['id'] ?>
                                </td>
                                <td class="px-6 py-3 font-medium">
                                    <?= htmlspecialchars($b['brand_name']) ?>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end gap-3">
                                        <button onclick='openEditModal(<?= json_encode($b) ?>)'
                                            class="text-blue-600 hover:text-blue-800">
                                            <i class="fa fa-pen"></i>
                                        </button>
                                        <a href="?delete=<?= $b['id'] ?>" onclick="return confirm('Delete this brand?')"
                                            class="text-red-600 hover:text-red-800">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="py-16 text-center text-slate-400">
                                <i class="fa fa-box-open text-4xl mb-3"></i>
                                <p class="text-sm">No brands found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex gap-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="px-3 py-1 rounded-lg text-sm
                    <?= $i == $page
                        ? 'bg-blue-600 text-white'
                        : 'bg-white border hover:bg-slate-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- MODAL -->
    <div id="modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white w-full max-w-md rounded-xl p-6 shadow-lg animate-fadeIn">
            <h2 id="modalTitle" class="text-lg font-bold mb-4"></h2>

            <form method="POST">
                <input type="hidden" name="brand_id" id="brand_id">

                <label class="block text-sm font-medium mb-1">Brand Name</label>
                <input name="brand_name" id="brand_name" required
                    class="w-full border rounded-lg px-3 py-2 mb-5 focus:ring-2 focus:ring-blue-500 outline-none"
                    placeholder="e.g. Apple">

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-lg border hover:bg-slate-50">
                        Cancel
                    </button>
                    <button id="modalBtn" type="submit"
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                        Save
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
            modalTitle.textContent = 'Add Brand';
            modalBtn.name = 'add_brand';
            brand_id.value = '';
            brand_name.value = '';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function openEditModal(data) {
            modalTitle.textContent = 'Edit Brand';
            modalBtn.name = 'update_brand';
            brand_id.value = data.id;
            brand_name.value = data.brand_name;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>

</body>

</html>