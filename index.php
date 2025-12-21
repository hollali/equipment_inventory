<?php
// index.php
session_start();
require_once 'classes/Auth.php';

$auth = new Auth();
$error = '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: staff/dashboard.php");
    }
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['selector'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        $result = $auth->login($username, $password, $role);

        if ($result['success']) {
            // Set session variables
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['username'] = $result['user']['username'];
            $_SESSION['role'] = $result['user']['role'];
            $_SESSION['full_name'] = $result['user']['full_name'];
            $_SESSION['email'] = $result['user']['email'];

            // Redirect based on role
            if ($result['user']['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: staff/dashboard.php");
            }
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parliamentary Service of Ghana Inventory Management System</title>
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
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" onsubmit="return validateForm()">
                <div class="loginInputsContainer">
                    <label for="username">Username</label>
                    <input id="username" placeholder="Enter Your Username" type="text" name="username"
                        required="required" autocomplete="off"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
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
                <div class="selectorContainer">
                    <label for="">Select Role</label>
                    <select name="selector" id="selector" required="required">
                        <option value="admin" <?php echo (isset($_POST['selector']) && $_POST['selector'] === 'admin') ? 'selected' : ''; ?>>ADMIN</option>
                        <option value="staff" <?php echo (isset($_POST['selector']) && $_POST['selector'] === 'staff') ? 'selected' : ''; ?>>STAFF</option>
                    </select>
                </div>
                <div class="loginButtonContainer">
                    <button type="submit">LOGIN</button>
                    <p>Forgot Password? <a href="">Click Here</a></p>
                </div>
            </form>
        </div>
    </div>
    <script src="./scripts/login.js" defer></script>
</body>

</html>