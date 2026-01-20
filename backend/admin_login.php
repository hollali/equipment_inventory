<?php
session_start();
require_once __DIR__ . "/../config/database.php";

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

// Get form data
$username = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");

// Basic validation
if ($username === "" || $password === "") {
    $_SESSION["error"] = "All fields are required";
    header("Location: ../index.php");
    exit();
}

// Connect to database
$database = new Database();
$conn = $database->getConnection();

// Prepare SQL
$sql = "SELECT id, username, password FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    $_SESSION["error"] = "Something went wrong";
    header("Location: ../index.php");
    exit();
}

// Bind + execute
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Check result
if ($row = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $row["password"])) {

        // Login success
        $_SESSION["admin_id"] = $row["id"];
        $_SESSION["admin_username"] = $row["username"];

        header("Location: ../admin/dashboard.php");
        exit();
    }
}

// Login failed
$_SESSION["error"] = "Invalid username or password";
header("Location: ../index.php");
exit();
