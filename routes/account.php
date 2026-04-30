<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../controllers/AccountController.php';

$authUser = AuthMiddleware::verify($pdo);
$controller = new AccountController($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method === 'GET') {
    $controller->show($authUser);
    exit;
}

if ($method === 'PUT' || $method === 'PATCH') {
    $controller->update($authUser, $body);
    exit;
}

Response::error("MÃ©todo no permitido", 405);
