<?php
class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $name;
    public $email;
    public $password;
    public $role;
    public $is_active;
    // Outras propriedades conforme sua tabela

    public function __construct($db) {
        $this->conn = $db;
    }
    public function read() {
        $query = "SELECT id, name, email, role, phone, is_active, created_at 
                  FROM " . $this->table . " 
                  ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function read_single() {
        $query = "SELECT * FROM " . $this->table . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET name = :name, email = :email, password = :password, 
                      role = :role, phone = :phone, address = :address, 
                      birth_date = :birth_date, is_active = :is_active,
                      document_number = :document_number, admin_permissions = :admin_permissions,
                      admin_department = :admin_department";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->is_active = htmlspecialchars(strip_tags($this->is_active));
        $this->admin_permissions = htmlspecialchars(strip_tags($this->admin_permissions));
        $this->admin_department = htmlspecialchars(strip_tags($this->admin_department));

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $this->password);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':birth_date', $this->birth_date);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':document_number', $this->document_number);
        $stmt->bindParam(':admin_permissions', $this->admin_permissions);
        $stmt->bindParam(':admin_department', $this->admin_department);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, email = :email, role = :role, 
                      phone = :phone, address = :address, birth_date = :birth_date, 
                      is_active = :is_active, document_number = :document_number, 
                      admin_permissions = :admin_permissions, 
                      admin_department = :admin_department 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Sanitize and bind
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->is_active = htmlspecialchars(strip_tags($this->is_active));
        $this->admin_permissions = htmlspecialchars(strip_tags($this->admin_permissions));
        $this->admin_department = htmlspecialchars(strip_tags($this->admin_department));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':birth_date', $this->birth_date);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':document_number', $this->document_number);
        $stmt->bindParam(':admin_permissions', $this->admin_permissions);
        $stmt->bindParam(':admin_department', $this->admin_department);
        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function search($keywords) {
        $query = "SELECT id, name, email, role, phone, is_active, created_at 
                  FROM " . $this->table . " 
                  WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? 
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);

        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";

        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        $stmt->bindParam(3, $keywords);

        $stmt->execute();
        return $stmt;
    }
}
?>