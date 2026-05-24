<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/FaqController.php';
require_once __DIR__ . '/../utils/Response.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error("Método no permitido", 405);
    exit;
}

$controller = new FaqController($pdo);
$controller->publicIndex();
