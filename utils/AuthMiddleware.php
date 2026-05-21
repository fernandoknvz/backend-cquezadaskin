<?php
require_once __DIR__ . '/../controllers/AuthController.php';

class AuthMiddleware {
    public static function verify($pdo) {
        $headers = getallheaders();
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }
        $auth = $normalizedHeaders['authorization'] ?? '';

        if (!str_starts_with($auth, 'Bearer ')) {
            Response::error("Token no proporcionado", 401);
            exit;
        }

        $token = trim(str_replace('Bearer ', '', $auth));

        $authController = new AuthController($pdo);
        $decoded = $authController->verifyToken($token);

        if (!$decoded) {
            Response::error("Token inválido o expirado", 401);
            exit;
        }

        return $decoded;
    }
}
