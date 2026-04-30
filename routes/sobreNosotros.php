<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/SobreNosotrosController.php';

$controller = new SobreNosotrosController($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$params = $_GET;
$body = json_decode(file_get_contents("php://input"), true) ?? [];

$controller->handleRequest($method, $params, $body);
