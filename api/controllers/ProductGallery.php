<?php
class ProductGallery {
    private $conn;
    private $table = 'product_gallery';

    public $id;
    public $product_id;
    public $image_path;
    public $is_main;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readByProduct() {
        $query = "SELECT * FROM " . $this->table . " WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->product_id);
        $stmt->execute();
        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                 SET product_id=:product_id, image_path=:image_path, is_main=:is_main";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $this->product_id);
        $stmt->bindParam(':image_path', $this->image_path);
        $stmt->bindParam(':is_main', $this->is_main);

        return $stmt->execute();
    }

    public function deleteByProduct() {
        $query = "DELETE FROM " . $this->table . " WHERE product_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->product_id);
        return $stmt->execute();
    }
}
?>