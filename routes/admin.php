<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuthGuard.php';
require_once __DIR__ . '/../controllers/AdminController.php';

$method = $_SERVER['REQUEST_METHOD'];
$route = trim((string)($_GET['route'] ?? ''), '/');
$segments = explode('/', $route);
$params = $_GET;
$body = json_decode(file_get_contents("php://input"), true) ?? [];

AuthGuard::onlyAdmins($pdo, $method);

$controller = new AdminController($pdo);
$controller->handleRequest($method, $segments, $params, $body);
