<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page (enable in production)
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}
*/

$db = new Database();
$conn = $db->getConnection();

/* ===========================
   DUPLICATE-PROOF AUTO-GENERATE ITEM CODE
=========================== */
$year = date("Y");

/* Get the last item code for the current year */
$result = mysqli_query($conn, "
    SELECT item_code FROM inventory_items 
    WHERE item_code LIKE 'ITEM-$year-%' 
    ORDER BY id DESC 
    LIMIT 1
");

$nextNumber = 1;
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    /* Extract last numeric part */
    $lastCode = $row['item_code']; // e.g., ITEM-2025-0007
    $parts = explode('-', $lastCode);
    $lastNumber = intval(end($parts));
    $nextNumber = $lastNumber + 1;
}

$item_code = "ITEM-$year-" . str_pad($nextNumber, 4, "0", STR_PAD_LEFT);

/* ===========================
   FETCH CATEGORIES
=========================== */
$categories = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name ASC");

/* ===========================
   FETCH SUPPLIERS
=========================== */
$suppliers = mysqli_query($conn, "SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC");

/* ===========================
   SAVE INVENTORY ITEM
=========================== */
if (isset($_POST["save"])) {

    $item_name = trim($_POST["item_name"]);
    $category_id = intval($_POST["category_id"]);
    $supplier_id = !empty($_POST["supplier_id"]) ? intval($_POST["supplier_id"]) : NULL;
    $quantity = intval($_POST["quantity"]);
    $min_quantity = intval($_POST["min_quantity"]);
    $unit_price = floatval($_POST["unit_price"]);
    $location = trim($_POST["location"]);
    $description = trim($_POST["description"]);

    $sql = "INSERT INTO inventory_items 
            (item_name, item_code, category_id, supplier_id, quantity, min_quantity, unit_price, location, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "ssiiiidss",
            $item_name,
            $item_code,
            $category_id,
            $supplier_id,
            $quantity,
            $min_quantity,
            $unit_price,
            $location,
            $description
        );

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION["success_message"] = "Inventory item added successfully!";
            header("Location: inventory.php");
            exit();
        } else {
            $error = "Failed to add inventory item.";
        }

        mysqli_stmt_close($stmt);
    } else {
        $error = "Failed to prepare statement.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Inventory Item</title>

    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/inventory.css">
    <link rel="stylesheet" href="../css/add-inventory-modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

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
                        <input type="text" value="<?= htmlspecialchars($item_code) ?>" disabled>
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

                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id">
                            <option value="">Optional</option>
                            <?php while ($s = mysqli_fetch_assoc($suppliers)): ?>
                                <option value="<?= $s["id"] ?>">
                                    <?= htmlspecialchars($s["supplier_name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

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

</body>

</html>