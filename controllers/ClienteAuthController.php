<?php
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../models/DisponibilidadModel.php';
require_once __DIR__ . '/../models/TestimonioModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JwtConfig.php';
require_once __DIR__ . '/../utils/RutValidator.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ClienteAuthController {
    private $model;
    private $citaModel;
    private $disponibilidadModel;
    private $testimonioModel;
    private $jwt_secret;

    public function __construct($pdo) {
        $this->model = new ClienteModel($pdo);
        $this->citaModel = new CitaModel($pdo);
        $this->disponibilidadModel = new DisponibilidadModel($pdo);
        $this->testimonioModel = new TestimonioModel($pdo);
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

        $timingRestriction = $this->reservationTimingRestriction($reserva['fecha'], $reserva['hora']);
        if ($timingRestriction === 'past') {
            return Response::error("No puedes cancelar una reserva pasada", 400);
        }
        if ($timingRestriction === 'too_soon') {
            return Response::error("No puedes cancelar una reserva con menos de 1 hora de anticipacion", 400);
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
        $motivoCliente = $this->optionalText($body['motivo'] ?? null);
        $motivo = 'Reagendada por cliente. Requiere confirmacion admin.';
        if ($motivoCliente !== null) {
            $motivo .= ' Motivo cliente: ' . $motivoCliente;
        }

        if (!$fecha || !$hora) {
            return Response::error("fecha y hora válidas son requeridas", 400);
        }

        $timingRestriction = $this->reservationTimingRestriction($fecha, $hora);
        if ($timingRestriction === 'past') {
            return Response::error("No se puede reagendar en horarios pasados", 400);
        }
        if ($timingRestriction === 'too_soon') {
            return Response::error("Para reservas de hoy debes seleccionar un horario con al menos 1 hora de anticipacion", 400);
        }

        $duracionMin = (int)($reserva['duracion_min'] ?? 30);
        if ($this->citaModel->hasActiveConflict($fecha, $hora, $reservaId, $duracionMin)) {
            return Response::error("Ya existe una reserva activa en ese horario", 409);
        }

        foreach ($this->buildSlots($fecha, $hora, $duracionMin) as $slotHora) {
            if (!$this->disponibilidadModel->isSlotAvailable($fecha, $slotHora, $reservaId)) {
                return Response::error("Horario no disponible", 409);
            }
        }

        $reservaAnterior = $this->citaModel->getById($reservaId) ?: $reserva;
        $this->citaModel->reagendarClienteReserva($reservaId, $clienteId, $fecha, $hora, $motivo);
        $reservaActualizada = $this->citaModel->getById($reservaId);

        if ($reservaActualizada) {
            $this->notifyClientRescheduledBooking($reservaAnterior, $reservaActualizada);
        }

        Response::json([
            'success' => true,
            'message' => 'Solicitud de reagendamiento recibida y pendiente de confirmacion',
            'data' => $this->citaModel->getClienteReservaById($reservaId, $clienteId),
        ]);
    }

    public function crearValoracion($authUser, array $body) {
        $clienteId = $this->resolveClienteId($authUser);
        if (!$clienteId) {
            return Response::error("Cliente no autorizado", 401);
        }

        $cliente = $this->model->getById($clienteId);
        if (!$cliente) {
            return Response::error("Cliente no encontrado", 404);
        }

        $comentario = trim((string)($body['comentario'] ?? ''));
        $puntuacion = (int)($body['puntuacion'] ?? 0);
        $nombreMostrado = trim((string)($body['nombre_mostrado'] ?? $cliente['nombre'] ?? 'Cliente'));
        $citaId = isset($body['cita_id']) && $body['cita_id'] !== '' ? (int)$body['cita_id'] : null;

        if ($comentario === '') {
            return Response::error("Comentario requerido", 400);
        }
        if ($puntuacion < 1 || $puntuacion > 5) {
            return Response::error("Puntuación debe estar entre 1 y 5", 400);
        }
        if ($nombreMostrado === '') {
            return Response::error("Nombre mostrado inválido", 400);
        }

        if ($citaId !== null) {
            $reserva = $this->citaModel->getClienteReservaById($citaId, $clienteId);
            if ($reserva === null) {
                return Response::error("Reserva no encontrada", 404);
            }
            if ($reserva === false) {
                return Response::error("No puedes valorar una reserva ajena", 403);
            }
            if (($reserva['estado'] ?? '') !== 'completada') {
                return Response::error("Solo puedes valorar reservas completadas", 400);
            }
        }

        $id = $this->testimonioModel->createClienteValoracion($clienteId, [
            'cita_id' => $citaId,
            'puntuacion' => $puntuacion,
            'comentario' => $comentario,
            'nombre_mostrado' => $nombreMostrado,
        ]);

        Response::json([
            'success' => true,
            'message' => 'Valoración recibida y pendiente de moderación',
            'data' => $this->testimonioModel->getById($id),
        ], 201);
    }

    public function listarValoraciones($authUser) {
        $clienteId = $this->resolveClienteId($authUser);
        if (!$clienteId) {
            return Response::error("Cliente no autorizado", 401);
        }

        Response::json([
            'success' => true,
            'data' => $this->testimonioModel->listByCliente($clienteId),
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
        return $this->reservationTimingRestriction($fecha, $hora) !== null;
    }

    private function reservationTimingRestriction(string $fecha, string $hora): ?string {
        $horaNormalizada = $this->normalizeTime($hora);
        if (!$horaNormalizada) {
            return 'past';
        }

        $startAt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $horaNormalizada,
            new DateTimeZone('America/Santiago')
        );
        if (!$startAt) {
            return 'past';
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
        if ($fecha < $now->format('Y-m-d')) {
            return 'past';
        }

        if ($fecha === $now->format('Y-m-d') && $startAt < $now->modify('+1 hour')) {
            return 'too_soon';
        }

        return null;
    }

    private function buildSlots(string $fecha, string $hora, int $duracionMin): array {
        $duracionMin = in_array($duracionMin, [30, 60, 90], true) ? $duracionMin : 30;
        $startAt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $hora,
            new DateTimeZone('America/Santiago')
        );
        if (!$startAt) {
            return [];
        }

        $slots = [];
        for ($i = 0; $i < (int)($duracionMin / 30); $i++) {
            $slots[] = $startAt->modify('+' . ($i * 30) . ' minutes')->format('H:i:s');
        }

        return $slots;
    }

    private function notifyClientRescheduledBooking(array $before, array $after): void {
        $id = (int)($after['id'] ?? 0);
        $correo = trim((string)($after['correo'] ?? ''));
        $data = $this->bookingMailData($after);
        $data['fecha_anterior'] = $before['fecha'] ?? '';
        $data['hora_anterior'] = $before['hora'] ?? '';
        $data['fecha_nueva'] = $after['fecha'] ?? '';
        $data['hora_nueva'] = $after['hora'] ?? '';
        $data['hora_fin_nueva'] = $this->bookingEndTime($after);
        $data['origen'] = 'cliente';
        $data['estado'] = 'pendiente';

        if ($correo !== '') {
            $sent = sendMail(
                $correo,
                'Recibimos tu solicitud de reagendamiento - ' . mailBrandName(),
                buildClientBookingRescheduleRequestedMail($data)
            );
            $this->logMailEvent('reagendamiento_cliente', $id, $sent);
        } else {
            error_log('Mail event reagendamiento_cliente fallida | reserva_id=' . $id . ' | reason=cliente_sin_correo');
        }

        $notifyTo = mailEnv('MAIL_NOTIFY_TO') ?: mailEnv('MAIL_FROM_ADDRESS') ?: mailEnv('MAIL_FROM');
        if ($notifyTo) {
            $sent = sendMail(
                $notifyTo,
                'Solicitud de reagendamiento de cliente - ' . mailBrandName(),
                buildAdminBookingClientRescheduleRequestedMail($data)
            );
            $this->logMailEvent('reagendamiento_cliente_admin', $id, $sent);
        } else {
            error_log('Mail event reagendamiento_cliente_admin fallida | reserva_id=' . $id . ' | reason=admin_sin_destinatario');
        }
    }

    private function bookingMailData(array $reserva): array {
        return [
            'nombre' => $reserva['cliente'] ?? $reserva['nombre'] ?? '',
            'correo' => $reserva['correo'] ?? '',
            'fecha' => $reserva['fecha'] ?? '',
            'hora' => $reserva['hora'] ?? '',
            'hora_fin' => $this->bookingEndTime($reserva),
            'duracion_min' => (int)($reserva['duracion_min'] ?? 30),
            'servicio' => $reserva['servicio'] ?? $reserva['servicio_nombre'] ?? '',
            'motivo' => $reserva['observacion_admin'] ?? '',
        ];
    }

    private function bookingEndTime(array $reserva): string {
        $fecha = $this->dateOnly($reserva['fecha'] ?? '');
        $hora = (string)($reserva['hora'] ?? '');
        $horaNormalizada = $this->normalizeTime($hora);
        if ($fecha === '' || !$horaNormalizada) {
            return '';
        }

        $duracionMin = (int)($reserva['duracion_min'] ?? 30);
        $startAt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $horaNormalizada,
            new DateTimeZone('America/Santiago')
        );

        if (!$startAt) {
            return '';
        }

        return $startAt->modify('+' . $duracionMin . ' minutes')->format('H:i:s');
    }

    private function dateOnly($value): string {
        $date = $this->normalizeDate((string)$value);
        if ($date) {
            return $date;
        }

        $ts = strtotime((string)$value);
        return $ts === false ? '' : date('Y-m-d', $ts);
    }

    private function logMailEvent(string $event, int $reservaId, bool $sent): void {
        $result = json_encode(mailLastResult(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log(
            'Mail event ' . $event . ' ' . ($sent ? 'enviada' : 'fallida')
            . ' | reserva_id=' . $reservaId
            . ' | result=' . ($result ?: '{}')
        );
    }

}
