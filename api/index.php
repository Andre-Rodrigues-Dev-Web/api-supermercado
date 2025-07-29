<?php 
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Configurações para upload de arquivos
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '120M');
ini_set('max_file_uploads', '200');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/SupplierController.php';

// Obter o path da requisição
$request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path_segments = explode('/', $request_path);

// Remover segmentos vazios
$path_segments = array_values(array_filter($path_segments, function($segment) {
    return $segment !== '';
}));

// Encontrar a posição do 'api' no caminho
$api_position = array_search('api', $path_segments);

if ($api_position !== false) {
    // Pegar os segmentos após 'api'
    $path_segments = array_slice($path_segments, $api_position + 1);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Se não houver segmentos, usar 'home' como padrão
    $resource = $path_segments[0] ?? 'home';

    switch (strtolower($resource)) {
        case 'login':
            $controller = new AuthController();
            if ($method === 'POST') {
                $controller->login();
            } else {
                http_response_code(405);
                echo json_encode(["message" => "Método não permitido"]);
            }
            break;

        case 'users':
            $controller = new UserController();
            switch ($method) {
                case 'GET':
                    if (isset($path_segments[1]) && is_numeric($path_segments[1])) { // Linha com erro
                        $controller->read_single($path_segments[1]);
                    } else {
                        $controller->read();
                    }
                    break;
                case 'POST':
                    $controller->create();
                    break;
                case 'PUT':
                    $controller->update();
                    break;
                case 'DELETE':
                    $controller->delete();
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(["message" => "Método não permitido"]);
            }
            break;

        case 'products':
            $controller = new ProductController();
            switch ($method) {
                case 'GET':
                    if (isset($path_segments[1]) && is_numeric($path_segments[1])) {
                        $controller->read_single($path_segments[1]);
                    } else {
                        $controller->read();
                    }
                    break;
                case 'POST':
                    $controller->create();
                    break;
                case 'PUT':
                    if (isset($path_segments[1]) && is_numeric($path_segments[1])) {
                        $controller->update($path_segments[1]);
                    } else {
                        http_response_code(400);
                        echo json_encode(["message" => "ID do produto não fornecido"]);
                    }
                    break;
                case 'DELETE':
                    if (isset($path_segments[1]) && is_numeric($path_segments[1])) {
                        $controller->delete($path_segments[1]);
                    } else {
                        http_response_code(400);
                        echo json_encode(["message" => "ID do produto não fornecido"]);
                    }
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(["message" => "Método não permitido"]);
            }
            break;

        case 'suppliers':
            $controller = new SupplierController();
            switch ($method) {
                case 'GET':
                    $controller->read();
                    break;
                case 'POST':
                    $controller->create();
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(["message" => "Método não permitido"]);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                "message" => "Endpoint não encontrado",
                "request_path" => $request_path,
                "path_segments" => $path_segments
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Erro no servidor",
        "error" => $e->getMessage(),
        "trace" => $e->getTrace()
    ]);
}