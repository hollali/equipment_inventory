<?php
session_start();
require_once "../config/database.php";

if (!isset($_GET["id"])) {
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET["id"]);
$db = new Database();
$conn = $db->getConnection();

/* Generate temp password */
$temp_password = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 8);
$hashed = password_hash($temp_password, PASSWORD_DEFAULT);

/* Update */
$sql = "UPDATE users 
        SET password = ?, force_password_change = 1, updated_at = NOW()
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $hashed, $user_id);
$stmt->execute();

/* Log activity */
$log = $conn->prepare("
    INSERT INTO activity_logs (user_id, action)
    VALUES (?, 'Password reset by admin')
");
$log->bind_param("i", $_SESSION["admin_id"]);
$log->execute();

echo "<script>
alert('Temporary Password: $temp_password');
window.location='users.php';
</script>";
