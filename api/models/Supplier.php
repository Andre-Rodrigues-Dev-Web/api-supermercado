<?php
class Supplier {
    private $conn;
    private $table = 'fornecedores';

    public $id;
    public $nome;
    public $cnpj;
    public $email;
    public $telefone;
    public $endereco;
    public $cidade;
    public $estado;
    public $cep;
    public $observacoes;
    public $data_cadastro;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY data_cadastro DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?>