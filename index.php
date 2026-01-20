<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "./config/database.php";

$username = "";
$password = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        echo '<script>alert("Username and password are required.");</script>';
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        if (!$conn) {
            die("Database connection failed.");
        }

        // Fetch user with role 'admin'
        $stmt = mysqli_prepare($conn, "SELECT id, username, full_name, password, status FROM users WHERE username = ? AND role = 'admin'");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 1) {
            $admin = mysqli_fetch_assoc($result);

            if ($admin['status'] !== 'active') {
                echo '<script>alert("Your account is inactive. Contact the system administrator.");</script>';
            } elseif (password_verify($password, $admin['password'])) {
                // Login success
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_username'] = $admin['username'];

                // Update last login
                $update = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($update, "i", $admin['id']);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);

                echo '<script>
                    alert("Login successful!");
                    window.location.href = "admin/dashboard.php";
                </script>';
                exit();
            } else {
                echo '<script>alert("Invalid username or password.");</script>';
            }
        } else {
            echo '<script>alert("Invalid username or password.");</script>';
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PSG Inventory System</title>
    <link href="images/logo.png" rel="icon" type="image/x-icon">
    <link rel="stylesheet" href="./css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>

<body>
    <div class="container">
        <div class="loginHeader">
            <h1>Parliamentary Service of Ghana</h1>
            <h3>Inventory Management System</h3>
        </div>
        <div class="loginBody">
            <form action="" method="POST" onsubmit="return validateForm()">
                <div class="loginInputsContainer">
                    <h1>Admin Login Panel</h1>
                    <label for="username">Username</label>
                    <input id="username" placeholder="Enter Your Username" type="text" name="username" required
                        autocomplete="off" value="<?= htmlspecialchars($username) ?>">
                    <small id="userError" class="error"></small>
                </div>
                <div class="loginInputsContainer">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input id="password" placeholder="Enter Your Password" type="password" name="password" required
                            autocomplete="off" />
                        <span class="toggle-password" onclick="togglePassword()">
                            <i class="fa-solid fa-eye"></i>
                        </span>
                    </div>
                    <small id="passError" class="error"></small>
                </div>
                <div class="loginButtonContainer">
                    <button type="submit">LOGIN</button>
                    <p>Staff Login Panel <a href="staff-login.php">Click Here</a></p>
                </div>
            </form>
        </div>
    </div>
    <script src="./scripts/login.js" defer></script>
</body>

</html>