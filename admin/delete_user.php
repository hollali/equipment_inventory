<?php
session_start();
require_once "../config/database.php";

/* 🔐 Validate request */
if (!isset($_GET["id"])) {
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET["id"]);

/* 🔐 Prevent self-delete */
if (isset($_SESSION["admin_id"]) && $_SESSION["admin_id"] == $user_id) {
    header("Location: users.php?error=self_delete");
    exit();
}

/* 🔌 DB */
$db = new Database();
$conn = $db->getConnection();

/* ✅ Check user exists */
$checkSql = "SELECT id FROM users WHERE id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $user_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    header("Location: users.php?error=not_found");
    exit();
}

/* 🗑️ Delete user */
$deleteSql = "DELETE FROM users WHERE id = ?";
$deleteStmt = $conn->prepare($deleteSql);
$deleteStmt->bind_param("i", $user_id);

if ($deleteStmt->execute()) {
    header("Location: users.php?deleted=1");
    exit();
}

header("Location: users.php?error=delete_failed");
exit();
