<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$authUser = AuthMiddleware::verify($pdo);
$controller = new AuthController($pdo);
$body = json_decode(file_get_contents("php://input"), true) ?? [];
$controller->registerAdmin($body, $authUser);
