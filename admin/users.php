<?php
session_start();
require_once "../config/database.php";

/* 🔌 Database connection */
$db = new Database();
$conn = $db->getConnection();

/* ➕ ADD USER - Handle form submission */
if (isset($_POST['submit']) && $_POST['submit'] == 'add_user') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Duplicate check
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        echo '<script>alert("Username or email already exists!"); window.location="users.php";</script>';
        exit();
    }
    $checkStmt->close();

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare and execute insert statement
    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $username, $full_name, $email, $hashed_password, $role, $status);

    if ($stmt->execute()) {
        header("Location: users.php?success=added");
        exit();
    } else {
        $error_message = "Error adding user: " . $stmt->error;
    }
    $stmt->close();
}

/* ✏️ UPDATE USER - Handle form submission */
if (isset($_POST['submit']) && $_POST['submit'] == 'update_user') {
    $user_id = intval($_POST['user_id']);
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Duplicate check excluding current user
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $checkStmt->bind_param("ssi", $username, $email, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        echo '<script>alert("Username or email already exists for another user!"); window.location="users.php";</script>';
        exit();
    }
    $checkStmt->close();

    $updateSql = "UPDATE users 
                  SET username = ?, full_name = ?, email = ?, role = ?, status = ?, updated_at = NOW()
                  WHERE id = ?";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssssi", $username, $full_name, $email, $role, $status, $user_id);

    if ($updateStmt->execute()) {
        header("Location: users.php?success=updated");
        exit();
    } else {
        $error_message = "Error updating user: " . $updateStmt->error;
    }
    $updateStmt->close();
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Parliament Inventory</title>
    <link href="images/logo.png" rel="icon" type="image/x-icon">

    <!-- CSS Files -->
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/users.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">

        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">

            <!-- Page Header -->
            <header class="content-header">
                <div>
                    <h1>User Management</h1>
                    <p>Manage system users and access levels</p>
                </div>
                <div class="user-info">
                    <span><?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin' ?></span>
                    <span class="badge badge-admin">ADMIN</span>
                </div>
            </header>

            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] == 'added'): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> User added successfully!
                    </div>
                <?php elseif ($_GET['success'] == 'updated'): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> User updated successfully!
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Toolbar -->
            <div class="table-toolbar">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search users..." autocomplete="off"
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-search">
                        <i class="fa fa-search"></i> Search
                    </button>
                </form>

                <button class="btn btn-add" onclick="openAddUserModal()">
                    <i class="fa fa-user-plus"></i> Add User
                </button>
            </div>

            <!-- Users Table -->
            <div class="content-section">
                <div class="table-container">
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
                                            <a href="view_user.php?id=<?php echo $row["id"]; ?>" class="icon-btn view"
                                                title="View">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <button class="icon-btn edit" title="Edit" onclick="openEditUserModal(
                                                <?= $row['id'] ?>,
                                                '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>',
                                                '<?= $row['role'] ?>',
                                                '<?= $row['status'] ?>'
                                            )">
                                                <i class="fa fa-pen"></i>
                                            </button>
                                            <a href="reset_password.php?id=<?php echo $row["id"]; ?>" class="icon-btn reset"
                                                title="Reset Password"
                                                onclick="return confirm('Reset password for this user?');">
                                                <i class="fa fa-key"></i>
                                            </a>
                                            <a href="delete_user.php?id=<?php echo $row["id"]; ?>" class="icon-btn delete"
                                                title="Delete User" onclick="return confirm('Delete this user?');">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="fa-solid fa-users"
                                            style="font-size: 3rem; margin-bottom: 1rem; display: block; color: #64748b;"></i>
                                        No users found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAddUserModal()">&times;</span>
            <h2>Add New User</h2>
            <form method="POST" action="users.php">
                <input type="text" name="username" placeholder="Username" required>
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required minlength="6">
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="staff">Staff</option>
                </select>
                <select name="status" required>
                    <option value="">Select Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <button type="submit" name="submit" value="add_user">Add User</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditUserModal()">&times;</span>
            <h2>Edit User</h2>
            <form method="POST" action="users.php">
                <input type="hidden" name="user_id" id="edit_user_id">

                <input type="text" name="username" id="edit_username" placeholder="Username" required>
                <input type="text" name="full_name" id="edit_full_name" placeholder="Full Name" required>
                <input type="email" name="email" id="edit_email" placeholder="Email" required>

                <select name="role" id="edit_role" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="staff">Staff</option>
                    <option value="user">User</option>
                </select>

                <select name="status" id="edit_status" required>
                    <option value="">Select Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <button type="submit" name="submit" value="update_user">Update User</button>
            </form>
        </div>
    </div>

    <script>
        // Add User Modal
        function openAddUserModal() {
            document.getElementById("addUserModal").style.display = "flex";
        }

        function closeAddUserModal() {
            document.getElementById("addUserModal").style.display = "none";
        }

        // Edit User Modal
        function openEditUserModal(id, username, fullName, email, role, status) {
            document.getElementById("edit_user_id").value = id;
            document.getElementById("edit_username").value = username;
            document.getElementById("edit_full_name").value = fullName;
            document.getElementById("edit_email").value = email;
            document.getElementById("edit_role").value = role;
            document.getElementById("edit_status").value = status;
            document.getElementById("editUserModal").style.display = "flex";
        }

        function closeEditUserModal() {
            document.getElementById("editUserModal").style.display = "none";
        }

        // Close modals on outside click
        window.onclick = function (event) {
            const addModal = document.getElementById("addUserModal");
            const editModal = document.getElementById("editUserModal");

            if (event.target === addModal) {
                closeAddUserModal();
            }
            if (event.target === editModal) {
                closeEditUserModal();
            }
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAddUserModal();
                closeEditUserModal();
            }
        });

        // Auto-hide success message after 5 seconds
        setTimeout(function () {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    </script>

</body>

</html>