<?php
require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/Response.php';

class AuthGuard {
    public static function onlyAdmins($pdo, $method = null) {
        $authUser = AuthMiddleware::verify($pdo);

        if (!$authUser || !in_array($authUser['rol'] ?? '', ['admin', 'superadmin'], true)) {
            Response::error("Acceso denegado: se requiere rol administrativo", 403);
            exit;
        }

        return $authUser;
    }

    public static function onlyAdminsForMethods($pdo, string $method, array $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE']) {
        if (!in_array(strtoupper($method), $protectedMethods, true)) {
            return null;
        }

        return self::onlyAdmins($pdo, $method);
    }
}
