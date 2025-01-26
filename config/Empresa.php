<?php
class Empresa {
    public static function obtenerConfig($codigoEmpresa) {
        // Conexión a la base de datos principal
        $dbMain = new Database('MAIN');
        $conn = $dbMain->connect();

        $query = "SELECT nombre_base_datos FROM empresas WHERE codigo_empresa = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $codigoEmpresa);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Empresa no encontrada");
        }

        return $result->fetch_assoc();
    }
}