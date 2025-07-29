<?php
require_once __DIR__ . '/../models/Login.php';
require_once __DIR__ . '/../config/database.php';

class AuthController {
    private $db;
    private $loginModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->loginModel = new Login($this->db);
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->email) && !empty($data->password)) {
            $this->loginModel->email = $data->email;
            $stmt = $this->loginModel->authenticate();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verifica se a conta está ativa primeiro
                if (!$row['is_active']) {
                    http_response_code(403);
                    echo json_encode([
                        "status" => "error", 
                        "message" => "Conta desativada. Contate o suporte."
                    ]);
                    return;
                }

                // Verifica a senha
                if (password_verify($data->password, $row['password'])) {
                    // Prepara os dados do token/sessão
                    $userData = [
                        "id" => $row['id'],
                        "name" => $row['name'],
                        "email" => $row['email'],
                        "role" => $row['role'],
                        "permissions" => $row['admin_permissions'],
                        "department" => $row['admin_department']
                    ];

                    // Resposta de sucesso
                    http_response_code(200);
                    echo json_encode([
                        "status" => "success",
                        "message" => "Login realizado com sucesso!",
                        "user" => $userData,
                        "token" => base64_encode(json_encode($userData))
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode([
                        "status" => "error", 
                        "message" => "Credenciais inválidas."
                    ]);
                }
            } else {
                http_response_code(404);
                echo json_encode([
                    "status" => "error", 
                    "message" => "Administrador não encontrado."
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "status" => "error", 
                "message" => "Dados incompletos. Email e senha são obrigatórios."
            ]);
        }
    }

    public function createUserTable() {
        if ($this->loginModel->createTableIfNotExists()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success", 
                "message" => "Tabela de usuários criada ou já existe."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Erro ao criar tabela de usuários."
            ]);
        }
    }
}
?>