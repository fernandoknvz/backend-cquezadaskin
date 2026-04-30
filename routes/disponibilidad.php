<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/DisponibilidadController.php';

$controller = new DisponibilidadController($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$params = $_GET;
$body = json_decode(file_get_contents("php://input"), true) ?? [];

$controller->handleRequest($method, $params, $body);
