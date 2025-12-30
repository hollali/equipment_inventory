<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page (optional)
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}
*/

/* 🔌 Database connection */
$db = new Database();
$conn = $db->getConnection();

/* ➕ Add Category */
if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    $description = trim($_POST['description']);

    if ($name !== '') {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO categories (category_name, description) VALUES (?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ss", $name, $description);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: categories.php");
    exit();
}

/* ✏️ Update Category */
if (isset($_POST['update_category'])) {
    $id = $_POST['category_id'];
    $name = trim($_POST['category_name']);
    $description = trim($_POST['description']);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE categories SET category_name = ?, description = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, "ssi", $name, $description, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: categories.php");
    exit();
}

/* ❌ Delete Category */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: categories.php");
    exit();
}

/* 🔍 Search & Pagination Setup */
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 10; // Items per page
$offset = ($page - 1) * $perPage;

/* Count total records */
$countSql = "SELECT COUNT(*) as total FROM categories";
$countParams = [];
$countTypes = "";

if ($search !== '') {
    $countSql .= " WHERE category_name LIKE ? OR description LIKE ?";
    $searchTerm = "%$search%";
    $countParams = [$searchTerm, $searchTerm];
    $countTypes = "ss";
}

$countStmt = mysqli_prepare($conn, $countSql);
if (!empty($countParams)) {
    mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
}
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
mysqli_stmt_close($countStmt);

$totalPages = ceil($totalRecords / $perPage);

/* Fetch paginated data */
$sql = "SELECT * FROM categories";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE category_name LIKE ? OR description LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
    $types = "ss";
}

$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

/* Calculate pagination info */
$startRecord = $totalRecords > 0 ? $offset + 1 : 0;
$endRecord = min($offset + $perPage, $totalRecords);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Categories</title>

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="../css/categories.css">
</head>

<body>

    <!-- 🔹 PAGE HEADER -->
    <header class="page-header">
        <div class="header-left">
            <a href="./dashboard.php" class="btn btn-back">
                <i class="fa fa-arrow-left"></i> Back
            </a>
            <h1>Categories</h1>
        </div>

        <div class="header-center">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Search categories..." autocomplete="off"
                    value="<?= htmlspecialchars($search) ?>">
                <button type="submit">
                    <i class="fa fa-search"></i> Search
                </button>
            </form>
        </div>

        <div class="header-right">
            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa fa-plus"></i> Add Category
            </button>
        </div>
    </header>

    <!-- 🔹 TABLE CARD -->
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= $cat['id'] ?></td>
                            <td><?= htmlspecialchars($cat['category_name']) ?></td>
                            <td><?= htmlspecialchars($cat['description']) ?></td>
                            <td class="actions">

                                <!-- 👁 View -->
                                <button class="btn btn-view" onclick="openViewModal(
                            '<?= $cat['id'] ?>',
                            '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($cat['description'], ENT_QUOTES) ?>'
                        )">
                                    <i class="fa fa-eye"></i>
                                </button>

                                <!-- ✏ Edit -->
                                <button class="btn btn-edit" onclick="openEditModal(
                            '<?= $cat['id'] ?>',
                            '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($cat['description'], ENT_QUOTES) ?>'
                        )">
                                    <i class="fa fa-pen"></i>
                                </button>

                                <!-- ❌ Delete -->
                                <a href="?delete=<?= $cat['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>&page=<?= $page ?>"
                                    class="btn btn-delete" onclick="return confirm('Delete this category?')">
                                    <i class="fa fa-trash"></i>
                                </a>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 🔹 PAGINATION -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Showing <?= $startRecord ?> to <?= $endRecord ?> of <?= $totalRecords ?> entries
            </div>
            <div class="pagination">
                <?php
                // Build query string for pagination links
                $queryString = $search ? '&search=' . urlencode($search) : '';

                // Previous button
                if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $queryString ?>" class="page-btn">
                        <i class="fa fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <button class="page-btn" disabled>
                        <i class="fa fa-chevron-left"></i>
                    </button>
                <?php endif; ?>

                <?php
                // Page number logic
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                // Show first page if not in range
                if ($startPage > 1): ?>
                    <a href="?page=1<?= $queryString ?>" class="page-btn">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?page=<?= $i ?><?= $queryString ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php
                // Show last page if not in range
                if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="dots">...</span>
                    <?php endif; ?>
                    <a href="?page=<?= $totalPages ?><?= $queryString ?>" class="page-btn"><?= $totalPages ?></a>
                <?php endif; ?>

                <!-- Next button -->
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $queryString ?>" class="page-btn">
                        <i class="fa fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button class="page-btn" disabled>
                        <i class="fa fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ➕ ADD MODAL -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add Category</h2>
            <form method="POST">
                <input type="text" name="category_name" placeholder="Category Name" required>
                <textarea name="description" placeholder="Description"></textarea>
                <button type="submit" name="add_category" class="btn btn-add">
                    Save
                </button>
            </form>
        </div>
    </div>

    <!-- ✏️ EDIT MODAL -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Category</h2>
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_id">
                <input type="text" name="category_name" id="edit_name" required>
                <textarea name="description" id="edit_desc"></textarea>
                <button type="submit" name="update_category" class="btn btn-edit">
                    Update
                </button>
            </form>
        </div>
    </div>

    <!-- 👁 VIEW MODAL -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <h2>View Category</h2>

            <div class="view-group">
                <label>ID</label>
                <p id="view_id"></p>
            </div>

            <div class="view-group">
                <label>Category Name</label>
                <p id="view_name"></p>
            </div>

            <div class="view-group">
                <label>Description</label>
                <p id="view_desc"></p>
            </div>
        </div>
    </div>

    <!-- 🔹 JAVASCRIPT -->
    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(id, name, desc) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_desc').value = desc;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openViewModal(id, name, desc) {
            document.getElementById('view_id').innerText = id;
            document.getElementById('view_name').innerText = name;
            document.getElementById('view_desc').innerText = desc || '—';
            document.getElementById('viewModal').style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
    </script>

</body>

</html>