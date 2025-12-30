<?php
session_start();

/* 🔐 Protect page (optional)
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}
*/
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Settings</title>

    <!-- ✅ Correct CSS path -->
    <link rel="stylesheet" href="../css/settings.css">
</head>

<body>

    <div class="container">

        <div class="page-header">
            <!-- 🔙 Back button -->
            <a href="dashboard.php" class="btn btn-back">
                <i class="fa-solid fa-arrow-left"></i>Back</a>
            <h1>Settings</h1>
        </div>

        <div class="settings-grid">

            <a href="categories.php" class="settings-card">
                <h3>Categories</h3>
                <p>Manage inventory categories</p>
            </a>

            <a href="suppliers.php" class="settings-card">
                <h3>Suppliers</h3>
                <p>Manage suppliers & vendors</p>
            </a>

            <a href="users.php" class="settings-card">
                <h3>Users</h3>
                <p>Manage system users</p>
            </a>

            <a href="reset_password.php" class="settings-card">
                <h3>Reset Password</h3>
                <p>Change admin password</p>
            </a>

            <div class="settings-card disabled">
                <h3>System Settings</h3>
                <p>Coming soon</p>
            </div>

        </div>

    </div>

</body>

</html>