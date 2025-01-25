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
$orden = $_GET['order'] ?? '';

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

    // Obtener columnas y sus tipos (ej: INT, VARCHAR, DATE)
    $columnas = $conn->query("SHOW COLUMNS FROM $tabla")->fetch_all(MYSQLI_ASSOC);
    $nombresColumnas = array_column($columnas, 'Field');
    $tiposColumnas = [];
    foreach ($columnas as $col) {
        $tiposColumnas[$col['Field']] = $col['Type'];
    }

    // =============================================
    // ** Parte 1: Procesar filtros (case-insensitive y AND/OR) **
    // =============================================
    $filtros = [];
    $params = $_GET;

    foreach ($params as $paramKey => $valor) {
        // Ignorar parámetros reservados
        if (in_array($paramKey, ['tabla', 'page', 'order'])) continue;

        // Convertir parámetro a minúsculas y buscar coincidencia con columnas reales
        $campoReal = null;
        foreach ($nombresColumnas as $col) {
            if (strtolower($paramKey) === strtolower($col)) {
                $campoReal = $col;
                break;
            }
        }

        if (!$campoReal) {
            http_response_code(400);
            echo json_encode(["error" => "El campo '$paramKey' no existe en la tabla '$tabla'"]);
            exit;
        }

        // Determinar operador (AND u OR)
        $operadorLogico = 'AND';
        if (strpos($paramKey, '__or') !== false) {
            $operadorLogico = 'OR';
        }

        // =============================================
        // ** Parte 2: Manejar rangos (ej: 100~500) **
        // =============================================
        if (strpos($valor, '::') !== false) {
            list($valor1, $valor2) = explode('::', $valor, 2);
            $valor1 = $conn->real_escape_string(trim($valor1));
            $valor2 = $conn->real_escape_string(trim($valor2));
        
            // Validar tipo de dato para rangos
            $tipo = $tiposColumnas[$campoReal];
            if (strpos($tipo, 'int') !== false || strpos($tipo, 'float') !== false) {
                $clausula = "$campoReal BETWEEN $valor1 AND $valor2";
            } else {
                $clausula = "$campoReal BETWEEN '$valor1' AND '$valor2'";
            }
        
            // Agregar como array con operador lógico
            $filtros[] = [
                'clausula' => $clausula,
                'operador' => $operadorLogico
            ];
            continue;
        }

        // =============================================
        // ** Parte 3: Manejar operadores y tipos de datos **
        // =============================================
        $operadoresPermitidos = ['>', '<', '>=', '<=', '!=', '=', 'LIKE'];
        $operador = '=';

        foreach ($operadoresPermitidos as $op) {
            if (str_starts_with($valor, $op)) {
                $operador = $op;
                $valor = substr($valor, strlen($op));
                break;
            }
        }

        // Búsquedas parciales con *
        if ($operador === 'LIKE' || strpos($valor, '*') !== false) {
            $operador = 'LIKE';
            $valor = str_replace('*', '%', $valor);
        }

        // Validar y castear tipo de dato
        $tipo = $tiposColumnas[$campoReal];
        $valor = $conn->real_escape_string($valor);

        if (strpos($tipo, 'int') !== false) {
            $valor = (int)$valor;
        } elseif (strpos($tipo, 'float') !== false || strpos($tipo, 'double') !== false) {
            $valor = (float)$valor;
        } else {
            $valor = "'$valor'"; // Entre comillas si es texto/date
        }

        $filtros[] = [
            'clausula' => "$campoReal $operador $valor",
            'operador' => $operadorLogico
        ];
    }

    // =============================================
    // ** Construir cláusula WHERE agrupando AND/OR **
    // =============================================
    $whereClause = '';
    $grupos = [];
    $currentGroup = [];

    foreach ($filtros as $filtro) {
        if ($filtro['operador'] === 'OR') {
            $currentGroup[] = $filtro['clausula'];
        } else {
            if (!empty($currentGroup)) {
                $grupos[] = '(' . implode(' OR ', $currentGroup) . ')';
                $currentGroup = [];
            }
            $grupos[] = $filtro['clausula'];
        }
    }

    if (!empty($currentGroup)) {
        $grupos[] = '(' . implode(' OR ', $currentGroup) . ')';
    }

    if (!empty($grupos)) {
        $whereClause = 'WHERE ' . implode(' AND ', $grupos);
    }

    // =============================================
    // ** Parte 4: Ordenamiento **
    // =============================================
    $orderClause = '';
    if (!empty($orden)) {
        $direccion = 'ASC';
        $campoOrden = $orden;

        if (str_starts_with($orden, '-')) {
            $direccion = 'DESC';
            $campoOrden = substr($orden, 1);
        }

        // Validar campo de orden (case-insensitive)
        $campoValido = false;
        foreach ($nombresColumnas as $col) {
            if (strtolower($campoOrden) === strtolower($col)) {
                $campoOrden = $col;
                $campoValido = true;
                break;
            }
        }

        if (!$campoValido) {
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

    $queryTotal = "SELECT COUNT(*) as total FROM $tabla $whereClause";
    $totalRegistros = $conn->query($queryTotal)->fetch_assoc()['total'];
    $totalPaginas = ceil($totalRegistros / $limite);

    // Respuesta
    echo json_encode([
        "pagina_actual" => $pagina,
        "total_paginas" => $totalPaginas,
        "total_registros" => $totalRegistros,
        "filtros" => $whereClause,
        "orden" => $orderClause,
        "datos" => $datos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
}

$conn->close();