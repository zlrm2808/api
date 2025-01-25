<?php

require_once "../paths.php";
require_once AUTOLOAD;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private static $secretKey = 'tu_clave_secreta_super_segura'; // Cambia esto
    private static $algorithm = 'HS256';

    public static function generarToken($usuarioId) {
        $payload = [
            'iss' => 'tu_api', // Emisor
            'iat' => time(), // Fecha de emisión
            'exp' => time() + 3600, // Expira en 1 hora
            'sub' => $usuarioId
        ];
        return JWT::encode($payload, self::$secretKey, self::$algorithm);
    }

    public static function validarToken($token) {
        try {
            return JWT::decode($token, new Key(self::$secretKey, self::$algorithm));
        } catch (Exception $e) {
            return false;
        }
    }
}