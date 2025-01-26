<?php
require_once '../paths.php';
require_once AUTOLOAD_PATH;
require_once DATABASE_PATH;
require_once AUTH_PATH;

header("Content-Type: application/json");

$db = new Database('MAIN');
$conn = $db->connect();

$datos = json_decode(file_get_contents("php://input"), true);

if (empty($datos['usuario']) || empty($datos['password'])) {
  http_response_code(400);
  echo json_encode(["error" => "Debes proporcionar usuario y contraseña"]);
  exit;
}

$usuario = $datos['usuario'];
$password = $datos['password'];

try {
  $encriptKey = $db->encriptKey;

  $query = "SELECT USR_LOGIN, USR_PASSWORD FROM confusers WHERE USR_LOGIN = AES_ENCRYPT(?,?)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param('ss', $usuario, $encriptKey);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Usuario o Contraseña incorrecto(a)"]);
    exit;
  }

  $row = $result->fetch_assoc();
  $claveEncriptada = $row['USR_PASSWORD'];

  $queryDecrypt = "SELECT AES_DECRYPT(?,?) AS clave_desencriptada";
  $stmtDecrypt = $conn->prepare($queryDecrypt);
  $stmtDecrypt->bind_param('ss', $claveEncriptada, $encriptKey);
  $stmtDecrypt->execute();
  $resultDecrypt = $stmtDecrypt->get_result();
  $rowDecrypt = $resultDecrypt->fetch_assoc();
  $claveDesencriptada = $rowDecrypt['clave_desencriptada'];

  if ($password === $claveDesencriptada) {
    
    $token = Auth::generarToken([
      'usuario_id' => 1
    ]);

    echo json_encode(["token" => $token]);
  } else {
    http_response_code(401);
    echo json_encode(["error" => "Usuario o Contraseña incorrecto(a)"]);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "Error en el servidor: " . $e->getMessage()]);
}

$conn->close();
