<?php
// classes/Auth.php
require_once 'config/database.php';

class Auth
{
    private $conn;
    private $table_name = "users";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function login($username, $password, $role)
    {
        $query = "SELECT id, username, password, role, full_name, email 
                  FROM " . $this->table_name . " 
                  WHERE username = :username AND role = :role AND status = 'active'
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":role", $role);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $row['password'])) {
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