<?php
class Login {
    private $conn;
    private $table = 'users';

    public $id;
    public $name;
    public $email;
    public $password;
    public $role;
    public $is_active;
    public $admin_permissions;
    public $admin_department;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function authenticate() {
        $query = "SELECT id, name, email, password, role, is_active, admin_permissions, admin_department 
                  FROM " . $this->table . " 
                  WHERE email = :email AND role = 'admin' LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        return $stmt;
    }

    public function createTableIfNotExists() {
        $query = "CREATE TABLE IF NOT EXISTS " . $this->table . " (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            is_active TINYINT(1) DEFAULT 1,
            admin_permissions TEXT,
            admin_department VARCHAR(100)
        )";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }
}
?>