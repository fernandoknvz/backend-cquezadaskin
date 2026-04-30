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
}