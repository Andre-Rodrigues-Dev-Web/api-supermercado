<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/ProductGallery.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../config/database.php';

class ProductController {
    private $db;
    private $product;
    private $gallery;
    private $supplier;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->product = new Product($this->db);
        $this->gallery = new ProductGallery($this->db);
        $this->supplier = new Supplier($this->db);
    }

    private function verifyAdminToken() {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Token de acesso não fornecido"]);
            return false;
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $token_data = json_decode(base64_decode($token), true);

        if (!$token_data || $token_data['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Acesso negado. Permissões insuficientes"]);
            return false;
        }

        return $token_data;
    }

    //Caso as tabelas de produtos, galerias e fornecedores não existam, crie-as
    public function createTables() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10, 2) NOT NULL,
            stock INT DEFAULT 0,
            image VARCHAR(255),
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS product_gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            is_main TINYINT DEFAULT 0,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            cnpj VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(50),
            zip_code VARCHAR(20),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status TINYINT DEFAULT 1
        )");
    }

    // GET /api/products - Listar todos os produtos
    public function read() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $stmt = $this->product->read();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Adiciona imagens para cada produto
        foreach ($products as &$product) {
            $this->gallery->product_id = $product['id'];
            $stmt = $this->gallery->readByProduct();
            $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        http_response_code(200);
        echo json_encode($products);
    }

    // GET /api/products/:id - Obter um produto específico
    public function read_single($id) {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $this->product->id = $id;
        $stmt = $this->product->read_single();
        
        if ($stmt->rowCount() > 0) {
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obter imagens do produto
            $this->gallery->product_id = $id;
            $stmt = $this->gallery->readByProduct();
            $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode($product);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Produto não encontrado."]);
        }
    }

    // POST /api/products - Criar novo produto
    public function create() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $data = json_decode(file_get_contents("php://input"), true);

        // Validação básica
        if (empty($data['name']) || empty($data['price']) || empty($data['category_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Dados incompletos. Nome, preço e categoria são obrigatórios."]);
            return;
        }

        $this->product->category_id = $data['category_id'];
        $this->product->name = $data['name'];
        $this->product->description = $data['description'] ?? null;
        $this->product->price = $data['price'];
        $this->product->stock = $data['stock'] ?? 0;
        $this->product->image = $data['image'] ?? null;
        $this->product->status = $data['status'] ?? 1;

        $product_id = $this->product->create();

        if ($product_id) {
            // Processar galeria de imagens
            if (!empty($data['gallery'])) {
                foreach ($data['gallery'] as $image) {
                    $this->gallery->product_id = $product_id;
                    $this->gallery->image_path = $image['path'];
                    $this->gallery->is_main = $image['is_main'] ?? 0;
                    $this->gallery->create();
                }
            }

            http_response_code(201);
            echo json_encode([
                "message" => "Produto criado com sucesso",
                "product_id" => $product_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Não foi possível criar o produto"]);
        }
    }

    // PUT /api/products/:id - Atualizar produto
    public function update($id) {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $data = json_decode(file_get_contents("php://input"), true);

        $this->product->id = $id;
        $this->product->category_id = $data['category_id'] ?? null;
        $this->product->name = $data['name'] ?? null;
        $this->product->description = $data['description'] ?? null;
        $this->product->price = $data['price'] ?? null;
        $this->product->stock = $data['stock'] ?? null;
        $this->product->status = $data['status'] ?? null;

        if ($this->product->update()) {
            http_response_code(200);
            echo json_encode(["message" => "Produto atualizado com sucesso"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Não foi possível atualizar o produto"]);
        }
    }

    // DELETE /api/products/:id - Remover produto
    public function delete($id) {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $this->product->id = $id;

        if ($this->product->delete()) {
            // Remover também as imagens da galeria
            $this->gallery->product_id = $id;
            $this->gallery->deleteByProduct();
            
            http_response_code(200);
            echo json_encode(["message" => "Produto removido com sucesso"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Não foi possível remover o produto"]);
        }
    }
}
?>