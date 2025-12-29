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

/* ➕ Add Supplier */
if (isset($_POST['add_supplier'])) {
    $name = trim($_POST['supplier_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if ($name !== '') {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO suppliers (supplier_name, email, phone, address) 
            VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $address);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: suppliers.php");
    exit();
}

/* ✏️ Update Supplier */
if (isset($_POST['update_supplier'])) {
    $id = $_POST['supplier_id'];
    $name = trim($_POST['supplier_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE suppliers 
        SET supplier_name = ?, email = ?, phone = ?, address = ?
        WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $phone, $address, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: suppliers.php");
    exit();
}

/* ❌ Delete Supplier */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $stmt = mysqli_prepare($conn, "DELETE FROM suppliers WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: suppliers.php");
    exit();
}

/* 🔍 Search */
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM suppliers";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE supplier_name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = "ssss";
}

$sql .= " ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$suppliers = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Suppliers</title>
    <link rel="stylesheet" href="../css/suppliers.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <!-- 🔹 PAGE HEADER -->
    <header class="page-header">
        <div class="header-left">
            <a href="./dashboard.php" class="btn btn-back"><i class="fa fa-arrow-left"></i></a>
            <h1>Suppliers</h1>
        </div>

        <div class="header-right">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Search suppliers..." autocomplete="off"
                    value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fa fa-search"></i>Search</button>
            </form>

            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa fa-plus"></i> Add Supplier
            </button>
        </div>
    </header>

    <!-- 🔹 TABLE CARD -->
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Supplier Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr>
                            <td><?= $sup['id'] ?></td>
                            <td><?= htmlspecialchars($sup['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($sup['email']) ?></td>
                            <td><?= htmlspecialchars($sup['phone']) ?></td>
                            <td><?= htmlspecialchars($sup['address']) ?></td>
                            <td class="actions">
                                <button class="btn btn-edit" onclick="openEditModal(
                                    '<?= $sup['id'] ?>',
                                    '<?= htmlspecialchars($sup['supplier_name'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($sup['email'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($sup['phone'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($sup['address'], ENT_QUOTES) ?>'
                                )">
                                    <i class="fa fa-pen"></i>
                                </button>

                                <a href="?delete=<?= $sup['id'] ?>" class="btn btn-delete"
                                    onclick="return confirm('Delete this supplier?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No suppliers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ➕ ADD MODAL -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add Supplier</h2>
            <form method="POST">
                <input type="text" name="supplier_name" placeholder="Supplier Name" required>
                <input type="email" name="email" placeholder="Email">
                <input type="text" name="phone" placeholder="Phone">
                <textarea name="address" placeholder="Address"></textarea>
                <button type="submit" name="add_supplier" class="btn btn-add">Save</button>
            </form>
        </div>
    </div>

    <!-- ✏️ EDIT MODAL -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Supplier</h2>
            <form method="POST">
                <input type="hidden" name="supplier_id" id="edit_id">
                <input type="text" name="supplier_name" id="edit_name" required>
                <input type="email" name="email" id="edit_email">
                <input type="text" name="phone" id="edit_phone">
                <textarea name="address" id="edit_address"></textarea>
                <button type="submit" name="update_supplier" class="btn btn-edit">Update</button>
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
        function openEditModal(id, name, email, phone, address) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_address').value = address;
            document.getElementById('editModal').style.display = 'block';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>

</body>

</html>