<?php
//! config/database.php

class Database
{
    private $host = "localhost";
    private $db_name = "device_inventory";
    private $username = "root";
    private $password = "Vendetta7080";

    public $conn;

    public function getConnection()
    {
        // Connect using mysqli procedural style
        $this->conn = mysqli_connect(
            $this->host,
            $this->username,
            $this->password,
            $this->db_name
        );

        // Check connection
        if (!$this->conn) {
            die("Connection failed: " . mysqli_connect_error());
        }

        // Optional: set charset
        mysqli_set_charset($this->conn, "utf8");

        return $this->conn;
    }
}
?>