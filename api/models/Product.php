<?php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/ProductGallery.php';
require_once __DIR__ . '/../config/database.php';

class ProductController {
    private $db;
    private $product;
    private $gallery;
    private $uploadDir = __DIR__ . '/../uploads/imgs/products/';
    private $galleryDir = 'gallery/';
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxFileSize = 5 * 1024 * 1024; // 5MB

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->product = new Product($this->db);
        $this->gallery = new ProductGallery($this->db);
        
        $this->createUploadDirectories();
    }


    private function createUploadDirectories() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
        if (!file_exists($this->uploadDir . $this->galleryDir)) {
            mkdir($this->uploadDir . $this->galleryDir, 0777, true);
        }
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

    private function validateImage($file) {
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new Exception("Tipo de arquivo não permitido. Use JPEG, PNG, GIF ou WEBP.");
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception("Arquivo muito grande. Tamanho máximo: 5MB.");
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload do arquivo: " . $file['error']);
        }
    }

    private function uploadImage($file, $isGallery = false) {
        $this->validateImage($file);
        
        $targetDir = $isGallery ? $this->uploadDir . $this->galleryDir : $this->uploadDir;
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('prod_') . '.' . $fileExt;
        $targetPath = $targetDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Falha ao mover o arquivo para o diretório de upload.");
        }

        return $fileName;
    }

    private function deleteImage($fileName, $isGallery = false) {
        $filePath = $isGallery 
            ? $this->uploadDir . $this->galleryDir . $fileName
            : $this->uploadDir . $fileName;

        if (file_exists($filePath)) {
            unlink($filePath);
            return true;
        }
        return false;
    }

    public function read() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        try {
            $stmt = $this->product->read();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($products as &$product) {
                $this->gallery->product_id = $product['id'];
                $stmt = $this->gallery->readByProduct();
                $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => $products
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Erro ao listar produtos",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function read_single($id) {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        try {
            $this->product->id = $id;
            $stmt = $this->product->read_single();
            
            if ($stmt->rowCount() > 0) {
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $this->gallery->product_id = $id;
                $stmt = $this->gallery->readByProduct();
                $product['gallery'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode([
                    "status" => "success",
                    "data" => $product
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    "status" => "error",
                    "message" => "Produto não encontrado"
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Erro ao buscar produto",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function create() {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        try {
            if (empty($_POST['product_data'])) {
                throw new Exception("Dados do produto não fornecidos.");
            }

            $data = json_decode($_POST['product_data'], true);
            
            if (empty($data['name']) || empty($data['price']) || empty($data['category_id'])) {
                throw new Exception("Nome, preço e categoria são obrigatórios.");
            }

            $mainImage = null;
            if (!empty($_FILES['main_image'])) {
                $mainImage = $this->uploadImage($_FILES['main_image']);
            }

            $this->product->category_id = $data['category_id'];
            $this->product->name = $data['name'];
            $this->product->description = $data['description'] ?? null;
            $this->product->price = $data['price'];
            $this->product->stock = $data['stock'] ?? 0;
            $this->product->image = $mainImage;
            $this->product->status = $data['status'] ?? 1;

            $product_id = $this->product->create();

            if (!$product_id) {
                if ($mainImage) {
                    $this->deleteImage($mainImage);
                }
                throw new Exception("Falha ao criar o produto no banco de dados.");
            }

            $uploadedGalleryImages = [];
            if (!empty($_FILES['gallery_images'])) {
                foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                    $galleryFile = [
                        'name' => $_FILES['gallery_images']['name'][$key],
                        'type' => $_FILES['gallery_images']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['gallery_images']['error'][$key],
                        'size' => $_FILES['gallery_images']['size'][$key]
                    ];

                    $imagePath = $this->uploadImage($galleryFile, true);
                    $uploadedGalleryImages[] = $imagePath;
                    
                    $this->gallery->product_id = $product_id;
                    $this->gallery->image_path = $imagePath;
                    $this->gallery->is_main = 0;
                    
                    if (!$this->gallery->create()) {
                        // Se falhar, remove as imagens já enviadas
                        foreach ($uploadedGalleryImages as $img) {
                            $this->deleteImage($img, true);
                        }
                        if ($mainImage) {
                            $this->deleteImage($mainImage);
                        }
                        throw new Exception("Falha ao salvar imagens da galeria.");
                    }
                }
            }

            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Produto criado com sucesso",
                "product_id" => $product_id,
                "main_image" => $mainImage,
                "gallery_images" => $uploadedGalleryImages
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function update($id) {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        try {
            $this->product->id = $id;
            $currentProduct = $this->product->read_single()->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentProduct) {
                throw new Exception("Produto não encontrado.");
            }

            $data = json_decode($_POST['product_data'] ?? '{}', true);

            $this->product->category_id = $data['category_id'] ?? $currentProduct['category_id'];
            $this->product->name = $data['name'] ?? $currentProduct['name'];
            $this->product->description = $data['description'] ?? $currentProduct['description'];
            $this->product->price = $data['price'] ?? $currentProduct['price'];
            $this->product->stock = $data['stock'] ?? $currentProduct['stock'];
            $this->product->status = $data['status'] ?? $currentProduct['status'];
            $this->product->image = $currentProduct['image']; 

            $newMainImage = null;
            if (!empty($_FILES['main_image'])) {
                $newMainImage = $this->uploadImage($_FILES['main_image']);
                $this->product->image = $newMainImage;
                
                if ($currentProduct['image']) {
                    $this->deleteImage($currentProduct['image']);
                }
            }

            if (!$this->product->update()) {
                if ($newMainImage) {
                    $this->deleteImage($newMainImage);
                }
                throw new Exception("Falha ao atualizar o produto.");
            }

            $newGalleryImages = [];
            if (!empty($_FILES['gallery_images'])) {
                foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                    $galleryFile = [
                        'name' => $_FILES['gallery_images']['name'][$key],
                        'type' => $_FILES['gallery_images']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['gallery_images']['error'][$key],
                        'size' => $_FILES['gallery_images']['size'][$key]
                    ];

                    $imagePath = $this->uploadImage($galleryFile, true);
                    $newGalleryImages[] = $imagePath;
                    
                    $this->gallery->product_id = $id;
                    $this->gallery->image_path = $imagePath;
                    $this->gallery->is_main = 0;
                    
                    if (!$this->gallery->create()) {
                        foreach ($newGalleryImages as $img) {
                            $this->deleteImage($img, true);
                        }
                        throw new Exception("Falha ao adicionar novas imagens à galeria.");
                    }
                }
            }

            if (!empty($data['images_to_remove'])) {
                foreach ($data['images_to_remove'] as $imageId) {
                    $this->gallery->id = $imageId;
                    $stmt = $this->gallery->read_single();
                    if ($image = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $this->deleteImage($image['image_path'], true);
                        $this->gallery->delete();
                    }
                }
            }

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Produto atualizado com sucesso",
                "new_main_image" => $newMainImage,
                "new_gallery_images" => $newGalleryImages
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function delete($id) {
        $admin = $this->verifyAdminToken();
        if (!$admin) return;

        try {
            $this->product->id = $id;
            $product = $this->product->read_single()->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Produto não encontrado.");
            }

            if ($product['image']) {
                $this->deleteImage($product['image']);
            }

            $this->gallery->product_id = $id;
            $stmt = $this->gallery->readByProduct();
            while ($image = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->deleteImage($image['image_path'], true);
            }
            $this->gallery->deleteByProduct();

            // Finalmente, remover o produto
            if ($this->product->delete()) {
                http_response_code(200);
                echo json_encode([
                    "status" => "success",
                    "message" => "Produto removido com sucesso"
                ]);
            } else {
                throw new Exception("Falha ao remover o produto do banco de dados.");
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }
}
?>