<?php
session_start();
require_once "./config/database.php";

$username = "";
$error = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username and password are required.";
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        if (!$conn) {
            die("Database connection failed.");
        }

        // Fetch user by username and role
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, username, password, status FROM users WHERE username = ? AND role = 'staff'");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            if ($user['status'] !== 'active') {
                echo '<script>alert("Your account is inactive. Contact admin.");</script>';
            } elseif (password_verify($password, $user['password'])) {
                // Login success
                $_SESSION['staff_id'] = $user['id'];
                $_SESSION['staff_name'] = $user['full_name'];
                $_SESSION['staff_username'] = $user['username'];

                // Update last_login
                $update = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($update, "i", $user['id']);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);

                echo '<script>
            alert("Login successful!");
            window.location.href = "staff/dashboard.php";
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
    <title>Staff Login - PSG Inventory System</title>
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
            <?php if ($error !== ''): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" onsubmit="return validateForm()">
                <div class="loginInputsContainer">
                    <h1>Staff Login Panel</h1>
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" placeholder="Enter Your Username" required
                        value="<?= htmlspecialchars($username) ?>">
                    <small id="userError" class="error"></small>
                </div>

                <div class="loginInputsContainer">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input id="password" name="password" type="password" placeholder="Enter Your Password" required>
                        <span class="toggle-password"><i class="fa-solid fa-eye"></i></span>
                    </div>
                    <small id="passError" class="error"></small>
                </div>

                <div class="loginButtonContainer">
                    <button type="submit">LOGIN</button>
                    <p>Admin Login Panel <a href="index.php">Click Here</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="./scripts/login.js" defer></script>
    <script>
        // Password toggle
        const togglePassword = document.querySelector(".toggle-password");
        togglePassword.addEventListener("click", () => {
            const input = document.getElementById("password");
            const icon = togglePassword.querySelector("i");
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        });

        // Front-end validation
        function validateForm() {
            const username = document.getElementById("username").value.trim();
            const password = document.getElementById("password").value.trim();
            let valid = true;

            document.getElementById("userError").textContent = "";
            document.getElementById("passError").textContent = "";

            if (username === "") {
                document.getElementById("userError").textContent = "Username is required";
                valid = false;
            }
            if (password === "") {
                document.getElementById("passError").textContent = "Password is required";
                valid = false;
            } else if (password.length < 6) {
                document.getElementById("passError").textContent = "Password must be at least 6 characters";
                valid = false;
            }
            return valid;
        }
    </script>
</body>

</html>