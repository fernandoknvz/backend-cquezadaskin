<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ClienteAuthController.php';
require_once __DIR__ . '/../utils/ClientAuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

$controller = new ClienteAuthController($pdo);
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents("php://input"), true) ?? [];

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($requestUri, '/'));
$action = end($segments);

if ($method === 'POST' && $action === 'register') {
    $controller->register($body);
    exit;
}

if ($method === 'POST' && $action === 'login') {
    $controller->login($body);
    exit;
}

if ($method === 'GET' && $action === 'me') {
    $authUser = ClientAuthMiddleware::verify($pdo);
    $controller->me($authUser);
    exit;
}

if (($method === 'PUT' || $method === 'PATCH') && $action === 'me') {
    $authUser = ClientAuthMiddleware::verify($pdo);
    $controller->updateMe($authUser, $body);
    exit;
}

if ($method === 'DELETE' && $action === 'me') {
    $authUser = ClientAuthMiddleware::verify($pdo);
    $controller->deleteMe($authUser);
    exit;
}

if ($method === 'GET' && $action === 'reservas') {
    $authUser = ClientAuthMiddleware::verify($pdo);
    $controller->reservas($authUser);
    exit;
}

Response::error("Ruta de clientes no encontrada", 404);
