<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page (optional)
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}
*/

/* 🧪 Enable errors during development */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ✅ Validate ID */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("Invalid item ID");
}

$item_id = (int) $_GET["id"];

/* 🔌 Database */
$db = new Database();
$conn = $db->getConnection();

/* 📥 Fetch item */
$stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Item not found");
}

$item = $result->fetch_assoc();

/* 📥 Categories (ALIAS FIX) */
$categories = $conn->query("
    SELECT id, category_name AS name
    FROM categories
    ORDER BY category_name ASC
");

/* 📥 Suppliers (ALIAS FIX) */
$suppliers = $conn->query("
    SELECT id, supplier_name AS name
    FROM suppliers
    ORDER BY supplier_name ASC
");

/* 💾 Update Item */
if (isset($_POST["update"])) {

    $supplier_id = !empty($_POST["supplier_id"])
        ? (int) $_POST["supplier_id"]
        : null;

    $sql = "
        UPDATE inventory_items SET
            item_name = ?,
            item_code = ?,
            category_id = ?,
            supplier_id = ?,
            quantity = ?,
            min_quantity = ?,
            unit_price = ?,
            location = ?,
            description = ?,
            updated_at = NOW()
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssiii d s s i",
        $_POST["item_name"],
        $_POST["item_code"],
        $_POST["category_id"],
        $supplier_id,
        $_POST["quantity"],
        $_POST["min_quantity"],
        $_POST["unit_price"],
        $_POST["location"],
        $_POST["description"],
        $item_id
    );

    if ($stmt->execute()) {
        header("Location: edit_inventory.php?id=$item_id&success=1");
        exit();
    } else {
        $error = "Failed to update inventory item.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Inventory Item</title>

    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/inventory.css">
    <link rel="stylesheet" href="../css/edit-inventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <!-- PAGE HEADER -->
    <div class="page-title">
        <h1>Edit Inventory Item</h1>
        <p>Update item information</p>
    </div>

    <!-- ACTION BAR -->
    <div class="inventory-actions">
        <a href="inventory.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <!-- FORM -->
    <div class="edit-inventory">
        <form method="POST" class="edit-card">

            <?php if (!empty($error)): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" required value="<?= htmlspecialchars($item["item_name"]) ?>">
                </div>

                <div class="form-group">
                    <label>Item Code</label>
                    <input type="text" name="item_code" required value="<?= htmlspecialchars($item["item_code"]) ?>">
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= $c["id"] ?>" <?= $c["id"] == $item["category_id"] ? "selected" : "" ?>>
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
                            <option value="<?= $s["id"] ?>" <?= $s["id"] == $item["supplier_id"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($s["name"]) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="0" required value="<?= $item["quantity"] ?>">
                </div>

                <div class="form-group">
                    <label>Minimum Quantity</label>
                    <input type="number" name="min_quantity" min="0" required value="<?= $item["min_quantity"] ?>">
                </div>

                <div class="form-group">
                    <label>Unit Price</label>
                    <input type="number" step="0.01" name="unit_price" required value="<?= $item["unit_price"] ?>">
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($item["location"]) ?>">
                </div>

                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" rows="4"><?= htmlspecialchars($item["description"]) ?></textarea>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" name="update" class="btn-save">
                    <i class="fa-solid fa-save"></i> Save Changes
                </button>
            </div>

        </form>
    </div>

    <!-- ✅ SUCCESS TOAST -->
    <?php if (isset($_GET["success"])): ?>
        <div id="toast-success">
            <i class="fa-solid fa-circle-check"></i>
            Inventory item updated successfully
        </div>
    <?php endif; ?>

    <!-- CLEAN URL -->
    <script>
        if (window.location.search.includes("success=1")) {
            const url = new URL(window.location);
            url.searchParams.delete("success");
            window.history.replaceState({}, document.title, url);
        }
    </script>

</body>

</html>