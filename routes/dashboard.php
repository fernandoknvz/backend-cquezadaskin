<?php
/**
 * ===========================================================
 * RUTA: /api/dashboard
 * -----------------------------------------------------------
 * Subendpoints disponibles:
 *  - GET /api/dashboard/overview
 *  - GET /api/dashboard/citas-hoy
 *  - GET /api/dashboard/top-servicios
 * ===========================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

$controller = new DashboardController($pdo);
$method = $_SERVER['REQUEST_METHOD'];

/**
 * ✅ IMPORTANTE
 * No usar REQUEST_URI para detectar subrutas, porque en hosting/local
 * puede existir un prefijo tipo /backend-cquezadaskin/...
 *
 * El router central (api/index.php) ya resolvió el path en $_GET['route'].
 * Ej: dashboard/overview, dashboard/citas-hoy
 */
$route = $_GET['route'] ?? '';
$subpath = '';

// Extraer lo que viene después de "dashboard"
if (preg_match('/^dashboard(?:\/(.*))?$/', $route, $matches)) {
    $subpath = isset($matches[1]) ? trim($matches[1], '/') : '';
}

// Construye parámetros
$params = $_GET;
if ($subpath !== '') {
    $params['endpoint'] = $subpath;
}

// Ejecuta controlador
$controller->handleRequest($method, $params);
