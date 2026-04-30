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

    // 🔐 LOGIN
    public function login($body) {
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');
        $email = trim($body['email'] ?? '');

        if (!$username || !$password)
            return Response::error("Usuario y contraseña requeridos", 400);

        $user = $this->model->getByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash']))
            return Response::error("Credenciales inválidas", 401);

        $payload = [
            'sub' => $user['id'],
            'username' => $user['username'],
            'rol' => $user['rol'],
            'iat' => time(),
            'exp' => time() + (60 * 60 * 8) // 8 horas
        ];

        $token = JWT::encode($payload, $this->jwt_secret, 'HS256');

        Response::json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'rol' => $user['rol'],
                'email' => $user['email'] ?? null
            ]
        ]);
    }

    // 🧩 CREAR NUEVO ADMIN (solo otro admin)
    public function registerAdmin($body, $authUser) {
        if ($authUser['rol'] !== 'admin')
            return Response::error("Solo los administradores pueden crear usuarios", 403);

        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        if (!$username || !$password)
            return Response::error("Usuario y contraseña requeridos", 400);

        if ($this->model->getByUsername($username))
            return Response::error("El usuario ya existe", 409);

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Response::error("Email invÃ¡lido", 400);
            }
            if ($this->model->getByEmail($email)) {
                return Response::error("El email ya estÃ¡ registrado", 409);
            }
        }

        $id = $this->model->create([
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'password' => $password,
            'rol' => 'admin'
        ]);

        Response::json(["message" => "Usuario administrador creado", "id" => $id]);
    }

    // 👤 Obtener información del usuario logueado
    public function me($authUser) {
        Response::json(["user" => $authUser]);
    }

    // 🚪 Logout (solo informativo, el cliente elimina el token)
    public function logout() {
        Response::json(["success" => true, "message" => "Sesión cerrada correctamente"]);
    }

    // 🧠 Validar y decodificar token
    public function verifyToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->jwt_secret, 'HS256'));
            return (array)$decoded;
        } catch (Exception $e) {
            return null;
        }
    }
}
