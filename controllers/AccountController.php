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

    private function publicUser($user) {
        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'] ?? null,
            'rol' => $user['rol']
        ];
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

        Response::json(['user' => $this->publicUser($user)]);
    }

    public function update($authUser, $body) {
        $userId = $this->resolveUserId($authUser);
        if (!$userId) {
            return Response::error("Usuario no autorizado", 401);
        }

        $currentPassword = trim((string)($body['current_password'] ?? $body['currentPassword'] ?? ''));
        if ($currentPassword === '') {
            return Response::error("Contraseña actual requerida", 400);
        }

        $user = $this->model->getById($userId);
        if (!$user) {
            return Response::error("Usuario no encontrado", 404);
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return Response::error("Contraseña actual incorrecta", 401);
        }

        $hasChanges = false;

        if (array_key_exists('username', $body)) {
            $username = trim((string)$body['username']);
            if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
                return Response::error("Username inválido", 400);
            }

            if ($username !== $user['username']) {
                $existing = $this->model->getByUsername($username);
                if ($existing && (int)$existing['id'] !== (int)$userId) {
                    return Response::error("El usuario ya existe", 409);
                }
                $this->model->updateUsername($userId, $username);
                $hasChanges = true;
            }
        }

        if (array_key_exists('email', $body)) {
            if (!$this->model->hasEmailColumn()) {
                return Response::error("La columna email no existe. Aplica la migración de usuarios_admin antes de actualizar email.", 500);
            }

            $email = trim((string)$body['email']);
            $email = $email === '' ? null : $email;

            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Response::error("Email inválido", 400);
            }

            if ($email !== ($user['email'] ?? null)) {
                $existing = $email !== null ? $this->model->getByEmail($email) : false;
                if ($existing && (int)$existing['id'] !== (int)$userId) {
                    return Response::error("El email ya está registrado", 409);
                }
                $this->model->updateEmail($userId, $email);
                $hasChanges = true;
            }
        }

        $newPassword = trim((string)($body['new_password'] ?? $body['newPassword'] ?? ''));
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return Response::error("La nueva contraseña debe tener al menos 8 caracteres", 400);
            }
            $this->model->updatePassword($userId, $newPassword);
            $hasChanges = true;
        }

        if (!$hasChanges) {
            return Response::error("No hay cambios para actualizar", 400);
        }

        $updated = $this->model->getById($userId);
        Response::json([
            'message' => 'Cuenta actualizada',
            'user' => $this->publicUser($updated)
        ]);
    }
}
