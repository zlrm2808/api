<?php
require_once '../paths.php';
require_once DATABASE_PATH;

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

$datos = json_decode(file_get_contents("php://input"), true);

// Validar datos básicos
if (empty($datos) || !isset($datos['tabla'])) {
    http_response_code(400);
    echo json_encode(["error" => "Debes enviar al menos el parámetro 'tabla'."]);
    exit;
}

$tabla = $datos['tabla'];
unset($datos['tabla']); // Eliminar 'tabla' del array de datos

try {
    // Verificar que la tabla existe
    $result = $conn->query("SHOW TABLES LIKE '$tabla'");
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "La tabla '$tabla' no existe"]);
        exit;
    }

    // Obtener columnas y tipos
    $columnas = $conn->query("SHOW COLUMNS FROM $tabla")->fetch_all(MYSQLI_ASSOC);
    $camposTabla = [];
    foreach ($columnas as $col) {
        $camposTabla[$col['Field']] = [
            'tipo' => $col['Type'],
            'extra' => $col['Extra']
        ];
    }

    // Rellenar campos faltantes
    $valores = [];
    foreach ($camposTabla as $campo => $info) {
        // Ignorar campos autoincrementales (si existen)
        if (strpos($info['extra'], 'auto_increment') !== false) {
            continue;
        }

        // Convertir el nombre del campo de la tabla a MAYÚSCULAS para comparar
        $campoTablaUpper = strtoupper($campo);

        // Buscar coincidencia en los datos del JSON (también en MAYÚSCULAS)
        $claveJson = null;
        foreach ($datos as $clave => $valor) {
            if (strtoupper($clave) === $campoTablaUpper) {
                $claveJson = $clave;
                break;
            }
        }

        if ($claveJson !== null) {
            $valor = $datos[$claveJson];
            $tipo = $info['tipo'];

            // Manejar números sin comillas
            if (strpos($tipo, 'int') !== false || strpos($tipo, 'decimal') !== false || strpos($tipo, 'float') !== false) {
                $valores[$campo] = is_numeric($valor) ? $valor : 0;
            } else {
                $valores[$campo] = "'" . $conn->real_escape_string($valor) . "'";
            }
        } else {
            // Asignar valor predeterminado según el tipo de dato
            $tipo = $info['tipo'];
            if (strpos($tipo, 'int') !== false || strpos($tipo, 'decimal') !== false || strpos($tipo, 'float') !== false) {
                $valores[$campo] = 0;
            } elseif (strpos($tipo, 'date') !== false) {
                $valores[$campo] = "'0000-00-00'";
            } elseif (strpos($tipo, 'time') !== false) {
                $valores[$campo] = "'00:00:00'";
            } else {
                $valores[$campo] = "''";
            }
        }
    }

    // Construir la consulta INSERT
    $campos = implode(", ", array_keys($valores));
    $valoresStr = implode(", ", $valores);
    $query = "INSERT INTO $tabla ($campos) VALUES ($valoresStr)";

    // Depuración: Mostrar la consulta SQL generada
    error_log("Consulta SQL: " . $query);

    if ($conn->query($query)) {
        $respuesta = [
            "mensaje: Registro creado exitosamente",
            "valores_enviados: $datos",
            "valores_predeterminados: $valores"
        ];
        http_response_code(201);
        echo json_encode($respuesta);
    } else {
        throw new Exception("Error al crear el registro: " . $conn->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

$conn->close();