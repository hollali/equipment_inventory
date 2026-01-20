<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page (optional)
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid item ID");
}

$item_id = (int) $_GET['id'];

/* 🔌 Database */
$db = new Database();
$conn = $db->getConnection();

/* 📥 Fetch inventory item */
$sql = "
    SELECT 
        i.*,
        c.category_name,
        s.supplier_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE i.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Inventory item not found");
}

$item = $result->fetch_assoc();

/* 📊 Status */
if ($item["quantity"] <= 0) {
    $status = "Damaged";
    $status_class = "status-damaged";
} elseif ($item["quantity"] /*<= $item["min_quantity"]*/) {
    $status = "Fair";
    $status_class = "status-fair";
} else {
    $status = "Good";
    $status_class = "status-good";
}

/* 💰 Total value */
#$total_value = $item["quantity"] * $item["unit_price"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Inventory Item</title>

    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/inventory.css">
    <link rel="stylesheet" href="../css/view-inventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <!-- PAGE HEADER -->
    <div class="page-title">
        <h1>Inventory Item Details</h1>
        <p>Viewing item information</p>
    </div>

    <!-- ACTION BAR -->
    <div class="inventory-actions">
        <a href="inventory.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>

        <div class="status-badge <?= $status_class ?>">
            <?= $status ?>
        </div>
    </div>

    <!-- DETAILS CARD -->
    <div class="inventory-details">
        <div class="details-card">

            <h2><?= htmlspecialchars($item["item_name"]) ?></h2>
            <p class="item-code">Item Code: <?= htmlspecialchars($item["item_code"]) ?></p>

            <div class="details-grid">

                <div class="detail">
                    <span>Category</span>
                    <strong><?= htmlspecialchars($item["category_name"] ?? "N/A") ?></strong>
                </div>

                <!--<div class="detail">
                    <span>Supplier</span>
                    <strong><?//= htmlspecialchars($item["supplier_name"] ?? "N/A") ?></strong>
                </div>-->

                <!--<div class="detail">
                    <span>Quantity</span>
                    <strong><?/*= $item["quantity"] */ ?></strong>
                </div>--->

                <!--<div class="detail">
                    <span>Minimum Quantity</span>
                    <strong>
                        <?/*= $item["min_quantity"] */ ?>
                    </strong>
                </div>-->

                <!--<div class="detail">
                    <span>Unit Price</span>
                    <strong>₵
                        <?/*= number_format($item["unit_price"], 2) */ ?>
                    </strong>
                </div>-->

                <!--<div class="detail">
                    <span>Total Value</span>
                    <strong>₵
                        <?/*= number_format($total_value, 2) */ ?>
                    </strong>
                </div>-->

                <div class="detail">
                    <span>Location</span>
                    <strong>
                        <?= htmlspecialchars($item["location"] ?? "—") ?>
                    </strong>
                </div>

                <div class="detail">
                    <span>Status</span>
                    <strong class="<?= $status_class ?>">
                        <?= $status ?>
                    </strong>
                </div>

            </div>

            <!-- DESCRIPTION -->
            <div class="description-box">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($item["description"] ?? "No description provided")) ?></p>
            </div>

            <!-- META -->
            <div class="meta">
                <div>
                    <span>Created At</span>
                    <strong><?= $item["created_at"] ?></strong>
                </div>
                <div>
                    <span>Last Updated</span>
                    <strong><?= $item["updated_at"] ?></strong>
                </div>
            </div>

        </div>
    </div>

</body>

</html>