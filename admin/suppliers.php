<?php
session_start();
require_once "../config/database.php";

$db = new Database();
$conn = $db->getConnection();

/* ===================== ADD SUPPLIER ===================== */
if (isset($_POST['add_supplier'])) {
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO suppliers (supplier_name, email, phone, address)
         VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $_POST['supplier_name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['address']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: suppliers.php");
    exit();
}

/* ===================== UPDATE SUPPLIER ===================== */
if (isset($_POST['update_supplier'])) {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE suppliers SET supplier_name=?, email=?, phone=?, address=? WHERE id=?"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "ssssi",
        $_POST['supplier_name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['address'],
        $_POST['supplier_id']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: suppliers.php");
    exit();
}

/* ===================== DELETE SUPPLIER ===================== */
if (isset($_GET['delete'])) {
    $stmt = mysqli_prepare($conn, "DELETE FROM suppliers WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['delete']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: suppliers.php");
    exit();
}

/* ===================== SEARCH & PAGINATION ===================== */
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

$where = "";
$params = [];
$types = "";

if ($search !== "") {
    $where = "WHERE supplier_name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ?";
    $term = "%$search%";
    $params = [$term, $term, $term, $term];
    $types = "ssss";
}

/* Count */
$countSql = "SELECT COUNT(*) as total FROM suppliers $where";
$countStmt = mysqli_prepare($conn, $countSql);
if ($params)
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
$totalPages = ceil($total / $limit);

/* Data */
$sql = "SELECT * FROM suppliers $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$suppliers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Suppliers</title>
    <link rel="stylesheet" href="../css/suppliers.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <header class="page-header">
        <div class="header-left">
            <a href="dashboard.php" class="btn btn-back">
                <i class="fa fa-arrow-left"></i> Back
            </a>
            <h1>Suppliers</h1>
        </div>

        <div class="header-right">
            <form method="GET" class="search-box">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search suppliers...">
                <button type="submit">
                    <i class="fa fa-search"></i>
                </button>
            </form>

            <button class="btn btn-add" onclick="openAddModal()">
                <i class="fa fa-plus"></i> Add Supplier
            </button>
        </div>
    </header>


    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($suppliers):
                    foreach ($suppliers as $s): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td><?= htmlspecialchars($s['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td><?= htmlspecialchars($s['phone']) ?></td>
                            <td><?= htmlspecialchars($s['address']) ?></td>
                            <td class="actions">
                                <button class="btn btn-view"
                                    onclick="openViewModal('<?= $s['id'] ?>','<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES) ?>','<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>','<?= htmlspecialchars($s['phone'], ENT_QUOTES) ?>','<?= htmlspecialchars($s['address'], ENT_QUOTES) ?>')"><i
                                        class="fa fa-eye"></i></button>
                                <button class="btn btn-edit"
                                    onclick="openEditModal('<?= $s['id'] ?>','<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES) ?>','<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>','<?= htmlspecialchars($s['phone'], ENT_QUOTES) ?>','<?= htmlspecialchars($s['address'], ENT_QUOTES) ?>')"><i
                                        class="fa fa-pen"></i></button>
                                <a class="btn btn-delete" href="?delete=<?= $s['id'] ?>"
                                    onclick="return confirm('Delete supplier?')"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6">No suppliers found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        <div class="pagination-info">
            Page <?= $page ?> of <?= $totalPages ?> • <?= $total ?> Suppliers
        </div>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                    <i class="fa fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            ?>

            <?php if ($start > 1): ?>
                <a class="page-btn" href="?page=1&search=<?= urlencode($search) ?>">1</a>
                <span class="dots">…</span>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a class="page-btn <?= $i == $page ? 'active' : '' ?>"
                    href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <span class="dots">…</span>
                <a class="page-btn" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>">
                    <?= $totalPages ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a class="page-btn" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                    <i class="fa fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>


    <!-- ADD -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add Supplier</h2>
            <form method="POST">
                <input name="supplier_name" required placeholder="Name">
                <input name="email" placeholder="Email">
                <input name="phone" placeholder="Phone">
                <textarea name="address" placeholder="Address"></textarea>
                <button name="add_supplier" class="btn btn-add">Save</button>
            </form>
        </div>
    </div>

    <!-- EDIT -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Supplier</h2>
            <form method="POST">
                <input type="hidden" name="supplier_id" id="edit_id">
                <input name="supplier_name" id="edit_name" required>
                <input name="email" id="edit_email">
                <input name="phone" id="edit_phone">
                <textarea name="address" id="edit_address"></textarea>
                <button name="update_supplier" class="btn btn-edit">Update</button>
            </form>
        </div>
    </div>

    <!-- VIEW -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <h2>Supplier Details</h2>
            <p><b>ID:</b> <span id="v_id"></span></p>
            <p><b>Name:</b> <span id="v_name"></span></p>
            <p><b>Email:</b> <span id="v_email"></span></p>
            <p><b>Phone:</b> <span id="v_phone"></span></p>
            <p><b>Address:</b> <span id="v_address"></span></p>
        </div>
    </div>

    <script>
        function openAddModal() { addModal.style.display = 'block' }
        function closeAddModal() { addModal.style.display = 'none' }
        function openEditModal(i, n, e, p, a) {
            edit_id.value = i; edit_name.value = n; edit_email.value = e; edit_phone.value = p; edit_address.value = a;
            editModal.style.display = 'block';
        }
        function closeEditModal() { editModal.style.display = 'none' }
        function openViewModal(i, n, e, p, a) {
            v_id.textContent = i; v_name.textContent = n; v_email.textContent = e || '-';
            v_phone.textContent = p || '-'; v_address.textContent = a || '-';
            viewModal.style.display = 'block';
        }
        function closeViewModal() { viewModal.style.display = 'none' }
    </script>

</body>

</html>