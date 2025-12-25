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

$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View User</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="../css/users.css">
    <link rel="stylesheet" href="../css/view_user.css">
</head>

<body>

    <div class="page-container">

        <div class="page-header">
            <div>
                <h1>User Profile</h1>
                <p>View user account details</p>
            </div>

            <a href="users.php" class="btn btn-secondary">
                ← Back to Users
            </a>
        </div>

        <div class="profile-card">

            <div class="profile-header">
                <div class="avatar">
                    <i class="fa fa-user"></i>
                </div>
                <div>
                    <h2><?php echo htmlspecialchars($user["full_name"]); ?></h2>
                    <p><?php echo htmlspecialchars($user["email"]); ?></p>
                </div>
            </div>

            <div class="profile-grid">

                <div class="info">
                    <label>Username</label>
                    <span><?php echo htmlspecialchars($user["username"]); ?></span>
                </div>

                <div class="info">
                    <label>Role</label>
                    <span class="badge role-<?php echo strtolower($user["role"]); ?>">
                        <?php echo ucfirst($user["role"]); ?>
                    </span>
                </div>

                <div class="info">
                    <label>Status</label>
                    <span class="badge status-<?php echo strtolower($user["status"]); ?>">
                        <?php echo ucfirst($user["status"]); ?>
                    </span>
                </div>

                <div class="info">
                    <label>Last Login</label>
                    <span>
                        <?php echo $user["last_login"]
                            ? date("M d, Y H:i", strtotime($user["last_login"]))
                            : "Never"; ?>
                    </span>
                </div>

                <div class="info">
                    <label>Created At</label>
                    <span><?php echo date("M d, Y", strtotime($user["created_at"])); ?></span>
                </div>

                <div class="info">
                    <label>Updated At</label>
                    <span><?php echo date("M d, Y", strtotime($user["updated_at"])); ?></span>
                </div>

            </div>

        </div>
    </div>

</body>

</html>