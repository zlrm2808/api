<?php
require_once __DIR__ . '/config/Database.php';

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

// Parámetros básicos
$tabla = $_GET['tabla'] ?? '';
$pagina = $_GET['page'] ?? 1;
$limite = 10000;
$offset = ($pagina - 1) * $limite;

// Validar tabla
if (empty($tabla)) {
    http_response_code(400);
    echo json_encode(["error" => "Debes especificar una tabla (ej: /api/tabla?tabla=clientes)"]);
    exit;
}

try {
    // Paso 1: Verificar si la tabla existe
    $result = $conn->query("SHOW TABLES LIKE '$tabla'");
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "La tabla '$tabla' no existe"]);
        exit;
    }

    // Paso 2: Obtener columnas de la tabla (para validar los filtros)
    $columnas = $conn->query("SHOW COLUMNS FROM $tabla")->fetch_all(MYSQLI_ASSOC);
    $nombresColumnas = array_column($columnas, 'Field');

    // Paso 3: Recoger filtros de la URL (ej: &nombre=Juan&edad=30)
    $filtros = [];
    foreach ($_GET as $key => $value) {
        if ($key !== 'tabla' && $key !== 'page' && in_array($key, $nombresColumnas)) {
            $filtros[$key] = $conn->real_escape_string($value); // Limpiar el valor
        }
    }

    // Paso 4: Construir la consulta SQL con filtros
    $where = [];
    foreach ($filtros as $campo => $valor) {
        $where[] = "$campo = '$valor'"; // Búsqueda exacta
    }
    $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

    // Paso 5: Obtener datos paginados con filtros
    $query = "SELECT * FROM $tabla $whereClause LIMIT $limite OFFSET $offset";
    $result = $conn->query($query);

    $datos = [];
    while ($fila = $result->fetch_assoc()) {
        $datos[] = $fila;
    }

    // Paso 6: Calcular total de registros (con filtros)
    $queryTotal = "SELECT COUNT(*) as total FROM $tabla $whereClause";
    $totalRegistros = $conn->query($queryTotal)->fetch_assoc()['total'];
    $totalPaginas = ceil($totalRegistros / $limite);

    // Respuesta
    echo json_encode([
        "pagina_actual" => $pagina,
        "total_paginas" => $totalPaginas,
        "total_registros" => $totalRegistros,
        "filtros_aplicados" => $filtros,
        "datos" => $datos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
}

$conn->close();