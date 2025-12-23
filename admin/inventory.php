<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect admin page */
/*if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}*/

/* 🔌 Database connection */
$db = new Database();
$conn = $db->getConnection();

/* 📥 Fetch inventory items with category */
$sql = "
    SELECT 
        i.id,
        i.item_name,
        i.item_code,
        i.quantity,
        i.min_quantity,
        i.created_at,
        c.name AS category_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    ORDER BY i.id DESC
";

$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/inventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<body>

    <div class="page-header">
        <div class="page-title">
            <h1>Inventory Management</h1>
            <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong></p>
        </div>

        <div class="page-actions">
            <a href="dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>

            <a class="btn btn-add" href="add_inventory.php">
                <i class="fa-solid fa-plus"></i> Add Item
            </a>
        </div>
    </div>

    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search items..." onkeyup="searchTable()">
        <i class="fa-solid fa-magnifying-glass"></i>
    </div>



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
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <?php
                    /* 📊 Determine condition */
                    if ($row["quantity"] <= 0) {
                        $condition = "Damaged";
                        $class = "status-damaged";
                    } elseif ($row["quantity"] <= $row["min_quantity"]) {
                        $condition = "Fair";
                        $class = "status-fair";
                    } else {
                        $condition = "Good";
                        $class = "status-good";
                    }
                    ?>

                    <tr>
                        <td><?php echo $row["id"]; ?></td>
                        <td><?php echo htmlspecialchars($row["item_name"]); ?></td>
                        <td><?php echo htmlspecialchars($row["item_code"]); ?></td>
                        <td><?php echo htmlspecialchars($row["category_name"] ?? "N/A"); ?></td>
                        <td><?php echo $row["quantity"]; ?></td>
                        <td><span class="<?php echo $class; ?>"><?php echo $condition; ?></span></td>
                        <td><?php echo $row["created_at"]; ?></td>
                        <td>
                            <a class="icon-btn view" href="view_inventory.php?id=<?php echo $row["id"]; ?>"
                                title="View Details">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a class="icon-btn edit" href="edit_inventory.php?id=<?php echo $row["id"]; ?>" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <a class="icon-btn delete" href="delete_inventory.php?id=<?php echo $row["id"]; ?>"
                                onclick="return confirm('Delete this inventory item?');" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </a>

                        </td>
                    </tr>

                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No inventory items found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput").value.toLowerCase();
            const rows = document.querySelectorAll("table tbody tr");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? "" : "none";
            });
        }
    </script>

</body>

</html>