<?php
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../models/DisponibilidadModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JwtConfig.php';
require_once __DIR__ . '/../utils/RutValidator.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ClienteAuthController {
    private $model;
    private $citaModel;
    private $disponibilidadModel;
    private $jwt_secret;

    public function __construct($pdo) {
        $this->model = new ClienteModel($pdo);
        $this->citaModel = new CitaModel($pdo);
        $this->disponibilidadModel = new DisponibilidadModel($pdo);
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
                    'acepta_promociones' => filter_var($body['preferencias_promociones'] ?? $body['acepta_promociones'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
            'success' => true,
            'data' => $this->model->safeArray($cliente),
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

        if (array_key_exists('correo', $body) || array_key_exists('email', $body)) {
            $correo = strtolower(trim((string)($body['correo'] ?? $body['email'] ?? '')));
            if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return Response::error("Correo invalido", 400);
            }
            $existing = $this->model->getByCorreo($correo);
            if ($existing && (int)$existing['id'] !== $id) {
                return Response::error("Ya existe una cuenta registrada con este correo.", 409);
            }
            $data['correo'] = $correo;
        }

        if (array_key_exists('preferencias_promociones', $body) || array_key_exists('acepta_promociones', $body)) {
            $data['acepta_promociones'] = filter_var(
                $body['preferencias_promociones'] ?? $body['acepta_promociones'],
                FILTER_VALIDATE_BOOLEAN
            ) ? 1 : 0;
        }

        if (empty($data)) {
            return Response::error("No hay campos validos para actualizar", 400);
        }

        try {
            $this->model->updateProfile($id, $data);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return Response::error("Correo o RUT ya registrado por otro cliente", 409);
            }
            throw $e;
        }
        $updated = $this->model->getById($id);

        Response::json([
            'success' => true,
            'message' => 'Perfil actualizado',
            'data' => $this->model->safeArray($updated),
            'cliente' => $this->model->safeArray($updated),
        ]);
    }

    public function updatePassword($authUser, $body) {
        $id = $this->resolveClienteId($authUser);
        if (!$id) {
            return Response::error("Cliente no autorizado", 401);
        }

        $cliente = $this->model->getById($id);
        if (!$cliente) {
            return Response::error("Cliente no encontrado", 404);
        }

        $actual = (string)($body['password_actual'] ?? $body['current_password'] ?? '');
        $nueva = (string)($body['password_nueva'] ?? $body['new_password'] ?? '');

        if ($actual === '' || $nueva === '') {
            return Response::error("password_actual y password_nueva son requeridos", 400);
        }

        if (empty($cliente['password_hash']) || !password_verify($actual, $cliente['password_hash'])) {
            return Response::error("Password actual incorrecta", 401);
        }

        if (strlen($nueva) < 8) {
            return Response::error("La nueva password debe tener al menos 8 caracteres", 400);
        }

        $this->model->updatePasswordHash($id, password_hash($nueva, PASSWORD_BCRYPT));

        Response::json([
            'success' => true,
            'message' => 'Password actualizada',
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

        $reservas = $this->citaModel->getByClienteId($id);
        $split = $this->splitReservas($reservas);

        Response::json([
            'success' => true,
            'data' => $split,
            'reservas' => $reservas,
        ]);
    }

    public function cancelarReserva($authUser, int $reservaId, array $body) {
        $clienteId = $this->resolveClienteId($authUser);
        if (!$clienteId) {
            return Response::error("Cliente no autorizado", 401);
        }

        $reserva = $this->citaModel->getClienteReservaById($reservaId, $clienteId);
        if ($reserva === null) {
            return Response::error("Reserva no encontrada", 404);
        }
        if ($reserva === false) {
            return Response::error("No puedes acceder a esta reserva", 403);
        }

        if (in_array($reserva['estado'], ['cancelada', 'completada'], true)) {
            return Response::error("Esta reserva no se puede cancelar", 400);
        }

        if ($this->isTooSoon($reserva['fecha'], $reserva['hora'])) {
            return Response::error("No puedes cancelar una reserva con menos de 2 horas de anticipación", 400);
        }

        $motivo = $this->optionalText($body['motivo'] ?? null);
        $this->citaModel->cancelarClienteReserva($reservaId, $clienteId, $motivo);

        Response::json([
            'success' => true,
            'message' => 'Reserva cancelada',
            'data' => $this->citaModel->getClienteReservaById($reservaId, $clienteId),
        ]);
    }

    public function reagendarReserva($authUser, int $reservaId, array $body) {
        $clienteId = $this->resolveClienteId($authUser);
        if (!$clienteId) {
            return Response::error("Cliente no autorizado", 401);
        }

        $reserva = $this->citaModel->getClienteReservaById($reservaId, $clienteId);
        if ($reserva === null) {
            return Response::error("Reserva no encontrada", 404);
        }
        if ($reserva === false) {
            return Response::error("No puedes acceder a esta reserva", 403);
        }

        if (in_array($reserva['estado'], ['cancelada', 'completada'], true)) {
            return Response::error("Esta reserva no se puede reagendar", 400);
        }

        $fecha = $this->normalizeDate($body['fecha'] ?? null);
        $hora = $this->normalizeTime($body['hora'] ?? null);
        $motivo = $this->optionalText($body['motivo'] ?? null);

        if (!$fecha || !$hora) {
            return Response::error("fecha y hora válidas son requeridas", 400);
        }

        if ($this->isTooSoon($fecha, $hora)) {
            return Response::error("Debes escoger un horario con al menos 2 horas de anticipación", 400);
        }

        if ($this->citaModel->hasActiveConflict($fecha, $hora, $reservaId)) {
            return Response::error("Ya existe una reserva activa en ese horario", 409);
        }

        if (!$this->disponibilidadModel->isSlotAvailable($fecha, $hora, $reservaId)) {
            return Response::error("Horario no disponible", 409);
        }

        $this->citaModel->reagendarClienteReserva($reservaId, $clienteId, $fecha, $hora, $motivo);

        Response::json([
            'success' => true,
            'message' => 'Reserva reagendada',
            'data' => $this->citaModel->getClienteReservaById($reservaId, $clienteId),
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

    private function resolveClienteId($authUser): int {
        return (int)($authUser['cliente_id'] ?? ($authUser['sub'] ?? 0));
    }

    private function splitReservas(array $reservas): array {
        $now = time();
        $proximas = [];
        $historial = [];

        foreach ($reservas as $reserva) {
            $ts = strtotime(($reserva['fecha'] ?? '') . ' ' . ($reserva['hora'] ?? '00:00'));
            if ($ts !== false && $ts >= $now && !in_array($reserva['estado'], ['cancelada', 'completada'], true)) {
                $proximas[] = $reserva;
            } else {
                $historial[] = $reserva;
            }
        }

        return ['proximas' => $proximas, 'historial' => $historial];
    }

    private function normalizeDate($value): ?string {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }

    private function normalizeTime($value): ?string {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        $time = DateTime::createFromFormat('H:i', $value)
            ?: DateTime::createFromFormat('H:i:s', $value);
        return $time ? $time->format('H:i:s') : null;
    }

    private function optionalText($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private function isTooSoon(string $fecha, string $hora): bool {
        $ts = strtotime($fecha . ' ' . $hora);
        return $ts === false || $ts < (time() + (2 * 60 * 60));
    }

}
