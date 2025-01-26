<?php

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
  http_response_code(401);
  echo json_encode(["error" => "Token no proporcionado"]);
  exit;
}

$token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
if (!Auth::validarToken($token)) {
  http_response_code(401);
  echo json_encode(["error" => "Token inválido o expirado"]);
  exit;
}

require_once '../paths.php';
require_once DATABASE_PATH;
require_once AUTH_PATH;
require_once EMPRESA_PATH;

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

// Leer el cuerpo de la solicitud (JSON)
$input = json_decode(file_get_contents("php://input"), true);

// Validar estructura básica
if (empty($input) || !isset($input['select']) || !isset($input['from'])) {
    http_response_code(400);
    echo json_encode(["error" => "Estructura inválida. Debes incluir 'select', 'from', y opcionalmente 'joins', 'where', 'order_by', 'page', 'per_page'"]);
    exit;
}

try {
    // =================================================================
    // Paso 1: Validar nombres de tablas y columnas (¡Seguridad crítica!)
    // =================================================================

    $schema = SchemaCache::getSchema();

    $validarTabla = function($nombreTabla) use ($schema) {
        if (!isset($schema[$nombreTabla])) {
            throw new Exception("La tabla '$nombreTabla' no existe");
        }
    };

    $validarColumna = function($tabla, $columna) use ($schema) {
        if (!in_array($columna, $schema[$tabla])) {
            throw new Exception("La columna '$columna' no existe en $tabla");
        }
    };

    // Validar tabla principal
    $tablaPrincipal = $input['from'];
    $validarTabla($tablaPrincipal);

    // =================================================================
    // Paso 2: Construir SELECT y JOINS
    // =================================================================
    // Selección de campos (ej: ["tabla.campo AS alias", ...])
    $select = array_map(function($campo) use ($conn) {
        return $conn->real_escape_string($campo);
    }, $input['select']);

    $selectClause = implode(", ", $select);

    // Construir JOINS
    $joinClause = "";
    if (!empty($input['joins'])) {
        foreach ($input['joins'] as $join) {
            $tipo = strtoupper($join['type'] ?? 'INNER');
            $tabla = $join['table'];
            $validarTabla($tabla);

            $condiciones = [];
            foreach ($join['on'] as $condicion) {
                // Validar columnas en ambas tablas
                $validarColumna($join['left_table'] ?? $tablaPrincipal, $condicion['left']);
                $validarColumna($tabla, $condicion['right']);

                $condiciones[] = "{$condicion['left']} {$condicion['operator']} {$condicion['right']}";
            }

            $joinClause .= " $tipo JOIN $tabla ON " . implode(" AND ", $condiciones);
        }
    }

    // =================================================================
    // Paso 3: Construir WHERE con parámetros seguros
    // =================================================================
    $whereClause = "";
    $params = [];
    if (!empty($input['where'])) {
        $condiciones = [];
        foreach ($input['where'] as $filtro) {
            $campo = $conn->real_escape_string($filtro['field']);
            $operador = $conn->real_escape_string($filtro['operator']);
            $valor = $filtro['value'];

            // Validar columna
            $validarColumna($tablaPrincipal, $campo);

            // Usar prepared statements para valores
            $condiciones[] = "$campo $operador ?";
            $params[] = $valor;
        }
        $whereClause = " WHERE " . implode(" AND ", $condiciones);
    }

    // =================================================================
    // Paso 4: Orden y paginación
    // =================================================================
    $orderClause = "";
    if (!empty($input['order_by'])) {
        $orderCampo = $conn->real_escape_string($input['order_by']);
        $orderClause = " ORDER BY $orderCampo";
    }

    $limitClause = "";
    if (!empty($input['page']) && !empty($input['per_page'])) {
        $offset = ($input['page'] - 1) * $input['per_page'];
        $limitClause = " LIMIT {$input['per_page']} OFFSET $offset";
    }

    // =================================================================
    // Paso 5: Ejecutar consulta
    // =================================================================
    $sql = "SELECT $selectClause FROM $tablaPrincipal $joinClause $whereClause $orderClause $limitClause";
    $stmt = $conn->prepare($sql);

    // Vincular parámetros (si existen)
    if (!empty($params)) {
        $tipos = str_repeat('s', count($params)); // Supone que todos son strings (ajusta según necesites)
        $stmt->bind_param($tipos, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $datos = [];
    while ($fila = $result->fetch_assoc()) {
        $datos[] = $fila;
    }

    echo json_encode(["datos" => $datos]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

$conn->close();
?>