<?php
require_once __DIR__ . '/../controllers/ClienteAuthController.php';
require_once __DIR__ . '/Response.php';

class ClientAuthMiddleware {
    public static function verify($pdo) {
        $auth = self::authorizationHeader();

        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            Response::error("Token de cliente no proporcionado", 401);
            exit;
        }

        $token = trim($matches[1]);
        $controller = new ClienteAuthController($pdo);
        $decoded = $controller->verifyToken($token);

        if (!$decoded || (($decoded['tipo'] ?? '') !== 'cliente')) {
            Response::error("Token de cliente invalido o expirado", 401);
            exit;
        }

        return $decoded;
    }

    private static function authorizationHeader(): string {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'authorization') {
                return trim((string)$value);
            }
        }

        $serverKeys = [
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
            'Authorization',
        ];

        foreach ($serverKeys as $key) {
            if (!empty($_SERVER[$key])) {
                return trim((string)$_SERVER[$key]);
            }
        }

        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            foreach ($apacheHeaders as $key => $value) {
                if (strtolower((string)$key) === 'authorization') {
                    return trim((string)$value);
                }
            }
        }

        return '';
    }
}
