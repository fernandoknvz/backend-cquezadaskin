<?php
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../utils/Response.php';

class AccountController {
    private $model;

    public function __construct($pdo) {
        $this->model = new UsuarioModel($pdo);
    }

    private function resolveUserId($authUser) {
        return $authUser['sub'] ?? $authUser['id'] ?? null;
    }

    public function show($authUser) {
        $userId = $this->resolveUserId($authUser);
        if (!$userId) {
            return Response::error("Usuario no autorizado", 401);
        }

        $user = $this->model->getById($userId);
        if (!$user) {
            return Response::error("Usuario no encontrado", 404);
        }

        Response::json([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'rol' => $user['rol'],
                'email' => $user['email'] ?? null
            ]
        ]);
    }

    public function update($authUser, $body) {
        $userId = $this->resolveUserId($authUser);
        if (!$userId) {
            return Response::error("Usuario no autorizado", 401);
        }

        $currentPassword = trim((string)($body['current_password'] ?? ''));
        if ($currentPassword === '') {
            return Response::error("ContraseÃ±a actual requerida", 400);
        }

        $user = $this->model->getById($userId);
        if (!$user) {
            return Response::error("Usuario no encontrado", 404);
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return Response::error("ContraseÃ±a actual incorrecta", 401);
        }

        $emailProvided = array_key_exists('email', $body);
        $email = $emailProvided ? trim((string)$body['email']) : null;
        $newPassword = trim((string)($body['new_password'] ?? ''));
        $hasChanges = false;

        if ($emailProvided) {
            if ($email === '') {
                return Response::error("Email invÃ¡lido", 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Response::error("Email invÃ¡lido", 400);
            }
            if ($email !== $user['email']) {
                $existing = $this->model->getByEmail($email);
                if ($existing && (int)$existing['id'] !== (int)$userId) {
                    return Response::error("El email ya estÃ¡ registrado", 409);
                }
                $this->model->updateEmail($userId, $email);
                $hasChanges = true;
            }
        }

        if ($newPassword !== '') {
            $this->model->updatePassword($userId, $newPassword);
            $hasChanges = true;
        }

        if (!$hasChanges) {
            return Response::error("No hay cambios para actualizar", 400);
        }

        $updated = $this->model->getById($userId);
        Response::json([
            'message' => 'Cuenta actualizada',
            'user' => [
                'id' => $updated['id'],
                'username' => $updated['username'],
                'rol' => $updated['rol'],
                'email' => $updated['email'] ?? null
            ]
        ]);
    }
}
