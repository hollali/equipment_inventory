<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page (optional)
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}
*/

$db = new Database();
$conn = $db->getConnection();

/* 📥 Categories */
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

/* 📥 Suppliers */
$suppliers = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");

/* 💾 Save item */
if (isset($_POST["save"])) {

    $sql = "
        INSERT INTO inventory_items
        (item_name, item_code, category_id, supplier_id, quantity, min_quantity,
         unit_price, location, description, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssiiiddss",
        $_POST["item_name"],
        $_POST["item_code"],
        $_POST["category_id"],
        $_POST["supplier_id"],
        $_POST["quantity"],
        $_POST["min_quantity"],
        $_POST["unit_price"],
        $_POST["location"],
        $_POST["description"]
    );

    if ($stmt->execute()) {
        header("Location: inventory.php?added=1");
        exit();
    } else {
        $error = "Failed to add inventory item.";
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

    <!-- MODAL OVERLAY -->
    <div class="modal-overlay" id="inventoryModal">

        <div class="modal">

            <!-- MODAL HEADER -->
            <div class="modal-header">
                <h2>Add Inventory Item</h2>
                <a href="inventory.php" class="close-btn">&times;</a>
            </div>

            <!-- MODAL BODY -->
            <form method="POST" class="modal-body">

                <?php if (!empty($error)): ?>
                    <div class="error-box"><?= $error ?></div>
                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" name="item_name" required>
                    </div>

                    <div class="form-group">
                        <label>Item Code</label>
                        <input type="text" name="item_code" required>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?= $c["id"] ?>">
                                    <?= htmlspecialchars($c["name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php while ($s = $suppliers->fetch_assoc()): ?>
                                <option value="<?= $s["id"] ?>">
                                    <?= htmlspecialchars($s["name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Minimum Quantity</label>
                        <input type="number" name="min_quantity" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Unit Price</label>
                        <input type="number" step="0.01" name="unit_price" required>
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

                <!-- MODAL ACTIONS -->
                <div class="modal-actions">
                    <a href="inventory.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="save" class="btn-save">
                        <i class="fa-solid fa-plus"></i> Add Item
                    </button>
                </div>

            </form>

        </div>

    </div>

</body>

</html>