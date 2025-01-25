<?php
require_once __DIR__ . '/config/Database.php';

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

// Parámetros básicos
$tabla = $_GET['tabla'] ?? '';
$pagina = $_GET['page'] ?? 1;
$limite = 1000;
$offset = ($pagina - 1) * $limite;
$orden = $_GET['order'] ?? ''; // Nuevo: parámetro de orden

// Validar tabla
if (empty($tabla)) {
    http_response_code(400);
    echo json_encode(["error" => "Debes especificar una tabla (ej: /api/tabla?tabla=clientes)"]);
    exit;
}

try {
    // Verificar si la tabla existe
    $result = $conn->query("SHOW TABLES LIKE '$tabla'");
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "La tabla '$tabla' no existe"]);
        exit;
    }

    // Obtener columnas de la tabla
    $columnas = $conn->query("SHOW COLUMNS FROM $tabla")->fetch_all(MYSQLI_ASSOC);
    $nombresColumnas = array_column($columnas, 'Field');

    // =============================================
    // ** Parte 1: Procesar filtros avanzados **
    // =============================================
    $filtros = [];
    foreach ($_GET as $campo => $valor) {
        // Ignorar parámetros reservados
        if (in_array($campo, ['tabla', 'page', 'order'])) continue;

        // Validar que el campo exista en la tabla
        if (!in_array($campo, $nombresColumnas)) {
            http_response_code(400);
            echo json_encode(["error" => "El campo '$campo' no existe en la tabla '$tabla'"]);
            exit;
        }

        // Detectar operadores y valores (ej: "precio=>100" -> operador ">", valor "100")
        $operadoresPermitidos = ['>', '<', '>=', '<=', '!=', '=', 'LIKE'];
        $operador = '='; // Por defecto

        // Buscar si el valor inicia con un operador
        foreach ($operadoresPermitidos as $op) {
            if (str_starts_with($valor, $op)) {
                $operador = $op;
                $valor = substr($valor, strlen($op));
                break;
            }
        }

        // Manejar búsquedas parciales con *
        if ($operador === 'LIKE' || strpos($valor, '*') !== false) {
            $operador = 'LIKE';
            $valor = str_replace('*', '%', $valor); // Convertir * a %
            $valor = "%$valor%"; // Búsqueda parcial por defecto
        }

        // Escapar el valor para seguridad
        $valor = $conn->real_escape_string($valor);

        // Construir condición SQL
        $filtros[] = "$campo $operador '$valor'";
    }

    $whereClause = empty($filtros) ? "" : "WHERE " . implode(" AND ", $filtros);

    // =============================================
    // ** Parte 2: Procesar ordenamiento **
    // =============================================
    $orderClause = '';
    if (!empty($orden)) {
        $direccion = 'ASC'; // Por defecto
        $campoOrden = $orden;

        // Si el campo empieza con "-", es orden descendente
        if (str_starts_with($orden, '-')) {
            $direccion = 'DESC';
            $campoOrden = substr($orden, 1);
        }

        // Validar que el campo de orden exista
        if (!in_array($campoOrden, $nombresColumnas)) {
            http_response_code(400);
            echo json_encode(["error" => "El campo '$campoOrden' no existe en la tabla"]);
            exit;
        }

        $orderClause = "ORDER BY $campoOrden $direccion";
    }

    // =============================================
    // ** Consulta final **
    // =============================================
    $query = "SELECT * FROM $tabla $whereClause $orderClause LIMIT $limite OFFSET $offset";
    $result = $conn->query($query);

    $datos = [];
    while ($fila = $result->fetch_assoc()) {
        $datos[] = $fila;
    }

    // Total de registros (con filtros)
    $queryTotal = "SELECT COUNT(*) as total FROM $tabla $whereClause";
    $totalRegistros = $conn->query($queryTotal)->fetch_assoc()['total'];
    $totalPaginas = ceil($totalRegistros / $limite);

    // Respuesta
    echo json_encode([
        "pagina_actual" => $pagina,
        "total_paginas" => $totalPaginas,
        "total_registros" => $totalRegistros,
        "filtros" => $filtros,
        "orden" => $orden,
        "datos" => $datos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>