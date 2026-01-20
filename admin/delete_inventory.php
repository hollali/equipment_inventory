<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page (recommended)
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}
*/

/* ✅ Validate ID */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: inventory.php");
    exit();
}

$item_id = (int) $_GET["id"];

/* 🔌 Database */
$db = new Database();
$conn = $db->getConnection();

/* 🔍 Check if item exists */
$check_sql = "SELECT id FROM inventory_items WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: inventory.php");
    exit();
}

/* 🗑️ Delete item */
$delete_sql = "DELETE FROM inventory_items WHERE id = ?";
$stmt = $conn->prepare($delete_sql);
$stmt->bind_param("i", $item_id);

if ($stmt->execute()) {
    header("Location: inventory.php?deleted=1");
    exit();
} else {
    header("Location: inventory.php?error=delete_failed");
    exit();
}
