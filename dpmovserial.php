<?php
require_once __DIR__ . '/config/Database.php';

header("Content-Type: application/json");

$db = new Database();
$conn = $db->connect();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(["mensaje" => "¡Hola! Soy el endpoint dpmovserial"]);
}