<?php
require_once '../paths.php';
require_once AUTOLOAD;
require_once DATABASE_PATH;
require_once AUTH_PATH;

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

$datos = json_decode(file_get_contents("php://input"), true);

// Validar credenciales (ejemplo básico)
if ($datos['usuario'] === 'admin' && $datos['password'] === 'admin123') {
    $token = Auth::generarToken(1); // ID de usuario
    echo json_encode(["token" => $token]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales inválidas"]);
}