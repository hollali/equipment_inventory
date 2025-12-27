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

/* 📥 Fetch Categories */
$result = mysqli_query($conn, "SELECT * FROM categories ORDER BY id DESC");
$categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Categories Management</title>
    <link rel="stylesheet" href="assets/css/categories.css">
</head>

<body>

    <div class="container">
        <div class="page-header">
            <h1>Categories</h1>
            <button class="btn btn-add" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add Category</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Actions</th>
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
                                <button class="btn btn-edit" onclick="openEditModal(
                                '<?= $cat['id'] ?>',
                                '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($cat['description'], ENT_QUOTES) ?>'
                            )">Edit</button>

                                <a href="?delete=<?= $cat['id'] ?>" class="btn btn-delete"
                                    onclick="return confirm('Delete this category?')">
                                    Delete
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

    <!-- ➕ Add Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add Category</h2>
            <form method="POST">
                <input type="text" name="category_name" placeholder="Category Name" required>
                <textarea name="description" placeholder="Description"></textarea>
                <button type="submit" name="add_category" class="btn btn-add">Save</button>
            </form>
        </div>
    </div>

    <!-- ✏️ Edit Modal -->
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
    </script>

</body>

</html>