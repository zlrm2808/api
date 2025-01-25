<?php
require_once __DIR__ . '/config/Database.php';

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

$tabla = $_GET['tabla'] ?? '';

// Validar que el nombre de la tabla no esté vacío
if (empty($tabla)) {
    http_response_code(400);
    echo json_encode(["error" => "Debes especificar una tabla (ej: /api/tabla?tabla=clientes)"]);
    exit;
}

// Paginación: número de página y registros por página $limite es el numero de registros por pagina
$pagina = $_GET['page'] ?? 1;
$limite = 1000;
$offset = ($pagina - 1) * $limite;

try {
    // Verificar si la tabla existe
    $result = $conn->query("SHOW TABLES LIKE '$tabla'");
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "La tabla '$tabla' no existe"]);
        exit;
    }

    $query = "SELECT * FROM $tabla LIMIT $limite OFFSET $offset";
    $result = $conn->query($query);

    $datos = [];
    while ($fila = $result->fetch_assoc()) {
        $datos[] = $fila;
    }

    $totalRegistros = $conn->query("SELECT COUNT(*) as total FROM $tabla")->fetch_assoc()['total'];
    $totalPaginas = ceil($totalRegistros / $limite);

    echo json_encode([
        "pagina_actual" => $pagina,
        "total_paginas" => $totalPaginas,
        "total_registros" => $totalRegistros,
        "datos" => $datos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>