<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "../config/database.php";

/* 🔐 Protect page */
if (!isset($_SESSION["admin_id"])) {
    die("Unauthorized access");
}

if (!isset($_GET["id"])) {
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET["id"]);

/* 🔌 DB */
$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("Database connection failed");
}

/* 🔑 Generate temp password */
$temp_password = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 8);
$hashed = password_hash($temp_password, PASSWORD_DEFAULT);

/* 🔄 Update password */
$sql = "UPDATE users 
        SET password = ?, force_password_change = 1, updated_at = NOW()
        WHERE id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("si", $hashed, $user_id);
$stmt->execute();

if ($stmt->affected_rows <= 0) {
    die("User not found or password not updated");
}

/* 📝 Log activity */
$log = $conn->prepare("
    INSERT INTO activity_logs (user_id, action)
    VALUES (?, ?)
");

if (!$log) {
    die("Log prepare failed: " . $conn->error);
}

$action = "Password reset by admin";
$log->bind_param("is", $_SESSION["admin_id"], $action);
$log->execute();

/* ✅ Success */
echo "<script>
alert('Temporary Password: {$temp_password}');
window.location='users.php';
</script>";
