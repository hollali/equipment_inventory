<?php
session_start();
require_once "../config/database.php";

/* =======================
   AUTH CHECK
======================= */
if (!isset($_SESSION['staff_id'])) {
    header("Location: ../index.php");
    exit();
}

/* =======================
   DB CONNECTION
======================= */
$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("Database connection failed");
}

/* =======================
   SUBMIT REQUEST
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = $_SESSION['staff_id'];
    $quantity = $_POST['quantity'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    /* =======================
       VALIDATION
    ======================= */
    if (empty($quantity) || empty($reason)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: request-item.php");
        exit();
    }

    if (!is_numeric($quantity) || $quantity <= 0) {
        $_SESSION['error'] = "Quantity must be a valid number.";
        header("Location: request-item.php");
        exit();
    }

    /* =======================
       GENERATE REQUEST CODE
    ======================= */
    $request_code = 'REQ-' . strtoupper(uniqid());

    /* =======================
       INSERT REQUEST
    ======================= */
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO item_request 
        (request_code, user_id, quantity, reason, status, request_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())"
    );

    if (!$stmt) {
        $_SESSION['error'] = "Failed to prepare request.";
        header("Location: request-item.php");
        exit();
    }

    mysqli_stmt_bind_param(
        $stmt,
        "siis",
        $request_code,
        $user_id,
        $quantity,
        $reason
    );

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Request submitted successfully.";
        header("Location: my-requests.php");
    } else {
        $_SESSION['error'] = "Failed to submit request.";
        header("Location: request-item.php");
    }

    mysqli_stmt_close($stmt);
    exit();
}

/* =======================
   INVALID ACCESS
======================= */
header("Location: dashboard.php");
exit();
