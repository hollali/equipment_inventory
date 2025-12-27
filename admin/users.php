<?php
session_start();
require_once "../config/database.php";

/* 🔌 Database connection */
$db = new Database();
$conn = $db->getConnection();

/* 🔍 Search */
$search = trim($_GET['search'] ?? '');

$sql = "SELECT id, username, full_name, email, role, status, created_at, updated_at, last_login
        FROM users";

if ($search !== '') {
    $sql .= " WHERE username LIKE ? 
              OR full_name LIKE ? 
              OR email LIKE ?";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $term = "%$search%";
    $stmt->bind_param("sss", $term, $term, $term);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Management</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Page CSS -->
    <link rel="stylesheet" href="../css/users.css">
</head>

<body>

    <div class="page-container">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>User Management</h1>
                <p>Manage system users and access levels</p>
            </div>

            <span class="welcome-text">
                Welcome, <strong><?php echo htmlspecialchars($_SESSION["admin_username"] ?? "Admin"); ?></strong>
            </span>
        </div>

        <!-- Toolbar -->
        <div class="table-toolbar">

            <!-- Dashboard (LEFT) -->
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i>Dashboard
            </a>

            <!-- Search (CENTER) -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search users..." autocomplete="off"
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-edit">
                    <i class="fa fa-search"></i> Search
                </button>
            </form>

            <!-- Add User (RIGHT) -->
            <a href="add_user.php" class="btn btn-add" onclick="openAddUserModal()">
                <i class="fa fa-user-plus"></i> Add User
            </a>

        </div>

        <!-- Users Table -->
        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>#<?php echo $row["id"]; ?></td>
                                <td><?php echo htmlspecialchars($row["username"]); ?></td>
                                <td><?php echo htmlspecialchars($row["full_name"]); ?></td>
                                <td><?php echo htmlspecialchars($row["email"]); ?></td>

                                <td>
                                    <span class="badge role-<?php echo strtolower($row["role"]); ?>">
                                        <?php echo ucfirst($row["role"]); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge status-<?php echo strtolower($row["status"]); ?>">
                                        <?php echo ucfirst($row["status"]); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo $row["last_login"]
                                        ? date("M d, Y H:i", strtotime($row["last_login"]))
                                        : "Never"; ?>
                                </td>

                                <td class="actions">
                                    <a href="view_user.php?id=<?php echo $row["id"]; ?>" class="icon-btn view" title="View">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="edit_user.php?id=<?php echo $row["id"]; ?>" class="icon-btn edit" title="Edit">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <a href="reset_password.php?id=<?php echo $row["id"]; ?>" class="icon-btn reset"
                                        onclick="return confirm('Reset password for this user?');">
                                        <i class="fa fa-key"></i>
                                    </a>
                                    <a href="delete_user.php?id=<?php echo $row["id"]; ?>" class="icon-btn delete"
                                        onclick="return confirm('Delete this user?');">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function openAddUserModal() {
            document.getElementById("addUserModal")?.style.display = "flex";
        }
    </script>

</body>

</html>