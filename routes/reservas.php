<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ReservaController.php';
require_once __DIR__ . '/../utils/ClientAuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method !== 'POST') {
    Response::error("Metodo no permitido", 405);
    exit;
}

$authUser = ClientAuthMiddleware::verify($pdo);
$controller = new ReservaController($pdo);
$controller->create($authUser, $body);
