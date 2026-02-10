<?php
// login.php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            // Create Database instance and get connection
            $database = new Database();
            $conn = $database->getConnection();

            // Check if user exists using mysqli
            $stmt = $conn->prepare("
                SELECT u.id, u.firstname, u.lastname, u.email, u.role, u.status, u.password_hash,
                       u.ad_username, u.ad_department, u.is_ad_user
                FROM users u
                WHERE u.email = ? AND u.status = 'active'
                LIMIT 1
            ");

            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['firstname'] = $user['firstname'];
                    $_SESSION['lastname'] = $user['lastname'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['ad_username'] = $user['ad_username'];
                    $_SESSION['ad_department'] = $user['ad_department'];
                    $_SESSION['is_ad_user'] = $user['is_ad_user'];
                    $_SESSION['login_time'] = time();

                    // Update last login timestamp
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW(), update_at = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $user['id']);
                    $updateStmt->execute();

                    // Log successful login
                    $logMessage = "User {$user['email']} logged in successfully";
                    file_put_contents('logs/login.log', date('Y-m-d H:i:s') . " - $logMessage\n", FILE_APPEND);

                    // Close statements
                    $stmt->close();
                    $updateStmt->close();
                    $conn->close();

                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password.';

                    // Log failed login attempt
                    $logMessage = "Failed login attempt for email: $email";
                    file_put_contents('logs/failed_login.log', date('Y-m-d H:i:s') . " - $logMessage\n", FILE_APPEND);
                }
            } else {
                $error = 'Invalid email or password.';
            }

            // Close statement and connection
            if (isset($stmt))
                $stmt->close();
            if (isset($conn))
                $conn->close();

        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
            // Log error
            file_put_contents('logs/error.log', date('Y-m-d H:i:s') . " - Login error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Parliament ICT Systems Login</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .error-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .loading {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-50 text-gray-900">
    <div
        class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] -z-10">
    </div>

    <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-6 py-10">
        <!-- Logo and Header -->
        <div class="mb-8 text-center">
            <div class="mb-4 flex justify-center">
                <div
                    class="flex h-20 w-20 items-center justify-center rounded-2xl bg-white shadow-lg ring-1 ring-gray-200/50">
                    <img src="images/logo.png" alt="Parliament of Ghana" class="h-16 w-16 object-contain" />
                </div>
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500 font-medium">Parliament of Ghana</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">ICT Directorate</h1>
                <p class="mt-2 text-sm text-gray-600">Device Inventory Management System</p>
            </div>
        </div>

        <!-- Login Card -->
        <div class="w-full rounded-2xl bg-white p-8 shadow-xl ring-1 ring-gray-200/50">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-900">System Login</h2>
                <p class="mt-1 text-sm text-gray-500">Sign in to access the device inventory system</p>
            </div>

            <!-- Display Error/Success Messages -->
            <?php if ($error): ?>
                <div id="errorMsg"
                    class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 error-shake">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>
                            <?php echo htmlspecialchars($error); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div id="successMsg"
                    class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>
                            <?php echo htmlspecialchars($success); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form id="loginForm" action="" method="post" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="email">
                        <i class="fas fa-envelope mr-1 text-gray-400"></i> Email
                    </label>
                    <div class="relative">
                        <input id="email" name="email" type="email" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm shadow-sm transition-all
                                   focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none
                                   placeholder:text-gray-400" placeholder="name@parliament.gov.gh" autocomplete="off"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required />
                        <div class="absolute right-3 top-3.5 text-gray-400">
                            <i class="fas fa-at text-sm"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5" for="password">
                        <i class="fas fa-lock mr-1 text-gray-400"></i> Password
                    </label>
                    <div class="relative">
                        <input id="password" name="password" type="password" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm shadow-sm transition-all
                                   focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none
                                   placeholder:text-gray-400" placeholder="Enter your password" autocomplete="off"
                            required />
                        <button type="button" onclick="togglePassword()"
                            class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
                            <i id="passwordToggle" class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                    <!--<div class="mt-1 flex justify-end">
                        <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
                            Forgot password?
                        </a>
                    </div>-->
                </div>

                <!--<div class="flex items-center">
                    <input id="remember" name="remember" type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                </div>-->

                <button type="submit" id="loginButton"
                    class="w-full rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3.5 text-sm font-semibold 
                               text-white shadow-sm hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 
                               focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 flex items-center justify-center">
                    <span id="buttonText">Sign In</span>
                    <i id="loadingIcon" class="fas fa-spinner loading ml-2 hidden"></i>
                </button>
            </form>

            <!-- Info Box -->
            <div class="mt-6 rounded-lg bg-blue-50 border border-blue-200 p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                    <div>
                        <p class="text-xs font-medium text-blue-800">System Access Information</p>
                        <p class="text-xs text-blue-600 mt-1">
                            • Use your Parliament email credentials<br>
                            • Contact ICT Help desk for account issues<br>
                            • Ensure your account is active
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-xs text-gray-500">
                &copy;
                <?php echo date('Y'); ?> Parliament of Ghana - ICT Directorate<br>
                <span class="text-gray-400">v1.0 • Device Inventory System</span>
            </p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggle');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            const button = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const loadingIcon = document.getElementById('loadingIcon');

            // Disable button and show loading
            button.disabled = true;
            button.classList.add('opacity-75');
            buttonText.textContent = 'Signing in...';
            loadingIcon.classList.remove('hidden');

            // Remove any existing error shake
            const errorMsg = document.getElementById('errorMsg');
            if (errorMsg) {
                errorMsg.classList.remove('error-shake');
                void errorMsg.offsetWidth; // Trigger reflow
                errorMsg.classList.add('error-shake');
            }
        });

        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function () {
            const emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
            }

            // Enter key submits form
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.target.matches('button, [type="submit"]')) {
                    const activeElement = document.activeElement;
                    if (activeElement && activeElement.form) {
                        activeElement.form.dispatchEvent(new Event('submit', { cancelable: true }));
                    }
                }
            });
        });

        // Clear error when user starts typing
        document.getElementById('email').addEventListener('input', function () {
            const errorMsg = document.getElementById('errorMsg');
            if (errorMsg) errorMsg.style.display = 'none';
        });

        document.getElementById('password').addEventListener('input', function () {
            const errorMsg = document.getElementById('errorMsg');
            if (errorMsg) errorMsg.style.display = 'none';
        });
    </script>
</body>

</html>