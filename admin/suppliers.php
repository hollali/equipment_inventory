<?php
session_start();
require_once "../config/database.php";

$db = new Database();
$conn = $db->getConnection();

/* ===================== ADD SUPPLIER ===================== */
if (isset($_POST['add_supplier'])) {
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO suppliers (supplier_name, contact_person, email, phone, address)
         VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $_POST['supplier_name'],
        $_POST['contact_person'],
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
        "UPDATE suppliers 
         SET supplier_name=?, contact_person=?, email=?, phone=?, address=?
         WHERE id=?"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "sssssi",
        $_POST['supplier_name'],
        $_POST['contact_person'],
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

/* ===================== DELETE ===================== */
if (isset($_GET['delete'])) {
    $stmt = mysqli_prepare($conn, "DELETE FROM suppliers WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['delete']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: suppliers.php");
    exit();
}

/* ===================== SEARCH + PAGINATION ===================== */
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

$where = "";
$params = [];
$types = "";

if ($search !== "") {
    $where = "WHERE supplier_name LIKE ?
              OR contact_person LIKE ?
              OR email LIKE ?
              OR phone LIKE ?
              OR address LIKE ?";
    $term = "%$search%";
    $params = [$term, $term, $term, $term, $term];
    $types = "sssss";
}

/* COUNT */
$countSql = "SELECT COUNT(*) AS total FROM suppliers $where";
$countStmt = mysqli_prepare($conn, $countSql);
if ($params)
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
$totalPages = ceil($total / $limit);

/* DATA */
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

/* Calculate pagination info */
$startRecord = $total > 0 ? $offset + 1 : 0;
$endRecord = min($offset + $limit, $total);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - Parliament Inventory</title>
    <link href="images/logo.png" rel="icon" type="image/x-icon">

    <!-- CSS Files -->
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/suppliers.css">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">

        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">

            <!-- Page Header -->
            <header class="content-header">
                <div>
                    <h1>Suppliers</h1>
                    <p>Manage supplier information and contacts</p>
                </div>
                <div class="user-info">
                    <span><?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin' ?></span>
                    <span class="badge badge-admin">ADMIN</span>
                </div>
            </header>

            <!-- Toolbar -->
            <div class="table-toolbar">
                <form method="GET" class="search-form">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" autocomplete="off"
                        placeholder="Search suppliers...">
                    <button type="submit" class="btn btn-search">
                        <i class="fa fa-search"></i> Search
                    </button>
                </form>

                <button class="btn btn-add" onclick="openAddModal()">
                    <i class="fa fa-plus"></i> Add Supplier
                </button>
            </div>

            <!-- Table Card -->
            <div class="content-section">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Supplier</th>
                                <th>Contact Person</th>
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
                                        <td>#<?= $s['id'] ?></td>
                                        <td><?= htmlspecialchars($s['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars($s['contact_person']) ?></td>
                                        <td><?= htmlspecialchars($s['email']) ?></td>
                                        <td><?= htmlspecialchars($s['phone']) ?></td>
                                        <td><?= htmlspecialchars($s['address']) ?></td>
                                        <td class="actions">
                                            <button class="btn btn-view" onclick="openViewModal(
                    '<?= $s['id'] ?>',
                    '<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['contact_person'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['phone'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['address'], ENT_QUOTES) ?>'
                )"><i class="fa fa-eye"></i></button>

                                            <button class="btn btn-edit" onclick="openEditModal(
                    '<?= $s['id'] ?>',
                    '<?= htmlspecialchars($s['supplier_name'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['contact_person'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['phone'], ENT_QUOTES) ?>',
                    '<?= htmlspecialchars($s['address'], ENT_QUOTES) ?>'
                )"><i class="fa fa-pen"></i></button>

                                            <a class="btn btn-delete" href="?delete=<?= $s['id'] ?>"
                                                onclick="return confirm('Delete supplier?')">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: #64748b;">
                                        <i class="fa-solid fa-truck"
                                            style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                                        No suppliers found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?= $startRecord ?> to <?= $endRecord ?> of <?= $total ?> entries
                    </div>

                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a class="page-btn" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                <i class="fa fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <button class="page-btn" disabled>
                                <i class="fa fa-chevron-left"></i>
                            </button>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        ?>

                        <?php if ($start > 1): ?>
                            <a class="page-btn" href="?page=1&search=<?= urlencode($search) ?>">1</a>
                            <?php if ($start > 2): ?>
                                <span class="dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a class="page-btn <?= $i == $page ? 'active' : '' ?>"
                                href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <span class="dots">...</span>
                            <?php endif; ?>
                            <a class="page-btn" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>">
                                <?= $totalPages ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="page-btn" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
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

        </main>
    </div>

    <!-- ADD MODAL -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add Supplier</h2>
            <form method="POST">
                <input name="supplier_name" placeholder="Supplier Name" required>
                <input name="contact_person" placeholder="Contact Person">
                <input name="email" type="email" placeholder="Email">
                <input name="phone" placeholder="Phone">
                <textarea name="address" placeholder="Address"></textarea>
                <button name="add_supplier" class="btn btn-add">Save Supplier</button>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Supplier</h2>
            <form method="POST">
                <input type="hidden" name="supplier_id" id="edit_id">
                <input name="supplier_name" id="edit_name" required>
                <input name="contact_person" id="edit_contact">
                <input name="email" type="email" id="edit_email">
                <input name="phone" id="edit_phone">
                <textarea name="address" id="edit_address"></textarea>
                <button name="update_supplier" class="btn btn-edit">Update Supplier</button>
            </form>
        </div>
    </div>

    <!-- VIEW MODAL -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <h2>Supplier Details</h2>

            <div class="view-group">
                <label>ID</label>
                <p id="v_id"></p>
            </div>

            <div class="view-group">
                <label>Supplier Name</label>
                <p id="v_name"></p>
            </div>

            <div class="view-group">
                <label>Contact Person</label>
                <p id="v_contact"></p>
            </div>

            <div class="view-group">
                <label>Email</label>
                <p id="v_email"></p>
            </div>

            <div class="view-group">
                <label>Phone</label>
                <p id="v_phone"></p>
            </div>

            <div class="view-group">
                <label>Address</label>
                <p id="v_address"></p>
            </div>
        </div>
    </div>

    <script>
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        const viewModal = document.getElementById('viewModal');

        function openAddModal() { addModal.style.display = 'flex' }
        function closeAddModal() { addModal.style.display = 'none' }

        function openEditModal(i, n, c, e, p, a) {
            edit_id.value = i;
            edit_name.value = n;
            edit_contact.value = c;
            edit_email.value = e;
            edit_phone.value = p;
            edit_address.value = a;
            editModal.style.display = 'flex';
        }
        function closeEditModal() { editModal.style.display = 'none' }

        function openViewModal(i, n, c, e, p, a) {
            v_id.textContent = i;
            v_name.textContent = n;
            v_contact.textContent = c || '-';
            v_email.textContent = e || '-';
            v_phone.textContent = p || '-';
            v_address.textContent = a || '-';
            viewModal.style.display = 'flex';
        }
        function closeViewModal() { viewModal.style.display = 'none' }

        // Close modals on outside click
        window.onclick = function (event) {
            if (event.target === addModal) closeAddModal();
            if (event.target === editModal) closeEditModal();
            if (event.target === viewModal) closeViewModal();
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeViewModal();
            }
        });
    </script>

</body>

</html>