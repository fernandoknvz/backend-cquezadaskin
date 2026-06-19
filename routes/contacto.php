<?php
require_once __DIR__ . '/../controllers/ContactoController.php';
require_once __DIR__ . '/../utils/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method !== 'POST') {
    Response::json([
        'success' => false,
        'message' => 'Metodo no permitido',
    ], 405);
    exit;
}

$controller = new ContactoController();
$controller->create($body);
