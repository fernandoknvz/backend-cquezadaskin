<?php
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/DisponibilidadModel.php';
require_once __DIR__ . '/../models/ServicioModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/mailer.php';

class ReservaController {
    private $citaModel;
    private $clienteModel;
    private $disponibilidadModel;
    private $servicioModel;

    public function __construct($pdo) {
        $this->citaModel = new CitaModel($pdo);
        $this->clienteModel = new ClienteModel($pdo);
        $this->disponibilidadModel = new DisponibilidadModel($pdo);
        $this->servicioModel = new ServicioModel($pdo);
    }

    public function create($authUser, $body) {
        $clienteId = (int)($authUser['cliente_id'] ?? ($authUser['sub'] ?? 0));
        if (!$clienteId) {
            return Response::error("Cliente no autorizado", 401);
        }

        $cliente = $this->clienteModel->getById($clienteId);
        if (!$cliente) {
            return Response::error("Cliente autenticado no existe", 401);
        }

        $servicioId = (int)($body['servicio_id'] ?? 0);
        $fecha = $this->normalizeDate($body['fecha'] ?? null);
        $hora = $this->normalizeTime($body['hora'] ?? null);
        $duracionMin = (int)($body['duracion_min'] ?? 30);
        $allowedDurations = [30, 60, 90];

        if (!$servicioId || !$fecha || !$hora) {
            return Response::error("servicio_id, fecha y hora son requeridos", 400);
        }

        if (!in_array($duracionMin, $allowedDurations, true)) {
            return Response::error("Duracion invalida. Use 30, 60 o 90 minutos", 400);
        }

        $servicio = $this->servicioModel->getById($servicioId);
        if (!$servicio || (isset($servicio['activo']) && (int)$servicio['activo'] !== 1)) {
            return Response::error("Servicio no disponible para reserva", 422);
        }

        $startAt = $this->makeChileDateTime($fecha, $hora);
        if (!$startAt) {
            return Response::error("Fecha u hora invalida", 400);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
        $today = $now->format('Y-m-d');
        if ($fecha < $today) {
            return Response::error("No se puede reservar en horarios pasados", 400);
        }

        if ($fecha === $today && $startAt < $now->modify('+1 hour')) {
            return Response::error("Para reservas de hoy debes seleccionar un horario con al menos 1 hora de anticipación", 400);
        }

        if ($startAt < $now) {
            return Response::error("No se puede reservar en horarios pasados", 400);
        }

        $blocks = (int)($duracionMin / 30);
        $slots = [];
        for ($i = 0; $i < $blocks; $i++) {
            $slots[] = $startAt->modify('+' . ($i * 30) . ' minutes')->format('H:i:s');
        }

        foreach ($slots as $slotHora) {
            if ($this->citaModel->hasActiveConflict($fecha, $slotHora)) {
                return Response::json([
                    'ok' => false,
                    'success' => false,
                    'mensaje' => 'Este horario ya fue reservado',
                    'message' => 'Este horario ya fue reservado',
                    'error' => 'Este horario ya fue reservado',
                ], 409);
            }

            if (!$this->disponibilidadModel->isSlotAvailable($fecha, $slotHora)) {
                return Response::error("Horario no disponible para {$duracionMin} minutos", 409);
            }
        }

        $citaIds = [];
        foreach ($slots as $slotHora) {
            $citaIds[] = (int)$this->citaModel->create($clienteId, $servicioId, $fecha, $slotHora, 'solicitada');
        }

        $horaFin = $this->calculateEndTime($fecha, $slots);
        $this->notifyBookingReceived($cliente, $servicio, $fecha, $slots[0] ?? $hora, $horaFin, $duracionMin, $servicioId, $citaIds);

        Response::json([
            'success' => true,
            'message' => 'Reserva solicitada correctamente',
            'estado' => 'solicitada',
            'reserva' => [
                'cliente_id' => $clienteId,
                'servicio_id' => $servicioId,
                'fecha' => $fecha,
                'hora' => substr($slots[0], 0, 5),
                'duracion_min' => $duracionMin,
                'slots' => array_map(fn($h) => substr($h, 0, 5), $slots),
                'cita_ids' => $citaIds,
            ],
        ], 201);
    }

    private function normalizeDate($value) {
        if (!$value || !is_string($value)) {
            return null;
        }
        $value = trim($value);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }

    private function normalizeTime($value) {
        if (!$value || !is_string($value)) {
            return null;
        }
        $value = trim($value);
        $time = DateTime::createFromFormat('H:i', $value)
            ?: DateTime::createFromFormat('H:i:s', $value);
        return $time ? $time->format('H:i:s') : null;
    }

    private function calculateEndTime(string $fecha, array $slots): string {
        if (empty($slots)) {
            return '';
        }

        $lastSlot = $slots[count($slots) - 1];
        $endTs = strtotime($fecha . ' ' . $lastSlot);

        return $endTs !== false ? date('H:i:s', $endTs + (30 * 60)) : '';
    }

    private function makeChileDateTime(string $fecha, string $hora): ?DateTimeImmutable {
        $dateTime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $fecha . ' ' . $hora,
            new DateTimeZone('America/Santiago')
        );

        return $dateTime instanceof DateTimeImmutable ? $dateTime : null;
    }

    private function notifyBookingReceived(array $cliente, array $servicio, string $fecha, string $hora, string $horaFin, int $duracionMin, int $servicioId, array $citaIds): void {
        $nombre = trim((string)($cliente['nombre'] ?? ''));
        $correo = trim((string)($cliente['correo'] ?? ''));
        $telefono = trim((string)($cliente['telefono'] ?? ''));
        $servicioNombre = trim((string)($servicio['nombre'] ?? ''));

        $mailData = [
            'nombre' => $nombre,
            'correo' => $correo,
            'telefono' => $telefono,
            'fecha' => $fecha,
            'hora' => $hora,
            'hora_fin' => $horaFin,
            'duracion_min' => $duracionMin,
            'servicio_id' => $servicioId,
            'servicio' => $servicioNombre,
        ];

        $notifyTo = mailEnv('MAIL_NOTIFY_TO') ?: mailEnv('MAIL_FROM_ADDRESS');
        if ($notifyTo) {
            try {
                $sent = sendMail(
                    $notifyTo,
                    'Nueva solicitud de reserva - ' . mailBrandName(),
                    buildAdminNewBookingMail($mailData)
                );

                if (!$sent) {
                    error_log('Mailer Error: no se pudo enviar notificacion admin de reserva solicitada. cita_ids=' . implode(',', $citaIds));
                }
            } catch (Exception $mailEx) {
                error_log('Mailer Error: excepcion enviando notificacion admin de reserva solicitada: ' . $mailEx->getMessage());
            }
        } else {
            error_log('Mailer Error: MAIL_NOTIFY_TO o MAIL_FROM_ADDRESS no configurado para notificacion admin de reserva.');
        }

        if ($correo) {
            try {
                $sent = sendMail(
                    $correo,
                    'Hemos recibido tu solicitud de reserva - ' . mailBrandName(),
                    buildClientBookingReceivedMail($mailData)
                );

                if (!$sent) {
                    error_log('Mailer Error: no se pudo enviar correo al cliente por reserva solicitada. cita_ids=' . implode(',', $citaIds));
                }
            } catch (Exception $mailEx) {
                error_log('Mailer Error: excepcion enviando correo al cliente por reserva solicitada: ' . $mailEx->getMessage());
            }
        } else {
            error_log('Mailer Error: cliente sin correo para confirmacion de solicitud recibida. cita_ids=' . implode(',', $citaIds));
        }
    }
}
