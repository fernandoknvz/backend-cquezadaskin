<?php
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JwtConfig.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController {
    private $model;
    private $jwt_secret;

    public function __construct($pdo) {
        $this->model = new UsuarioModel($pdo);
        $this->jwt_secret = JwtConfig::secret();
    }

    public function login($body) {
        $identifier = trim((string)($body['identifier'] ?? $body['email'] ?? $body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return Response::error("Usuario/email y contraseña requeridos", 400);
        }

        $user = $this->model->getByIdentifier($identifier);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return Response::error("Credenciales inválidas", 401);
        }

        $payload = [
            'sub' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'] ?? null,
            'rol' => $user['rol'],
            'iat' => time(),
            'exp' => time() + (60 * 60 * 8)
        ];

        $token = JWT::encode($payload, $this->jwt_secret, 'HS256');

        Response::json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'rol' => $user['rol']
            ]
        ]);
    }

    public function registerAdmin($body, $authUser) {
        if (($authUser['rol'] ?? '') !== 'superadmin') {
            return Response::error("Solo superadmin puede crear usuarios administradores", 403);
        }

        $username = trim((string)($body['username'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $rol = trim((string)($body['rol'] ?? 'admin'));

        if ($username === '' || $password === '') {
            return Response::error("Usuario y contraseña requeridos", 400);
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            return Response::error("Username inválido", 400);
        }

        if (strlen($password) < 8) {
            return Response::error("La contraseña debe tener al menos 8 caracteres", 400);
        }

        if (!in_array($rol, ['admin', 'superadmin'], true)) {
            return Response::error("Rol inválido", 400);
        }

        if ($this->model->getByUsername($username)) {
            return Response::error("El usuario ya existe", 409);
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Response::error("Email inválido", 400);
            }
            if ($this->model->getByEmail($email)) {
                return Response::error("El email ya está registrado", 409);
            }
        }

        $id = $this->model->create([
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'password' => $password,
            'rol' => $rol
        ]);

        Response::json([
            'message' => 'Usuario administrador creado',
            'user' => [
                'id' => (int)$id,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'rol' => $rol
            ]
        ], 201);
    }

    public function me($authUser) {
        $userId = $authUser['sub'] ?? $authUser['id'] ?? null;
        if (!$userId) {
            return Response::error("Usuario no autorizado", 401);
        }

        $user = $this->model->getById($userId);
        if (!$user) {
            return Response::error("Usuario no encontrado", 404);
        }

        Response::json([
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? null,
                'rol' => $user['rol']
            ]
        ]);
    }

    public function logout() {
        Response::json(['success' => true, 'message' => 'Sesión cerrada correctamente']);
    }

    public function verifyToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->jwt_secret, 'HS256'));
            return (array)$decoded;
        } catch (Exception $e) {
            return null;
        }
    }
}
