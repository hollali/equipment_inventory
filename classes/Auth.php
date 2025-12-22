<?php
// classes/Auth.php
require_once __DIR__ . '/../config/database.php';

class Auth
{
    private $conn;
    private $table_name = "users";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection(); // mysqli connection
        session_start(); // ensure sessions are started
    }

    public function login($username, $password, $role)
    {
        // Use prepared statement to prevent SQL injection
        $query = "SELECT id, username, password, role, full_name, email 
                  FROM " . $this->table_name . " 
                  WHERE username = ? AND role = ? AND status = 'active'
                  LIMIT 1";

        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);

            if (password_verify($password, $row['password'])) {
                // Save session
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $row['role'];

                return [
                    'success' => true,
                    'user' => [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'role' => $row['role'],
                        'full_name' => $row['full_name'],
                        'email' => $row['email']
                    ]
                ];
            }
        }

        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }

    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            header("Location: index.php");
            exit();
        }
    }

    public function requireAdmin()
    {
        $this->requireLogin();
        if ($_SESSION['role'] !== 'admin') {
            header("Location: staff/dashboard.php");
            exit();
        }
    }

    public function requireStaff()
    {
        $this->requireLogin();
        if ($_SESSION['role'] !== 'staff') {
            header("Location: admin/dashboard.php");
            exit();
        }
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }
}
?>