<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal-overlay">
        <div class="modal">

            <div class="modal-header">
                <h2>Add New User</h2>
                <button class="close-btn" onclick="closeAddUserModal()">&times;</button>
            </div>

            <form method="POST" action="add_user.php" class="user-form">

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-add">
                        <i class="fa fa-user-plus"></i> Create User
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">
                        Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>

</body>

</html>