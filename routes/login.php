<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$controller = new AuthController($pdo);
$body = json_decode(file_get_contents("php://input"), true) ?? [];
$controller->login($body);
