<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ServicioController.php';

// =============================
// ✅ DETECCIÓN AUTOMÁTICA DE ID EN RUTA
// =============================
// Ejemplo: /api/servicios/13 → $_GET['id'] = 13
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($requestUri, '/'));
$lastSegment = end($segments);

// Si el último segmento es un número, lo tratamos como ID
if (is_numeric($lastSegment)) {
    $_GET['id'] = (int) $lastSegment;
}

// =============================
// 📦 INSTANCIAR CONTROLADOR
// =============================
$controller = new ServicioController($pdo);

// =============================
// 🧩 OBTENER DATOS DE LA SOLICITUD
// =============================
$method = $_SERVER['REQUEST_METHOD'];
$params = $_GET;
$body = json_decode(file_get_contents("php://input"), true) ?? [];

// =============================
// 🚀 PROCESAR SOLICITUD
// =============================
$controller->handleRequest($method, $params, $body);
