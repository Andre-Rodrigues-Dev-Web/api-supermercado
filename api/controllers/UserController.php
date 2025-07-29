<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/database.php';

class UserController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
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

    // GET /api/users - Listar todos os usuários
    public function read() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $stmt = $this->user->read();
        $num = $stmt->rowCount();

        if ($num > 0) {
            $users_arr = array();
            $users_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $user_item = array(
                    "id" => $id,
                    "name" => $name,
                    "email" => $email,
                    "role" => $role,
                    "phone" => $phone,
                    "is_active" => $is_active,
                    "created_at" => $created_at
                );
                array_push($users_arr["data"], $user_item);
            }

            http_response_code(200);
            echo json_encode($users_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Nenhum usuário encontrado."));
        }
    }

    // GET /api/users/:id - Obter um usuário específico
    public function read_single() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $this->user->id = isset($_GET['id']) ? $_GET['id'] : die();

        $stmt = $this->user->read_single();
        $num = $stmt->rowCount();

        if ($num > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Não retornar a senha mesmo sendo hash
            unset($row['password']);
            unset($row['reset_token']);
            unset($row['reset_token_expires']);

            http_response_code(200);
            echo json_encode($row);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Usuário não encontrado."));
        }
    }

    // POST /api/users - Criar novo usuário
    public function create() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->name) && !empty($data->email) && !empty($data->password)) {
            $this->user->name = $data->name;
            $this->user->email = $data->email;
            $this->user->password = $data->password;
            $this->user->role = $data->role ?? 'user';
            $this->user->phone = $data->phone ?? null;
            $this->user->address = $data->address ?? null;
            $this->user->birth_date = $data->birth_date ?? null;
            $this->user->is_active = $data->is_active ?? true;
            $this->user->document_number = $data->document_number ?? null;
            $this->user->admin_permissions = $data->admin_permissions ?? null;
            $this->user->admin_department = $data->admin_department ?? null;

            if ($this->user->create()) {
                http_response_code(201);
                echo json_encode(array("message" => "Usuário criado com sucesso."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Não foi possível criar o usuário."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Dados incompletos. Nome, email e senha são obrigatórios."));
        }
    }

    // PUT /api/users/:id - Atualizar usuário
    public function update() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $data = json_decode(file_get_contents("php://input"));

        $this->user->id = $data->id;
        $this->user->name = $data->name ?? null;
        $this->user->email = $data->email ?? null;
        $this->user->role = $data->role ?? null;
        $this->user->phone = $data->phone ?? null;
        $this->user->address = $data->address ?? null;
        $this->user->birth_date = $data->birth_date ?? null;
        $this->user->is_active = $data->is_active ?? null;
        $this->user->document_number = $data->document_number ?? null;
        $this->user->admin_permissions = $data->admin_permissions ?? null;
        $this->user->admin_department = $data->admin_department ?? null;

        if ($this->user->update()) {
            http_response_code(200);
            echo json_encode(array("message" => "Usuário atualizado com sucesso."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Não foi possível atualizar o usuário."));
        }
    }

    // DELETE /api/users/:id - Remover usuário
    public function delete() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $data = json_decode(file_get_contents("php://input"));

        $this->user->id = $data->id;

        if ($this->user->delete()) {
            http_response_code(200);
            echo json_encode(array("message" => "Usuário removido com sucesso."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Não foi possível remover o usuário."));
        }
    }

    // GET /api/users/search?q= - Buscar usuários
    public function search() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        $keywords = isset($_GET['q']) ? $_GET['q'] : '';

        $stmt = $this->user->search($keywords);
        $num = $stmt->rowCount();

        if ($num > 0) {
            $users_arr = array();
            $users_arr["data"] = array();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $user_item = array(
                    "id" => $id,
                    "name" => $name,
                    "email" => $email,
                    "role" => $role,
                    "phone" => $phone,
                    "is_active" => $is_active,
                    "created_at" => $created_at
                );
                array_push($users_arr["data"], $user_item);
            }

            http_response_code(200);
            echo json_encode($users_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Nenhum usuário encontrado com os critérios fornecidos."));
        }
    }
}
?>