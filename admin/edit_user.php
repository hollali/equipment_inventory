<?php
session_start();
require_once "../config/database.php";

/* 🔐 Validate ID */
if (!isset($_GET["id"])) {
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET["id"]);

/* 🔌 DB */
$db = new Database();
$conn = $db->getConnection();

/* 📥 Fetch user */
$sql = "SELECT id, username, full_name, email, role, status 
        FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

/* 💾 Handle Update */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $role = $_POST["role"];
    $status = $_POST["status"];

    if ($username && $full_name && $email) {

        $updateSql = "UPDATE users 
                      SET username = ?, full_name = ?, email = ?, role = ?, status = ?, updated_at = NOW()
                      WHERE id = ?";

        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param(
            "sssssi",
            $username,
            $full_name,
            $email,
            $role,
            $status,
            $user_id
        );

        if ($updateStmt->execute()) {
            header("Location: users.php?updated=1");
            exit();
        }

        $error = "Failed to update user.";
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit User</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="../css/users.css">
    <link rel="stylesheet" href="../css/edit_user.css">
</head>

<body>

    <div class="page-container">

        <div class="page-header">
            <div>
                <h1>Edit User</h1>
                <p>Update user account information</p>
            </div>

            <a href="users.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i>Back
            </a>
        </div>

        <div class="card form-card">

            <?php if (!empty($error)): ?>
                <div class="alert error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="user-form">

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user["username"]); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user["full_name"]); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user["email"]); ?>" required>
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="admin" <?php if ($user["role"] === "admin")
                                echo "selected"; ?>>Admin</option>
                            <option value="staff" <?php if ($user["role"] === "staff")
                                echo "selected"; ?>>Staff</option>
                            <option value="user" <?php if ($user["role"] === "user")
                                echo "selected"; ?>>User</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="active" <?php if ($user["status"] === "active")
                                echo "selected"; ?>>Active
                            </option>
                            <option value="inactive" <?php if ($user["status"] === "inactive")
                                echo "selected"; ?>>
                                Inactive</option>
                        </select>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-edit">
                        <i class="fa fa-save"></i> Save Changes
                    </button>

                    <a href="users.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>

            </form>
        </div>

    </div>

</body>

</html>