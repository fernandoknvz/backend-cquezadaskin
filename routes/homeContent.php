<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/HomeContentController.php';

$controller = new HomeContentController($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$params = $_GET;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'multipart/form-data') !== false) {
    $body = $_POST;
    if ($method === 'POST' && isset($body['_method'])) {
        $override = strtoupper((string)$body['_method']);
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            $method = $override;
        }
        unset($body['_method']);
    }
} else {
    $body = json_decode(file_get_contents("php://input"), true) ?? [];
}

$controller->handleRequest($method, $params, $body);
