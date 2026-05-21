<?php
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/DisponibilidadModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';
require_once __DIR__ . '/../config/mailer.php';

class CitaController {
    private $citaModel;
    private $clienteModel;
    private $disponibilidadModel;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->citaModel = new CitaModel($pdo);
        $this->clienteModel = new ClienteModel($pdo);
        $this->disponibilidadModel = new DisponibilidadModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
        AuthGuard::onlyAdmins($this->pdo, $method);

        try {
            switch ($method) {
                case 'GET':
                    $data = $this->citaModel->getAll();
                    Response::json($data);
                    break;

                case 'POST':
                    $nombre = trim($body['nombre'] ?? '');

                    // ✅ Compat: aceptar "correo" o "email" desde frontend
                    $correo = trim($body['correo'] ?? ($body['email'] ?? ''));

                    $telefono = trim($body['telefono'] ?? '');
                    $fecha = trim($body['fecha'] ?? '');
                    $hora = trim($body['hora'] ?? '');
                    $servicioId = intval($body['servicio_id'] ?? 0);

                    // Duración (MVP): 30/60/90 (bloques de 30)
                    $duracionMin = (int)($body['duracion_min'] ?? 30);
                    $allowedDurations = [30, 60, 90];
                    if (!in_array($duracionMin, $allowedDurations, true)) {
                        return Response::error("Duración inválida. Use 30, 60 o 90 minutos", 400);
                    }

                    if (!$nombre || !$correo || !$telefono || !$fecha || !$hora || !$servicioId) {
                        return Response::error("Datos incompletos para agendar cita", 400);
                    }

                    $fechaKey = date('Y-m-d', strtotime($fecha));
                    if (!$fechaKey) {
                        return Response::error("Fecha inválida", 400);
                    }

                    $startTs = strtotime($fechaKey . ' ' . $hora);
                    if ($startTs === false) {
                        return Response::error("Hora inválida", 400);
                    }

                    // Regla operativa: si la reserva es para hoy, debe ser con al menos 2 horas de anticipación
                    $now = time();
                    $fechaHoy = date('Y-m-d');
                    $margenMinimoSegundos = 2 * 60 * 60;
                                    
                    if ($fechaKey === $fechaHoy && $startTs < ($now + $margenMinimoSegundos)) {
                        return Response::error("Para reservas de hoy debes seleccionar un horario con al menos 2 horas de anticipación", 400);
                    }
                                    
                    if ($startTs < ($now - 60)) {
                        return Response::error("No se puede agendar en horarios pasados", 400);
                    }

                    $blocks = (int)($duracionMin / 30);

                    // Generar slots consecutivos (H:i:s)
                    $slots = [];
                    for ($i = 0; $i < $blocks; $i++) {
                        $slots[] = date('H:i:s', $startTs + ($i * 30 * 60));
                    }

                    $horaFin = '';
                    if (!empty($slots)) {
                        $lastSlot = $slots[count($slots) - 1];
                        $endTs = strtotime($fechaKey . ' ' . $lastSlot);
                        if ($endTs !== false) {
                            $horaFin = date('H:i:s', $endTs + (30 * 60));
                        }
                    }

                    // Validar disponibilidad de todos los slots
                    foreach ($slots as $slotHora) {
                        if (!$this->disponibilidadModel->isSlotAvailable($fechaKey, $slotHora)) {
                            return Response::error("Horario no disponible para {$duracionMin} minutos", 409);
                        }
                    }

                    // Verificar si el cliente ya existe
                    $cliente = $this->clienteModel->getByCorreo($correo);
                    if ($cliente) {
                        $clienteId = $cliente['id'];
                    } else {
                        $clienteId = $this->clienteModel->create([
                            'nombre' => $nombre,
                            'correo' => $correo,
                            'telefono' => $telefono
                        ]);
                    }

                    // Crear la(s) cita(s): 1 fila por slot de 30 min
                    $citaIds = [];
                    foreach ($slots as $slotHora) {
                        $citaIds[] = (int)$this->citaModel->create($clienteId, $servicioId, $fechaKey, $slotHora);
                    }

                    $servicioNombre = '';
                    try {
                        $stmtServicio = $this->pdo->prepare("SELECT nombre FROM servicios WHERE id = :id LIMIT 1");
                        $stmtServicio->execute(['id' => $servicioId]);
                        $servicioRow = $stmtServicio->fetch(PDO::FETCH_ASSOC);
                        $servicioNombre = trim((string)($servicioRow['nombre'] ?? ''));
                    } catch (Exception $e) {
                        error_log("Error obteniendo nombre del servicio para correo: " . $e->getMessage());
                    }

                    // Notificación admin (solo 1 correo por reserva)
                    $notifyTo = getenv('MAIL_NOTIFY_TO') ?: getenv('MAIL_FROM_ADDRESS');
                    if ($notifyTo) {
                        try {
                            $subjectAdmin = "Nueva solicitud de reserva - " . mailBrandName();
                            $bodyMailAdmin = buildAdminNewBookingMail([
                                'nombre' => $nombre,
                                'correo' => $correo,
                                'telefono' => $telefono,
                                'fecha' => $fechaKey,
                                'hora' => $slots[0] ?? $hora,
                                'hora_fin' => $horaFin,
                                'duracion_min' => $duracionMin,
                                'servicio_id' => $servicioId,
                                'servicio' => $servicioNombre,
                            ]);
                            sendMail($notifyTo, $subjectAdmin, $bodyMailAdmin);
                        } catch (Exception $mailEx) {
                            error_log("Error enviando correo admin de nueva cita: " . $mailEx->getMessage());
                        }
                    }
                                    
                    // Confirmación de recepción al cliente
                    if ($correo) {
                        try {
                            $subjectCliente = "Hemos recibido tu solicitud de reserva - " . mailBrandName();
                            $bodyMailCliente = buildClientBookingReceivedMail([
                                'nombre' => $nombre,
                                'fecha' => $fechaKey,
                                'hora' => $slots[0] ?? $hora,
                                'hora_fin' => $horaFin,
                                'duracion_min' => $duracionMin,
                                'servicio' => $servicioNombre,
                            ]);
                            sendMail($correo, $subjectCliente, $bodyMailCliente);
                        } catch (Exception $mailEx) {
                            error_log("Error enviando correo al cliente por solicitud recibida: " . $mailEx->getMessage());
                        }
                    }

                    Response::json([
                        "message" => "Cita agendada correctamente",
                        "duracion_min" => $duracionMin,
                        "slots" => array_map(fn($h) => substr($h, 0, 5), $slots),
                        "cita_ids" => $citaIds
                    ]);
                    break;

                case 'PUT':
                case 'PATCH':
                    $id = (int)($params['id'] ?? 0);
                    $estado = $body['estado'] ?? '';
                    $allowed = ['solicitada', 'pendiente', 'confirmada', 'cancelada'];

                    if (!$id || ($estado && !in_array($estado, $allowed, true))) {
                        return Response::error("Datos invalidos para actualizar", 400);
                    }

                    $before = $this->citaModel->getById($id);
                    if (!$before) {
                        return Response::error("Cita no encontrada", 404);
                    }

                    if (isset($body['fecha']) || isset($body['hora'])) {
                        $nuevaFecha = $body['fecha'] ?? $before['fecha'];
                        $nuevaHora = $body['hora'] ?? $before['hora'];
                        $fechaKey = date('Y-m-d', strtotime((string)$nuevaFecha));
                        $horaKey = date('H:i:s', strtotime((string)$nuevaHora));

                        if (!$this->disponibilidadModel->isSlotAvailable($fechaKey, $horaKey, $id)) {
                            return Response::error("Horario no disponible", 409);
                        }
                    }

                    $ok = false;
                    $estadoAnterior = $before['estado'] ?? '';
                                    
                    // Confirmar grupo continuo solicitado/pendiente
                    if ($estado === 'confirmada' && in_array($estadoAnterior, ['solicitada', 'pendiente'], true)) {
                        $fechaBase = date('Y-m-d', strtotime((string)$before['fecha']));
                        $horaBase = date('H:i:s', strtotime((string)$before['hora']));

                        $pendingGroup = $this->citaModel->getContinuousGroupByState(
                            $before['cliente_id'],
                            $before['servicio_id'],
                            $fechaBase,
                            $horaBase,
                            $estadoAnterior
                        );
                                    
                        if (!empty($pendingGroup)) {
                            $groupIds = array_map(fn($row) => (int)$row['id'], $pendingGroup);
                            $ok = $this->citaModel->updateStatusByIds($groupIds, 'confirmada');
                        } else {
                            $ok = $this->citaModel->update($id, $body);
                        }
                                    
                    // Cancelar grupo continuo del estado anterior
                    } elseif ($estado === 'cancelada' && in_array($estadoAnterior, ['solicitada', 'pendiente', 'confirmada'], true)) {
                        $fechaBase = date('Y-m-d', strtotime((string)$before['fecha']));
                        $horaBase = date('H:i:s', strtotime((string)$before['hora']));
                                    
                        $group = $this->citaModel->getContinuousGroupByState(
                            $before['cliente_id'],
                            $before['servicio_id'],
                            $fechaBase,
                            $horaBase,
                            $estadoAnterior
                        );
                                    
                        if (!empty($group)) {
                            $groupIds = array_map(fn($row) => (int)$row['id'], $group);
                            $ok = $this->citaModel->updateStatusByIds($groupIds, 'cancelada');
                        } else {
                            $ok = $this->citaModel->update($id, $body);
                        }
                                    
                    } else {
                        $ok = $this->citaModel->update($id, $body);
                    }
                                    
                    $shouldNotifyConfirm = $ok
                        && $estado === 'confirmada'
                        && in_array($estadoAnterior, ['solicitada', 'pendiente'], true);
                                    
                    $shouldNotifyCancel = $ok
                        && $estado === 'cancelada'
                        && in_array($estadoAnterior, ['solicitada', 'pendiente', 'confirmada'], true);
                                    
                    // Correo de confirmación
                    if ($shouldNotifyConfirm) {
                        $correoCliente = $body['correo'] ?? ($before['correo'] ?? '');
                                    
                        error_log("MAIL CONFIRM DEBUG | id={$id} | correo=" . ($correoCliente ?: 'VACIO'));
                                    
                        if ($correoCliente) {
                            try {
                                $fechaMail = date('Y-m-d', strtotime((string)$before['fecha']));
                                $horaMail = date('H:i:s', strtotime((string)$before['hora']));
                                    
                                $group = $this->citaModel->getContinuousConfirmedGroup(
                                    $before['cliente_id'],
                                    $before['servicio_id'],
                                    $fechaMail,
                                    $horaMail
                                );
                                    
                                error_log(
                                    "MAIL CONFIRM DEBUG | id={$id} | fecha={$fechaMail} | hora={$horaMail} | group_count=" . count($group)
                                );
                                    
                                if (!empty($group)) {
                                    $firstSlot = $group[0];
                                    $lastSlot = $group[count($group) - 1];
                                    
                                    $lastFecha = date('Y-m-d', strtotime((string)($lastSlot['fecha'] ?? '')));
                                    $lastHora = date('H:i:s', strtotime((string)($lastSlot['hora'] ?? '')));
                                    $endTs = strtotime($lastFecha . ' ' . $lastHora);
                                    $endTs = $endTs !== false ? $endTs + (30 * 60) : false;
                                    
                                    $duracionTotal = count($group) * 30;
                                    
                                    $subject = "Tu cita ha sido confirmada - " . mailBrandName();
                                    $bodyHtml = buildClientBookingConfirmedMail([
                                        'nombre' => $before['cliente'] ?? '',
                                        'fecha' => $firstSlot['fecha'] ?? $fechaMail,
                                        'hora' => $firstSlot['hora'] ?? $horaMail,
                                        'hora_fin' => $endTs !== false ? date('H:i:s', $endTs) : '',
                                        'duracion_min' => $duracionTotal,
                                        'servicio' => $before['servicio'] ?? '',
                                    ]);
                                    
                                    $sent = sendMail($correoCliente, $subject, $bodyHtml);
                                    error_log("MAIL CONFIRM DEBUG | id={$id} | sent=" . ($sent ? 'true' : 'false'));
                                } else {
                                    error_log("MAIL CONFIRM DEBUG | id={$id} | group vacio tras confirmar");
                                }
                            } catch (Exception $mailEx) {
                                error_log("Error enviando correo de confirmación de cita ID {$id}: " . $mailEx->getMessage());
                            }
                        }
                    }
                                    
                    // Correo de cancelación
                    if ($shouldNotifyCancel) {
                        $correoCliente = $body['correo'] ?? ($before['correo'] ?? '');
                        $motivoCancelacion = trim((string)($body['motivo_cancelacion'] ?? ''));
                                    
                        error_log("MAIL CANCEL DEBUG | id={$id} | correo=" . ($correoCliente ?: 'VACIO'));
                                    
                        if ($correoCliente) {
                            try {
                                $fechaMail = date('Y-m-d', strtotime((string)$before['fecha']));
                                $horaMail = date('H:i:s', strtotime((string)$before['hora']));
                                    
                                $group = $this->citaModel->getContinuousGroupByState(
                                    $before['cliente_id'],
                                    $before['servicio_id'],
                                    $fechaMail,
                                    $horaMail,
                                    'cancelada'
                                );
                                    
                                error_log(
                                    "MAIL CANCEL DEBUG | id={$id} | fecha={$fechaMail} | hora={$horaMail} | group_count=" . count($group)
                                );
                                    
                                if (!empty($group)) {
                                    $firstSlot = $group[0];
                                    $lastSlot = $group[count($group) - 1];
                                    
                                    $lastFecha = date('Y-m-d', strtotime((string)($lastSlot['fecha'] ?? '')));
                                    $lastHora = date('H:i:s', strtotime((string)($lastSlot['hora'] ?? '')));
                                    $endTs = strtotime($lastFecha . ' ' . $lastHora);
                                    $endTs = $endTs !== false ? $endTs + (30 * 60) : false;
                                    
                                    $duracionTotal = count($group) * 30;
                                    
                                    $subject = "Tu cita ha sido cancelada - " . mailBrandName();
                                    $bodyHtml = buildClientBookingCancelledMail([
                                        'nombre' => $before['cliente'] ?? '',
                                        'fecha' => $firstSlot['fecha'] ?? $fechaMail,
                                        'hora' => $firstSlot['hora'] ?? $horaMail,
                                        'hora_fin' => $endTs !== false ? date('H:i:s', $endTs) : '',
                                        'duracion_min' => $duracionTotal,
                                        'servicio' => $before['servicio'] ?? '',
                                        'motivo' => $motivoCancelacion,
                                    ]);
                                    
                                    $sent = sendMail($correoCliente, $subject, $bodyHtml);
                                    error_log("MAIL CANCEL DEBUG | id={$id} | sent=" . ($sent ? 'true' : 'false'));
                                } else {
                                    error_log("MAIL CANCEL DEBUG | id={$id} | group vacio tras cancelar");
                                }
                            } catch (Exception $mailEx) {
                                error_log("Error enviando correo de cancelación de cita ID {$id}: " . $mailEx->getMessage());
                            }
                        }
                    }
                                    
                    if (!$shouldNotifyConfirm && !$shouldNotifyCancel) {
                        error_log(
                            "MAIL STATUS DEBUG | id={$id} | sin envio | estado_nuevo=" . ($estado ?? '') . " | estado_anterior=" . $estadoAnterior
                        );
                    }

                    Response::json([
                        "success" => $ok,
                        "message" => $ok ? "Cita actualizada" : "No se realizaron cambios"
                    ]);
                    break;

                case 'DELETE':
                    $id = (int)($params['id'] ?? 0);
                    if (!$id) {
                        return Response::error("ID requerido para eliminar", 400);
                    }
                    $ok = $this->citaModel->delete($id);
                    Response::json([
                        "success" => $ok,
                        "message" => $ok ? "Cita eliminada" : "No se encontro la cita"
                    ]);
                    break;

                default:
                    Response::error("Método no permitido", 405);
                    break;
            }
        } catch (Exception $e) {
            Response::error("Error interno: " . $e->getMessage(), 500);
        }
    }
}
