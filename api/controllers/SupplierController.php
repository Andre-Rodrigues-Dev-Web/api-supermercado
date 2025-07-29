<?php
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../config/database.php';

class SupplierController {
    private $db;
    private $supplier;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
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

    // GET /api/suppliers - Listar todos os fornecedores
    public function read() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $stmt = $this->supplier->read();
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode($suppliers);
    }

    // POST /api/suppliers - Criar novo fornecedor
    public function create() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $data = json_decode(file_get_contents("php://input"), true);

        // Validação básica
        if (empty($data['nome']) || empty($data['cnpj'])) {
            http_response_code(400);
            echo json_encode(["message" => "Nome e CNPJ são obrigatórios"]);
            return;
        }

        $this->supplier->nome = $data['nome'];
        $this->supplier->cnpj = $data['cnpj'];
        $this->supplier->email = $data['email'] ?? null;
        $this->supplier->telefone = $data['telefone'] ?? null;
        $this->supplier->endereco = $data['endereco'] ?? null;
        $this->supplier->cidade = $data['cidade'] ?? null;
        $this->supplier->estado = $data['estado'] ?? null;
        $this->supplier->cep = $data['cep'] ?? null;
        $this->supplier->observacoes = $data['observacoes'] ?? null;
        $this->supplier->status = $data['status'] ?? 1;

        if ($this->supplier->create()) {
            http_response_code(201);
            echo json_encode(["message" => "Fornecedor criado com sucesso"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Não foi possível criar o fornecedor"]);
        }
    }
}
?>