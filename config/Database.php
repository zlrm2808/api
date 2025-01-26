<?php
require_once "../paths.php";
require_once AUTOLOAD_PATH;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class Database {
    private $host;
    private $user;
    private $password;
    private $database;
    public $encriptKey;

    public function __construct($config = 'MAIN') {
        
        $prefix = ($config === 'MAIN') ? 'DB_MAIN_' : 'DB_EMP_';
        
        $this->host = $_ENV[$prefix . 'HOST'] ?? 'localhost';
        $this->user = $_ENV[$prefix . 'USER'] ?? 'root';
        $this->password = $_ENV[$prefix . 'PASSWORD'] ?? '';
        $this->database = $_ENV[$prefix . 'NAME'] ?? '';

        $this->encriptKey = $_ENV['ENCRYPTION_KEY'] ?? '';
        if (empty($this->encriptKey)) {
            throw new Exception("La clave de encriptación no está configurada en .env");
        }
    }

    public function connect() {
        $conn = new mysqli($this->host, $this->user, $this->password, $this->database);
        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }
        return $conn;
    }
}