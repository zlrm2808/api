<?php
require_once '../paths.php';
require_once DATABASE_PATH;

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

$datos = json_decode(file_get_contents("php://input"), true);

if (empty($datos) || !isset($datos['tabla']) || !isset($datos['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Datos incompletos. Debes enviar 'tabla' e 'id'."]);
    exit;
}

$tabla = $datos['tabla'];
$id = $datos['id'];

try {
    // Validar tabla
    $result = $conn->query("SHOW TABLES LIKE '$tabla'");
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "La tabla '$tabla' no existe"]);
        exit;
    }

    $query = "DELETE FROM $tabla WHERE id = $id";

    if ($conn->query($query)) {
        echo json_encode(["mensaje" => "Registro eliminado exitosamente"]);
    } else {
        throw new Exception("Error al eliminar: " . $conn->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

$conn->close();