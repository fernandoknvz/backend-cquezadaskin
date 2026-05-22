<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ClienteAuthController.php';
require_once __DIR__ . '/../utils/ClientAuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

$controller = new ClienteAuthController($pdo);
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents("php://input"), true) ?? [];

$route = trim((string)($_GET['route'] ?? ''), '/');
$segments = explode('/', $route);
$action = $segments[1] ?? '';
$id = isset($segments[2]) && ctype_digit((string)$segments[2]) ? (int)$segments[2] : null;
$subaction = $segments[3] ?? '';

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

if ($method === 'PATCH' && $action === 'me' && ($segments[2] ?? '') === 'password') {
    $authUser = ClientAuthMiddleware::verify($pdo);
    $controller->updatePassword($authUser, $body);
    exit;
}

if (($method === 'PUT' || $method === 'PATCH') && $action === 'me' && !isset($segments[2])) {
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

if ($method === 'PATCH' && $action === 'reservas' && $id && $subaction === 'cancelar') {
    $authUser = ClientAuthMiddleware::verify($pdo);
    $controller->cancelarReserva($authUser, $id, $body);
    exit;
}

if ($method === 'PATCH' && $action === 'reservas' && $id && $subaction === 'reagendar') {
    $authUser = ClientAuthMiddleware::verify($pdo);
    $controller->reagendarReserva($authUser, $id, $body);
    exit;
}

Response::error("Ruta de clientes no encontrada", 404);
