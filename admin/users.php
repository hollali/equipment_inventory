<?php
session_start();
require_once "../config/database.php";

/* 🔐 Protect page */
/*if (!isset($_SESSION["admin_id"])) {
    header("Location: ../index.php");
    exit();
}*/

/* 🔌 Database connection */
$db = new Database();
$conn = $db->getConnection();

/* 📥 Fetch users */
$sql = "SELECT id, username, role, created_at FROM users ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f3f3f3;
        }

        .btn {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-edit {
            background: #0d6efd;
            color: #fff;
        }

        .btn-delete {
            background: #dc3545;
            color: #fff;
        }
    </style>
</head>

<body>

    <h1>Users Management</h1>

    <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION["admin_username"]); ?></strong></p>

    <a href="dashboard.php">← Back to Dashboard</a>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Username</th>
                <th>Role</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $row["id"]; ?></td>
                        <td><?php echo htmlspecialchars($row["username"]); ?></td>
                        <td><?php echo ucfirst($row["role"]); ?></td>
                        <td><?php echo $row["created_at"]; ?></td>
                        <td>
                            <a class="btn btn-edit" href="edit_user.php?id=<?php echo $row["id"]; ?>">Edit</a>
                            <a class="btn btn-delete" href="delete_user.php?id=<?php echo $row["id"]; ?>"
                                onclick="return confirm('Are you sure you want to delete this user?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No users found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>

</html>