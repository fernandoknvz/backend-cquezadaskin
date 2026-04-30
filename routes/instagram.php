<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/InstagramController.php';

$controller = new InstagramController($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$params = $_GET;
$body = json_decode(file_get_contents("php://input"), true) ?? [];

// ✅ Contexto PUBLIC (GET solo activos)
$controller->handleRequest($method, $params, $body, false);