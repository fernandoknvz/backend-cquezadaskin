<?php
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JwtConfig.php';
require_once __DIR__ . '/../utils/RutValidator.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ClienteAuthController {
    private $model;
    private $citaModel;
    private $jwt_secret;

    public function __construct($pdo) {
        $this->model = new ClienteModel($pdo);
        $this->citaModel = new CitaModel($pdo);
        $this->jwt_secret = JwtConfig::secret();
    }

    public function register($body) {
        $nombre = trim((string)($body['nombre'] ?? ''));
        $rut = RutValidator::normalize($body['rut'] ?? '');
        $correo = strtolower(trim((string)($body['correo'] ?? ($body['email'] ?? ''))));
        $telefono = trim((string)($body['telefono'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $aceptaPolitica = filter_var($body['acepta_politica'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($nombre === '' || empty($body['rut']) || $correo === '' || $telefono === '' || $password === '') {
            return Response::error("Nombre, RUT, correo, telefono y password son requeridos", 400);
        }

        if ($rut === null) {
            return Response::error("Ingresa un RUT válido.", 400);
        }

        if (!$aceptaPolitica) {
            return Response::error("Debes aceptar la politica de privacidad para registrarte", 400);
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return Response::error("Correo invalido", 400);
        }

        if (strlen($password) < 8) {
            return Response::error("La contrasena debe tener al menos 8 caracteres", 400);
        }

        $existingByRut = $this->model->getByRut($rut);
        $existing = $this->model->getByCorreo($correo);

        if ($existingByRut && (!$existing || (int)$existingByRut['id'] !== (int)$existing['id'] || !empty($existingByRut['password_hash']))) {
            return Response::error("Ya existe una cuenta registrada con este RUT.", 409);
        }

        if ($existing && !empty($existing['password_hash'])) {
            return Response::error("Ya existe una cuenta registrada con este correo.", 409);
        }

        try {
            if ($existing) {
                $this->model->updateProfile($existing['id'], [
                    'nombre' => $nombre,
                    'rut' => $rut,
                    'telefono' => $telefono,
                ]);
                $this->model->updatePrivacyAcceptance((int)$existing['id'], true);
                $this->model->updatePasswordHash((int)$existing['id'], password_hash($password, PASSWORD_BCRYPT));
                $cliente = $this->model->getById($existing['id']);
            } else {
                $id = $this->model->create([
                    'nombre' => $nombre,
                    'rut' => $rut,
                    'correo' => $correo,
                    'telefono' => $telefono,
                    'password' => $password,
                    'acepta_politica' => true,
                    'fecha_aceptacion' => date('Y-m-d H:i:s'),
                ]);
                $cliente = $this->model->getById($id);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $message = $e->getMessage();
                if (stripos($message, 'uniq_clientes_rut') !== false || stripos($message, 'rut') !== false) {
                    return Response::error("Ya existe una cuenta registrada con este RUT.", 409);
                }
                return Response::error("Ya existe una cuenta registrada con este correo.", 409);
            }
            throw $e;
        }

        $token = $this->makeToken($cliente);

        Response::json([
            'success' => true,
            'message' => 'Cliente registrado',
            'token' => $token,
            'cliente' => $this->model->safeArray($cliente),
        ], 201);
    }

    public function login($body) {
        $correo = strtolower(trim((string)($body['correo'] ?? ($body['email'] ?? ''))));
        $password = (string)($body['password'] ?? '');

        if ($correo === '' || $password === '') {
            return Response::error("Correo y password son requeridos", 400);
        }

        $cliente = $this->model->getByCorreo($correo);
        if (!$cliente || empty($cliente['password_hash']) || !password_verify($password, $cliente['password_hash'])) {
            return Response::error("Credenciales invalidas", 401);
        }

        Response::json([
            'success' => true,
            'token' => $this->makeToken($cliente),
            'cliente' => $this->model->safeArray($cliente),
        ]);
    }

    public function me($authUser) {
        $id = (int)($authUser['cliente_id'] ?? ($authUser['sub'] ?? 0));
        if (!$id) {
            return Response::error("Cliente no autorizado", 401);
        }

        $cliente = $this->model->getById($id);
        if (!$cliente) {
            return Response::error("Cliente no encontrado", 404);
        }

        Response::json([
            'cliente' => $this->model->safeArray($cliente),
        ]);
    }

    public function updateMe($authUser, $body) {
        $id = (int)($authUser['cliente_id'] ?? ($authUser['sub'] ?? 0));
        if (!$id) {
            return Response::error("Cliente no autorizado", 401);
        }

        $cliente = $this->model->getById($id);
        if (!$cliente) {
            return Response::error("Cliente no encontrado", 404);
        }

        $data = [];
        if (array_key_exists('nombre', $body)) {
            $nombre = trim((string)$body['nombre']);
            if ($nombre === '') {
                return Response::error("Nombre invalido", 400);
            }
            $data['nombre'] = $nombre;
        }

        if (array_key_exists('telefono', $body)) {
            $telefono = trim((string)$body['telefono']);
            $data['telefono'] = $telefono !== '' ? $telefono : null;
        }

        if (empty($data)) {
            return Response::error("No hay campos validos para actualizar", 400);
        }

        $this->model->updateProfile($id, $data);
        $updated = $this->model->getById($id);

        Response::json([
            'message' => 'Perfil actualizado',
            'cliente' => $this->model->safeArray($updated),
        ]);
    }

    public function deleteMe($authUser) {
        $id = (int)($authUser['cliente_id'] ?? ($authUser['sub'] ?? 0));
        if (!$id) {
            return Response::error("Cliente no autorizado", 401);
        }

        $cliente = $this->model->getById($id);
        if (!$cliente) {
            return Response::error("Cliente no encontrado", 404);
        }

        $this->model->anonymize($id);

        Response::json([
            'success' => true,
            'message' => 'Datos personales del cliente anonimizados',
        ]);
    }

    public function reservas($authUser) {
        $id = (int)($authUser['cliente_id'] ?? ($authUser['sub'] ?? 0));
        if (!$id) {
            return Response::error("Cliente no autorizado", 401);
        }

        $cliente = $this->model->getById($id);
        if (!$cliente) {
            return Response::error("Cliente no encontrado", 404);
        }

        Response::json([
            'reservas' => $this->citaModel->getByClienteId($id),
        ]);
    }

    public function verifyToken($token) {
        try {
            return (array)JWT::decode($token, new Key($this->jwt_secret, 'HS256'));
        } catch (Exception $e) {
            return null;
        }
    }

    private function makeToken(array $cliente) {
        $payload = [
            'sub' => (string)$cliente['id'],
            'cliente_id' => (int)$cliente['id'],
            'correo' => $cliente['correo'],
            'tipo' => 'cliente',
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24 * 7),
        ];

        return JWT::encode($payload, $this->jwt_secret, 'HS256');
    }

}
