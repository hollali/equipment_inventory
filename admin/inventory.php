<?php
session_start();
require_once "../config/database.php";

/* ================== DEV ERROR REPORTING ================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ================== DB ================== */
$db = new Database();
$conn = $db->getConnection();
if (!$conn)
    die("Database connection failed");

/* ================== MODE ================== */
$addMode = isset($_GET['add']);
$editMode = isset($_GET['edit']) && is_numeric($_GET['edit']);
$error = "";

/* ================== AUTO ITEM CODE ================== */
if ($addMode) {
    $year = date("Y");

    $q = $conn->query("
        SELECT item_code 
        FROM inventory_items 
        WHERE item_code LIKE 'ITEM-$year-%'
        ORDER BY id DESC
        LIMIT 1
    ");

    $next = 1;
    if ($q && $q->num_rows > 0) {
        $last = $q->fetch_assoc()['item_code'];
        $next = (int) substr($last, -4) + 1;
    }

    $item_code = "ITEM-$year-" . str_pad($next, 4, "0", STR_PAD_LEFT);
}

/* ================== ADD ITEM ================== */
if (isset($_POST['save'])) {

    $item_name = trim($_POST['item_name']);
    $item_code = trim($_POST['item_code']);
    $category_id = (int) $_POST['category_id'];
    #$supplier_id = !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : 0;
    $quantity = (int) $_POST['quantity'];
    #$min_quantity = (int) $_POST['min_quantity'];
    #$unit_price = (float) $_POST['unit_price'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    /* ===== REMOVE STATUS (Let DB trigger handle it) ===== */
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO inventory_items
        (item_name, item_code, category_id,  quantity, location, description)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssisssi",
        $item_name,
        $item_code,
        $category_id,
        #$supplier_id,
        $quantity,
        #$min_quantity,
        #$unit_price,
        $location,
        $description
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: inventory.php?success=added");
    exit();
}

/* ================== UPDATE ITEM ================== */
if (isset($_POST['update'])) {

    $item_id = (int) $_POST['item_id'];
    $item_name = trim($_POST['item_name']);
    $item_code = trim($_POST['item_code']);
    $category_id = (int) $_POST['category_id'];
    #$supplier_id = !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : 0;
    $quantity = (int) $_POST['quantity'];
    #$min_quantity = (int) $_POST['min_quantity'];
    #$unit_price = (float) $_POST['unit_price'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    /* ===== REMOVE STATUS (Let DB trigger handle it) ===== */
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE inventory_items SET
            item_name = ?,
            item_code = ?,
            category_id = ?,
            quantity = ?,
            location = ?,
            description = ?,
            updated_at = NOW()
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssisss",
        $item_name,
        $item_code,
        $category_id,
        #$supplier_id,
        $quantity,
        #$min_quantity,
        #$unit_price,
        $location,
        $description,
        $item_id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: inventory.php?success=updated");
    exit();
}

/* ================== FETCH ITEM (EDIT) ================== */
if ($editMode) {
    $stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    if (!$item)
        die("Item not found");
}

/* ================== DROPDOWNS ================== */
if ($addMode || $editMode) {
    $categories = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
    /*$suppliers = $conn->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");*/
}

/* ================== LIST ================== */
if (!$addMode && !$editMode) {
    $list = $conn->query("
        SELECT
            i.id,
            i.item_name,
            i.item_code,
            i.quantity,
            i.status,
            i.created_at,
            c.category_name
        FROM inventory_items i
        LEFT JOIN categories c ON i.category_id = c.id
        ORDER BY i.id DESC
    ");
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>

    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/inventory.css">
    <link rel="stylesheet" href="../css/add-inventory-modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <?php if ($addMode): ?>
        <!-- ================== ADD MODE (MODAL) ================== -->
        <div class="modal-overlay" id="inventoryModal">
            <div class="modal">

                <div class="modal-header">
                    <h2>Add Inventory Item</h2>
                    <a href="inventory.php" class="close-btn">&times;</a>
                </div>

                <form method="POST" class="modal-body">

                    <?php if (!empty($error)): ?>
                        <div class="error-box">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Item Name *</label>
                            <input type="text" name="item_name" required>
                        </div>

                        <div class="form-group">
                            <label>Item Code</label>
                            <input type="text" name="item_code" value="<?= htmlspecialchars($item_code) ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" required>
                                <option value="">Select Category</option>
                                <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?= $c["id"] ?>">
                                        <?= htmlspecialchars($c["category_name"]) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!--<div class="form-group">
                            <label>Supplier</label>
                            <select name="supplier_id">
                                <option value="">Optional</option>
                                <?/*php while ($s = mysqli_fetch_assoc($suppliers)): */ ?>
                                    <option value="<?= $s["id"] ?>">
                                        <?/*= htmlspecialchars($s["supplier_name"]) */ ?>
                                    </option>
                                <?/*php endwhile; */ ?>
                            </select>
                        </div>-->

                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" min="0" required>
                        </div>

                        <div class="form-group">
                            <label>Minimum Quantity *</label>
                            <input type="number" name="min_quantity" min="0" required>
                        </div>

                        <div class="form-group">
                            <label>Unit Price *</label>
                            <input type="number" step="0.01" name="unit_price" min="0" required>
                        </div>

                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location">
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>

                    </div>

                    <div class="modal-actions">
                        <a href="inventory.php" class="btn-cancel">Cancel</a>
                        <button type="submit" name="save" class="btn-save">
                            <i class="fa-solid fa-plus"></i> Add Item
                        </button>
                    </div>

                </form>

            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", () => {
                document.getElementById("inventoryModal").style.display = "flex";
            });
        </script>

    <?php else: ?>
        <!-- ================== MAIN LAYOUT ================== -->
        <div class="dashboard-container">

            <?php include "sidebar.php"; ?>

            <main class="main-content">

                <!-- ================== PAGE HEADER ================== -->
                <header class="content-header">
                    <div>
                        <h1><?= $editMode ? "Edit Inventory Item" : "IT Inventory Management" ?></h1>
                        <p><?= $editMode ? "Update item information" : "Manage all inventory items" ?></p>
                    </div>
                </header>

                <!-- ================== BACK BUTTON ================== -->
                <?php if ($editMode): ?>
                    <div class="inventory-actions">
                        <a href="inventory.php" class="back-link">
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </a>
                    </div>
                <?php endif; ?>

                <!-- ================== EDIT MODE ================== -->
                <?php if ($editMode): ?>

                    <div class="edit-inventory">
                        <form method="POST" class="edit-card">

                            <input type="hidden" name="item_id" value="<?= $item["id"] ?>">

                            <?php if ($error): ?>
                                <div class="error-box"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <div class="form-grid">

                                <div class="form-group">
                                    <label>Item Name</label>
                                    <input type="text" name="item_name" required
                                        value="<?= htmlspecialchars($item["item_name"]) ?>">
                                </div>

                                <div class="form-group">
                                    <label>Item Code</label>
                                    <input type="text" name="item_code" required
                                        value="<?= htmlspecialchars($item["item_code"]) ?>">
                                </div>

                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php while ($c = $categories->fetch_assoc()): ?>
                                            <option value="<?= $c["id"] ?>" <?= $c["id"] == $item["category_id"] ? "selected" : "" ?>>
                                                <?= htmlspecialchars($c["category_name"]) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Supplier</label>
                                    <select name="supplier_id">
                                        <option value="">None</option>
                                        <?/*php while ($s = $suppliers->fetch_assoc()): */ ?>
                                        <option value="<?= $s["id"] ?>" <?= $s["id"] == $item["supplier_id"] ? "selected" : "" ?>>
                                            <?= htmlspecialchars($s["supplier_name"]) ?>
                                        </option>
                                        <?//php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Quantity</label>
                                    <input type="number" min="0" name="quantity" required value="<?= $item["quantity"] ?>">
                                </div>

                                <div class="form-group">
                                    <label>Minimum Quantity</label>
                                    <input type="number" min="0" name="min_quantity" required
                                        value="<?= $item["min_quantity"] ?>">
                                </div>

                                <div class="form-group">
                                    <label>Unit Price</label>
                                    <input type="number" step="0.01" name="unit_price" required
                                        value="<?= $item["unit_price"] ?>">
                                </div>

                                <div class="form-group">
                                    <label>Location</label>
                                    <input type="text" name="location" value="<?= htmlspecialchars($item["location"]) ?>">
                                </div>

                                <div class="form-group full">
                                    <label>Description</label>
                                    <textarea name="description"
                                        rows="4"><?= htmlspecialchars($item["description"]) ?></textarea>
                                </div>

                            </div>

                            <div class="form-actions">
                                <button type="submit" name="update" class="btn-save">
                                    <i class="fa-solid fa-save"></i> Save Changes
                                </button>
                            </div>

                        </form>
                    </div>

                <?php else: ?>

                    <!-- ================== SEARCH + ADD ================== -->
                    <div class="inventory-actions">
                        <div class="search-wrapper">
                            <input type="text" id="searchInput" placeholder="Search items..." onkeyup="searchTable()">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>

                        <a class="btn btn-add" href="inventory.php?add">
                            <i class="fa-solid fa-plus"></i> Add Item
                        </a>
                    </div>

                    <!-- ================== INVENTORY TABLE ================== -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Item Name</th>
                                    <th>Item Code</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Condition</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php while ($row = $list->fetch_assoc()): ?>

                                    <?php
                                    if ($row["quantity"] <= 0) {
                                        $condition = "Damaged";
                                        $class = "status-damaged";
                                    } elseif ($row["quantity"] /*<= $row["min_quantity"]*/) {
                                        $condition = "Fair";
                                        $class = "status-fair";
                                    } else {
                                        $condition = "Good";
                                        $class = "status-good";
                                    }
                                    ?>

                                    <tr>
                                        <td><?= $row["id"] ?></td>
                                        <td><?= htmlspecialchars($row["item_name"]) ?></td>
                                        <td><?= htmlspecialchars($row["item_code"]) ?></td>
                                        <td><?= htmlspecialchars($row["category_name"] ?? "N/A") ?></td>
                                        <td><?= $row["quantity"] ?></td>
                                        <td><span class="<?= $class ?>"><?= $condition ?></span></td>
                                        <td><?= date("M d, Y", strtotime($row["created_at"])) ?></td>

                                        <td class="action-buttons">

                                            <a class="icon-btn view" href="view_inventory.php?id=<?= $row["id"] ?>" title="View">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>

                                            <a class="icon-btn edit" href="inventory.php?edit=<?= $row["id"] ?>" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>

                                            <a class="icon-btn delete" href="delete_inventory.php?id=<?= $row["id"] ?>"
                                                onclick="return confirm('Delete this inventory item?');" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>

                                        </td>
                                    </tr>

                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </main>
        </div>

        <!-- ================== SEARCH SCRIPT ================== -->
        <script>
            function searchTable() {
                const input = document.getElementById("searchInput").value.toLowerCase();
                const rows = document.querySelectorAll("table tbody tr");

                rows.forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(input)
                        ? ""
                        : "none";
                });
            }
        </script>

    <?php endif; ?>

    <!-- ================== SUCCESS TOAST ================== -->
    <?php if (isset($_GET["success"])): ?>
        <div id="toast-success">
            <i class="fa-solid fa-circle-check"></i>
            <?php
            $msg = $_GET["success"] === "added"
                ? "Inventory item added successfully"
                : "Inventory item updated successfully";
            echo $msg;
            ?>
        </div>
        <script>
            const url = new URL(window.location);
            url.searchParams.delete("success");
            window.history.replaceState({}, document.title, url);
        </script>
    <?php endif; ?>

</body>

</html>