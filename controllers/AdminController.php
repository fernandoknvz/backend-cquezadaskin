<?php
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../models/DisponibilidadModel.php';
require_once __DIR__ . '/../models/FaqModel.php';
require_once __DIR__ . '/../models/TestimonioModel.php';
require_once __DIR__ . '/../controllers/FaqController.php';
require_once __DIR__ . '/../utils/Response.php';

class AdminController {
    private ClienteModel $clienteModel;
    private CitaModel $citaModel;
    private DisponibilidadModel $disponibilidadModel;
    private FaqModel $faqModel;
    private TestimonioModel $testimonioModel;
    private FaqController $faqController;

    private const ESTADOS_RESERVA = ['solicitada', 'pendiente', 'confirmada', 'cancelada', 'completada', 'reagendada'];

    public function __construct($pdo) {
        $this->clienteModel = new ClienteModel($pdo);
        $this->citaModel = new CitaModel($pdo);
        $this->disponibilidadModel = new DisponibilidadModel($pdo);
        $this->faqModel = new FaqModel($pdo);
        $this->testimonioModel = new TestimonioModel($pdo);
        $this->faqController = new FaqController($pdo);
    }

    public function handleRequest(string $method, array $segments, array $params, array $body): void {
        $resource = $segments[1] ?? '';

        if ($resource === 'clientes') {
            $this->handleClientes($method, $segments, $params, $body);
            return;
        }

        if ($resource === 'reservas') {
            $this->handleReservas($method, $segments, $params, $body);
            return;
        }

        if ($resource === 'calendario') {
            $this->handleCalendario($method, $segments, $params);
            return;
        }

        if ($resource === 'disponibilidad') {
            $this->handleDisponibilidad($method, $segments, $params, $body);
            return;
        }

        if ($resource === 'bloqueos') {
            $this->handleBloqueos($method, $segments, $body);
            return;
        }

        if ($resource === 'faq') {
            $this->handleFaq($method, $segments, $body);
            return;
        }

        if ($resource === 'valoraciones') {
            $this->handleValoraciones($method, $segments, $body);
            return;
        }

        Response::error("Ruta admin no encontrada", 404);
    }

    private function handleClientes(string $method, array $segments, array $params, array $body): void {
        $id = $this->numericId($segments[2] ?? null);

        if ($method === 'GET' && !$id) {
            $result = $this->clienteModel->listAdmin($params);
            Response::json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
            return;
        }

        if (!$id) {
            Response::error("ID de cliente inválido", 400);
            return;
        }

        if ($method === 'GET') {
            $detail = $this->clienteModel->getAdminDetail($id);
            if (!$detail) {
                Response::error("Cliente no encontrado", 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => $detail,
            ]);
            return;
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $this->updateCliente($id, $body);
            return;
        }

        if ($method === 'DELETE') {
            $updated = $this->clienteModel->updateAdmin($id, ['activo' => false]);
            if (!$updated && !$this->clienteModel->getById($id)) {
                Response::error("Cliente no encontrado", 404);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Cliente desactivado',
            ]);
            return;
        }

        Response::error("Método no permitido", 405);
    }

    private function updateCliente(int $id, array $body): void {
        $cliente = $this->clienteModel->getById($id);
        if (!$cliente) {
            Response::error("Cliente no encontrado", 404);
            return;
        }

        $data = [];
        foreach (['nombre', 'telefono', 'notas_admin'] as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = $body[$field];
            }
        }

        if (array_key_exists('email', $body) || array_key_exists('correo', $body)) {
            $email = strtolower(trim((string)($body['correo'] ?? $body['email'] ?? '')));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error("Email inválido", 400);
                return;
            }
            $data['correo'] = $email !== '' ? $email : null;
        }

        if (array_key_exists('activo', $body)) {
            $data['activo'] = $body['activo'];
        }

        if (array_key_exists('nombre', $data) && trim((string)$data['nombre']) === '') {
            Response::error("Nombre inválido", 400);
            return;
        }

        if (empty($data)) {
            Response::error("No hay campos válidos para actualizar", 400);
            return;
        }

        try {
            $this->clienteModel->updateAdmin($id, $data);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error("Correo o RUT ya registrado por otro cliente", 409);
                return;
            }
            throw $e;
        }

        Response::json([
            'success' => true,
            'message' => 'Cliente actualizado',
            'data' => $this->clienteModel->safeAdminArray($this->clienteModel->getById($id)),
        ]);
    }

    private function handleReservas(string $method, array $segments, array $params, array $body): void {
        $id = $this->numericId($segments[2] ?? null);
        $action = $segments[3] ?? '';

        if ($method === 'GET' && !$id) {
            $filters = $this->normalizeReservaFilters($params);
            if (isset($filters['error'])) {
                Response::error($filters['error'], 400);
                return;
            }

            $result = $this->citaModel->listAdmin($filters);
            Response::json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
            return;
        }

        if (!$id) {
            Response::error("ID de reserva inválido", 400);
            return;
        }

        if ($method === 'GET') {
            $reserva = $this->citaModel->getById($id);
            if (!$reserva) {
                Response::error("Reserva no encontrada", 404);
                return;
            }
            Response::json(['success' => true, 'data' => $reserva]);
            return;
        }

        if ($method === 'PATCH' && $action === 'estado') {
            $this->updateReservaEstado($id, $body);
            return;
        }

        if ($method === 'PATCH' && $action === 'reagendar') {
            $this->reagendarReserva($id, $body);
            return;
        }

        if ($method === 'DELETE') {
            if (!$this->citaModel->getById($id)) {
                Response::error("Reserva no encontrada", 404);
                return;
            }
            $this->citaModel->delete($id);
            Response::json(['success' => true, 'message' => 'Reserva eliminada']);
            return;
        }

        Response::error("Método no permitido", 405);
    }

    private function handleCalendario(string $method, array $segments, array $params): void {
        if ($method !== 'GET') {
            Response::error("Método no permitido", 405);
            return;
        }

        $scope = $segments[2] ?? '';
        $filters = $this->normalizeCalendarioFilters($params);
        if (isset($filters['error'])) {
            Response::error($filters['error'], 400);
            return;
        }

        if ($scope === 'dia') {
            $fecha = $this->normalizeDate($params['fecha'] ?? $params['fecha_desde'] ?? date('Y-m-d'));
            if (!$fecha) {
                Response::error("Fecha inválida", 400);
                return;
            }
            $filters['fecha_desde'] = $fecha;
            $filters['fecha_hasta'] = $fecha;
        } elseif ($scope === 'semana') {
            $fecha = $this->normalizeDate($params['fecha'] ?? date('Y-m-d'));
            if (!$fecha) {
                Response::error("Fecha inválida", 400);
                return;
            }
            $start = new DateTime($fecha);
            $start->modify('monday this week');
            $end = clone $start;
            $end->modify('+6 days');
            $filters['fecha_desde'] = $start->format('Y-m-d');
            $filters['fecha_hasta'] = $end->format('Y-m-d');
        } elseif ($scope !== '') {
            Response::error("Ruta de calendario no encontrada", 404);
            return;
        }

        Response::json([
            'success' => true,
            'data' => $this->citaModel->listCalendar($filters),
        ]);
    }

    private function handleDisponibilidad(string $method, array $segments, array $params, array $body): void {
        $action = $segments[2] ?? '';

        if ($action === 'bulk') {
            if ($method !== 'POST') {
                Response::error("Metodo no permitido", 405);
                return;
            }
            $this->createDisponibilidadBulk($body);
            return;
        }

        if ($action === 'habilitar-bulk') {
            if ($method !== 'POST') {
                Response::error("Metodo no permitido", 405);
                return;
            }
            $this->habilitarDisponibilidadBulk($body);
            return;
        }

        if ($action === 'habilitar-dia' || $action === 'bloquear-dia') {
            if ($method !== 'POST') {
                Response::error("Metodo no permitido", 405);
                return;
            }
            $this->updateDisponibilidadDia($body, $action === 'habilitar-dia');
            return;
        }

        $id = $this->numericId($segments[2] ?? null);

        if ($method === 'GET') {
            $filters = $this->normalizeDisponibilidadFilters($params);
            if (isset($filters['error'])) {
                Response::error($filters['error'], 400);
                return;
            }
            Response::json([
                'success' => true,
                'data' => $this->disponibilidadModel->listAdmin($filters),
            ]);
            return;
        }

        if ($method === 'POST') {
            $this->createDisponibilidad($body, false);
            return;
        }

        if (!$id) {
            Response::error("ID de disponibilidad inválido", 400);
            return;
        }

        if ($method === 'PATCH') {
            $this->updateDisponibilidad($id, $body);
            return;
        }

        if ($method === 'DELETE') {
            if (!$this->disponibilidadModel->getById($id)) {
                Response::error("Horario no encontrado", 404);
                return;
            }
            $this->disponibilidadModel->deleteSlot($id);
            Response::json(['success' => true, 'message' => 'Horario eliminado']);
            return;
        }

        Response::error("Método no permitido", 405);
    }

    private function handleBloqueos(string $method, array $segments, array $body): void {
        $id = $this->numericId($segments[2] ?? null);

        if ($method === 'POST') {
            $this->createDisponibilidad($body, true);
            return;
        }

        if ($method === 'DELETE') {
            if (!$id) {
                Response::error("ID de bloqueo inválido", 400);
                return;
            }
            $slot = $this->disponibilidadModel->getById($id);
            if (!$slot || ($slot['tipo'] ?? '') !== 'bloqueo') {
                Response::error("Bloqueo no encontrado", 404);
                return;
            }
            $this->disponibilidadModel->deleteSlot($id);
            Response::json(['success' => true, 'message' => 'Bloqueo eliminado']);
            return;
        }

        Response::error("Método no permitido", 405);
    }

    private function handleFaq(string $method, array $segments, array $body): void {
        $id = $this->numericId($segments[2] ?? null);

        if ($method === 'GET' && !$id) {
            $this->faqController->adminIndex();
            return;
        }
        if ($method === 'GET' && $id) {
            $this->faqController->adminShow($id);
            return;
        }
        if ($method === 'POST') {
            $this->faqController->adminCreate($body);
            return;
        }
        if (($method === 'PATCH' || $method === 'PUT') && $id) {
            $this->faqController->adminUpdate($id, $body);
            return;
        }
        if ($method === 'DELETE' && $id) {
            $this->faqController->adminDelete($id);
            return;
        }

        Response::error("Método no permitido", 405);
    }

    private function handleValoraciones(string $method, array $segments, array $body): void {
        $id = $this->numericId($segments[2] ?? null);
        $action = $segments[3] ?? '';

        if ($method === 'GET' && !$id) {
            Response::json(['success' => true, 'data' => $this->testimonioModel->listAdmin()]);
            return;
        }

        if (!$id) {
            Response::error("ID de valoración inválido", 400);
            return;
        }

        if ($method === 'GET') {
            $valoracion = $this->testimonioModel->getById($id);
            if (!$valoracion) {
                Response::error("Valoración no encontrada", 404);
                return;
            }
            Response::json(['success' => true, 'data' => $valoracion]);
            return;
        }

        if ($method === 'PATCH' && $action === 'estado') {
            $data = $this->validateValoracionAdmin($body, true);
            if (isset($data['error'])) {
                Response::error($data['error'], 400);
                return;
            }
            $this->testimonioModel->update($id, $data);
            Response::json([
                'success' => true,
                'message' => 'Estado de valoración actualizado',
                'data' => $this->testimonioModel->getById($id),
            ]);
            return;
        }

        if ($method === 'PATCH') {
            $data = $this->validateValoracionAdmin($body, false);
            if (isset($data['error'])) {
                Response::error($data['error'], 400);
                return;
            }
            if (empty($data)) {
                Response::error("No hay campos válidos para actualizar", 400);
                return;
            }
            $this->testimonioModel->update($id, $data);
            Response::json([
                'success' => true,
                'message' => 'Valoración actualizada',
                'data' => $this->testimonioModel->getById($id),
            ]);
            return;
        }

        if ($method === 'DELETE') {
            if (!$this->testimonioModel->getById($id)) {
                Response::error("Valoración no encontrada", 404);
                return;
            }
            $this->testimonioModel->softDelete($id);
            Response::json(['success' => true, 'message' => 'Valoración desactivada']);
            return;
        }

        Response::error("Método no permitido", 405);
    }

    private function validateValoracionAdmin(array $body, bool $requireEstado): array {
        $data = [];
        $hasVisibility = array_key_exists('visible', $body) || array_key_exists('publicada', $body);

        if ($requireEstado || array_key_exists('estado', $body)) {
            $estado = trim((string)($body['estado'] ?? ''));
            if ($estado === 'aprobada') {
                $estado = 'aprobado';
            }
            if (!in_array($estado, ['pendiente', 'aprobado', 'rechazado'], true)) {
                return ['error' => 'Estado inválido'];
            }
            $data['estado'] = $estado;

            if ($estado === 'aprobado' && !$hasVisibility) {
                $data['visible'] = 1;
                $data['activo'] = 1;
            } elseif ($estado !== 'aprobado' && !$hasVisibility) {
                $data['visible'] = 0;
            }
        }

        if ($hasVisibility) {
            $data['visible'] = filter_var($body['visible'] ?? $body['publicada'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $data['activo'] = $data['visible'] ? 1 : 0;
        }
        if (array_key_exists('respuesta_admin', $body)) {
            $data['respuesta_admin'] = $this->optionalText($body['respuesta_admin']);
        }
        if (array_key_exists('nombre_mostrado', $body)) {
            $nombre = trim((string)$body['nombre_mostrado']);
            if ($nombre === '') {
                return ['error' => 'Nombre mostrado inválido'];
            }
            $data['nombre_mostrado'] = $nombre;
        }
        if (array_key_exists('comentario', $body)) {
            $comentario = trim((string)$body['comentario']);
            if ($comentario === '') {
                return ['error' => 'Comentario inválido'];
            }
            $data['comentario'] = $comentario;
        }
        if (array_key_exists('puntuacion', $body)) {
            $puntuacion = (int)$body['puntuacion'];
            if ($puntuacion < 1 || $puntuacion > 5) {
                return ['error' => 'Puntuación inválida'];
            }
            $data['puntuacion'] = $puntuacion;
        }

        return $data;
    }

    private function createDisponibilidad(array $body, bool $forceBloqueo): void {
        $fecha = $this->normalizeDate($body['fecha'] ?? null);
        $hora = $this->normalizeTime($body['hora'] ?? null);
        $motivo = $this->optionalText($body['motivo'] ?? null);
        $disponible = $forceBloqueo ? false : filter_var($body['disponible'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $tipo = $forceBloqueo || !$disponible ? 'bloqueo' : 'disponible';

        if (!$fecha || !$hora) {
            Response::error("fecha y hora válidas son requeridas", 400);
            return;
        }

        if ($disponible && $this->disponibilidadModel->hasActiveBooking($fecha, $hora)) {
            Response::json([
                'ok' => false,
                'success' => false,
                'mensaje' => 'Este horario ya fue reservado',
                'message' => 'Este horario ya fue reservado',
                'error' => 'Este horario ya fue reservado',
            ], 409);
            return;
        }

        if (!$disponible && $this->disponibilidadModel->hasActiveBooking($fecha, $hora)) {
            Response::error("No se puede bloquear un horario con reserva activa", 409);
            return;
        }

        $id = $this->disponibilidadModel->upsertSlot($fecha, $hora, $disponible, $motivo, $tipo);
        Response::json([
            'success' => true,
            'message' => $tipo === 'bloqueo' ? 'Bloqueo guardado' : 'Disponibilidad guardada',
            'data' => $this->disponibilidadModel->getById($id),
        ], 201);
    }

    private function createDisponibilidadBulk(array $body): void {
        $validation = $this->validateDisponibilidadBulk($body);
        if (isset($validation['error'])) {
            Response::json([
                'ok' => false,
                'success' => false,
                'creados' => 0,
                'omitidos' => 0,
                'con_reserva' => 0,
                'errores' => [$validation['error']],
                'mensaje' => $validation['error'],
                'message' => $validation['error'],
                'error' => $validation['error'],
            ], 400);
            return;
        }

        $fecha = $validation['fecha'];
        $tipo = $validation['estado'];
        $disponible = $tipo === 'disponible';
        $horas = $this->buildBulkSlots(
            $validation['hora_inicio'],
            $validation['hora_fin'],
            $validation['intervalo_minutos']
        );

        if (empty($horas)) {
            Response::json([
                'ok' => false,
                'success' => false,
                'creados' => 0,
                'omitidos' => 0,
                'con_reserva' => 0,
                'errores' => ['No hay slots validos para crear'],
                'mensaje' => 'No hay slots validos para crear',
                'message' => 'No hay slots validos para crear',
                'error' => 'No hay slots validos para crear',
            ], 400);
            return;
        }

        $existentes = $this->disponibilidadModel->listExistingTimes($fecha, $horas);
        $conReserva = $this->disponibilidadModel->listTimesWithActiveBookings($fecha, $horas);
        $omitidos = count($existentes);
        $conReservaCount = count($conReserva);

        $bloqueados = array_fill_keys(array_merge($existentes, $conReserva), true);
        $validos = array_values(array_filter($horas, static function ($hora) use ($bloqueados) {
            return !isset($bloqueados[$hora]);
        }));

        $creados = 0;
        $errores = [];

        if (!empty($validos)) {
            try {
                $creados = $this->disponibilidadModel->insertSlots(
                    $fecha,
                    $validos,
                    $disponible,
                    $validation['motivo'],
                    $tipo
                );
                $omitidos += count($validos) - $creados;
            } catch (PDOException $e) {
                $errores[] = 'Error al crear disponibilidad masiva';
            }
        }

        Response::json([
            'ok' => empty($errores),
            'success' => empty($errores),
            'creados' => $creados,
            'omitidos' => $omitidos,
            'con_reserva' => $conReservaCount,
            'errores' => $errores,
            'mensaje' => empty($errores)
                ? 'Disponibilidad creada correctamente'
                : 'Disponibilidad creada parcialmente',
        ], empty($errores) ? 201 : 500);
    }

    private function habilitarDisponibilidadBulk(array $body): void {
        $validation = $this->validateHabilitarDisponibilidadBulk($body);
        if (isset($validation['error'])) {
            Response::json([
                'ok' => false,
                'mensaje' => 'fecha, hora_inicio y hora_fin validas son requeridas',
                'errores' => [],
            ], 400);
            return;
        }

        $fecha = $validation['fecha'];
        $horas = $this->buildBulkSlots(
            $validation['hora_inicio'],
            $validation['hora_fin'],
            $validation['intervalo_minutos']
        );

        if (empty($horas)) {
            Response::json([
                'ok' => false,
                'mensaje' => 'fecha, hora_inicio y hora_fin validas son requeridas',
                'errores' => [],
            ], 400);
            return;
        }

        $conReserva = $this->disponibilidadModel->listTimesWithActiveBookings($fecha, $horas);
        $conReservaMap = array_fill_keys($conReserva, true);
        $horasSinReserva = array_values(array_filter($horas, static function ($hora) use ($conReservaMap) {
            return !isset($conReservaMap[$hora]);
        }));

        $slotsExistentes = $this->disponibilidadModel->listSlotsByTimes($fecha, $horasSinReserva);
        $yaDisponibles = 0;
        $horasParaActualizar = [];
        $existentesMap = [];

        foreach ($slotsExistentes as $slot) {
            $hora = $slot['hora'];
            $existentesMap[$hora] = true;

            if ((int)($slot['activo'] ?? 0) === 1 && ($slot['tipo'] ?? '') === 'disponible') {
                $yaDisponibles++;
                continue;
            }

            $horasParaActualizar[] = $hora;
        }

        $horasParaCrear = array_values(array_filter($horasSinReserva, static function ($hora) use ($existentesMap) {
            return !isset($existentesMap[$hora]);
        }));

        $actualizados = 0;
        $creados = 0;

        try {
            if (!empty($horasParaActualizar)) {
                $actualizados = $this->disponibilidadModel->enableSlots($fecha, $horasParaActualizar, $validation['motivo']);
            }

            if (!empty($horasParaCrear)) {
                $creados = $this->disponibilidadModel->insertSlots($fecha, $horasParaCrear, true, $validation['motivo'], 'disponible');
            }
        } catch (PDOException $e) {
            Response::json([
                'ok' => false,
                'actualizados' => $actualizados,
                'creados' => $creados,
                'omitidos' => $yaDisponibles,
                'con_reserva' => count($conReserva),
                'errores' => ['Error al habilitar disponibilidad masiva'],
                'mensaje' => 'Error al habilitar disponibilidad masiva',
            ], 500);
            return;
        }

        $omitidos = $yaDisponibles + count($horasParaActualizar) - $actualizados + count($horasParaCrear) - $creados;

        Response::json([
            'ok' => true,
            'actualizados' => $actualizados,
            'creados' => $creados,
            'omitidos' => $omitidos,
            'con_reserva' => count($conReserva),
            'mensaje' => 'Horarios habilitados correctamente',
        ]);
    }

    private function updateDisponibilidadDia(array $body, bool $habilitar): void {
        $fecha = $this->normalizeDate($body['fecha'] ?? null);
        if (!$fecha) {
            Response::json([
                'ok' => false,
                'mensaje' => 'fecha valida es requerida',
                'errores' => ['fecha debe tener formato YYYY-MM-DD'],
            ], 400);
            return;
        }

        $slots = $this->disponibilidadModel->listDaySlots($fecha);
        if (empty($slots)) {
            Response::json([
                'ok' => false,
                'actualizados' => 0,
                'omitidos' => 0,
                'con_reserva' => 0,
                'mensaje' => 'No hay horarios creados para este dia',
            ]);
            return;
        }

        $conReserva = 0;
        foreach ($slots as $slot) {
            $ocupada = (int)($slot['ocupada'] ?? 0) === 1;
            if ($ocupada) {
                $conReserva++;
            }
        }

        $actualizados = $this->disponibilidadModel->setDayAvailability($fecha, $habilitar);
        $omitidos = count($slots) - $conReserva - $actualizados;

        Response::json([
            'ok' => true,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'con_reserva' => $conReserva,
            'mensaje' => $habilitar ? 'Dia habilitado correctamente' : 'Dia bloqueado correctamente',
        ]);
    }

    private function updateDisponibilidad(int $id, array $body): void {
        $slot = $this->disponibilidadModel->getById($id);
        if (!$slot) {
            Response::error("Horario no encontrado", 404);
            return;
        }

        $data = [];
        $fecha = array_key_exists('fecha', $body) ? $this->normalizeDate($body['fecha']) : $slot['fecha'];
        $hora = array_key_exists('hora', $body) ? $this->normalizeTime($body['hora']) : $this->normalizeTime($slot['hora']);

        if (!$fecha || !$hora) {
            Response::error("fecha u hora inválida", 400);
            return;
        }

        if (array_key_exists('fecha', $body)) {
            $data['fecha'] = $fecha;
        }
        if (array_key_exists('hora', $body)) {
            $data['hora'] = $hora;
        }
        if (array_key_exists('disponible', $body)) {
            $data['disponible'] = filter_var($body['disponible'], FILTER_VALIDATE_BOOLEAN);
            $data['tipo'] = $data['disponible'] ? 'disponible' : 'bloqueo';
        }
        if (array_key_exists('motivo', $body)) {
            $data['motivo'] = $this->optionalText($body['motivo']);
        }
        if (array_key_exists('tipo', $body)) {
            $tipo = trim((string)$body['tipo']);
            if (!in_array($tipo, ['disponible', 'bloqueo'], true)) {
                Response::error("Tipo inválido", 400);
                return;
            }
            $data['tipo'] = $tipo;
            if (!array_key_exists('disponible', $data)) {
                $data['disponible'] = $tipo === 'disponible';
            }
        }

        $willBeUnavailable = array_key_exists('disponible', $data)
            ? !$data['disponible']
            : (int)($slot['disponible'] ?? 0) !== 1;

        if ($willBeUnavailable && $this->disponibilidadModel->hasActiveBooking($fecha, $hora)) {
            Response::error("No se puede bloquear un horario con reserva activa", 409);
            return;
        }

        $willBeAvailable = array_key_exists('disponible', $data)
            ? (bool)$data['disponible']
            : ((int)($slot['disponible'] ?? 0) === 1 && ($data['tipo'] ?? $slot['tipo'] ?? '') === 'disponible');

        if ($willBeAvailable && $this->disponibilidadModel->hasActiveBooking($fecha, $hora)) {
            Response::json([
                'ok' => false,
                'success' => false,
                'mensaje' => 'Este horario ya fue reservado',
                'message' => 'Este horario ya fue reservado',
                'error' => 'Este horario ya fue reservado',
            ], 409);
            return;
        }

        try {
            $this->disponibilidadModel->updateSlot($id, $data);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::error("Ya existe un horario para esa fecha y hora", 409);
                return;
            }
            throw $e;
        }

        Response::json([
            'success' => true,
            'message' => 'Horario actualizado',
            'data' => $this->disponibilidadModel->getById($id),
        ]);
    }

    private function updateReservaEstado(int $id, array $body): void {
        $estado = trim((string)($body['estado'] ?? ''));
        $motivo = $this->optionalText($body['motivo'] ?? $body['observacion_admin'] ?? null);

        if (!in_array($estado, self::ESTADOS_RESERVA, true)) {
            Response::error("Estado inválido", 400);
            return;
        }

        if (!$this->citaModel->getById($id)) {
            Response::error("Reserva no encontrada", 404);
            return;
        }

        $this->citaModel->updateEstadoAdmin($id, $estado, $motivo);
        Response::json([
            'success' => true,
            'message' => 'Estado de reserva actualizado',
            'data' => $this->citaModel->getById($id),
        ]);
    }

    private function reagendarReserva(int $id, array $body): void {
        $fecha = $this->normalizeDate($body['fecha'] ?? $body['nueva_fecha'] ?? null);
        $hora = $this->normalizeTime($body['hora'] ?? $body['nueva_hora'] ?? null);
        $motivo = $this->optionalText($body['motivo'] ?? $body['observacion_admin'] ?? null);

        if (!$fecha || !$hora) {
            Response::error("fecha y hora válidas son requeridas", 400);
            return;
        }

        $reserva = $this->citaModel->getById($id);
        if (!$reserva) {
            Response::error("Reserva no encontrada", 404);
            return;
        }

        $duracionMin = (int)($reserva['duracion_min'] ?? 30);
        if ($this->citaModel->hasActiveConflict($fecha, $hora, $id, $duracionMin)) {
            Response::error("Ya existe una reserva activa en ese horario", 409);
            return;
        }

        foreach ($this->buildReservaSlots($fecha, $hora, $duracionMin) as $slotHora) {
            if (!$this->disponibilidadModel->isSlotAvailable($fecha, $slotHora, $id)) {
                Response::error("Horario no disponible", 409);
                return;
            }
        }

        $this->citaModel->reagendarAdmin($id, $fecha, $hora, $motivo);
        Response::json([
            'success' => true,
            'message' => 'Reserva reagendada',
            'data' => $this->citaModel->getById($id),
        ]);
    }

    private function normalizeReservaFilters(array $params): array {
        $filters = [
            'page' => $params['page'] ?? 1,
            'limit' => $params['limit'] ?? 10,
            'search' => trim((string)($params['search'] ?? '')),
        ];

        if (!empty($params['estado'])) {
            $estado = trim((string)$params['estado']);
            if (!in_array($estado, self::ESTADOS_RESERVA, true)) {
                return ['error' => 'Estado inválido'];
            }
            $filters['estado'] = $estado;
        }

        foreach (['fecha_desde', 'fecha_hasta'] as $field) {
            if (!empty($params[$field])) {
                $date = $this->normalizeDate($params[$field]);
                if (!$date) {
                    return ['error' => "{$field} inválida"];
                }
                $filters[$field] = $date;
            }
        }

        foreach (['cliente_id', 'servicio_id'] as $field) {
            if (!empty($params[$field])) {
                $id = $this->numericId($params[$field]);
                if (!$id) {
                    return ['error' => "{$field} inválido"];
                }
                $filters[$field] = $id;
            }
        }

        return $filters;
    }

    private function normalizeCalendarioFilters(array $params): array {
        $filters = [];

        foreach (['fecha_desde', 'fecha_hasta'] as $field) {
            if (!empty($params[$field])) {
                $date = $this->normalizeDate($params[$field]);
                if (!$date) {
                    return ['error' => "{$field} inválida"];
                }
                $filters[$field] = $date;
            }
        }

        if (empty($filters['fecha_desde']) && empty($filters['fecha_hasta'])) {
            $filters['fecha_desde'] = date('Y-m-d');
            $filters['fecha_hasta'] = date('Y-m-d', strtotime('+30 days'));
        }

        if (!empty($params['estado'])) {
            $estado = trim((string)$params['estado']);
            if (!in_array($estado, self::ESTADOS_RESERVA, true)) {
                return ['error' => 'Estado inválido'];
            }
            $filters['estado'] = $estado;
        }

        foreach (['servicio_id', 'cliente_id'] as $field) {
            if (!empty($params[$field])) {
                $id = $this->numericId($params[$field]);
                if (!$id) {
                    return ['error' => "{$field} inválido"];
                }
                $filters[$field] = $id;
            }
        }

        return $filters;
    }

    private function normalizeDisponibilidadFilters(array $params): array {
        $filters = [];

        foreach (['fecha_desde', 'fecha_hasta'] as $field) {
            if (!empty($params[$field])) {
                $date = $this->normalizeDate($params[$field]);
                if (!$date) {
                    return ['error' => "{$field} inválida"];
                }
                $filters[$field] = $date;
            }
        }

        if (!empty($params['fecha'])) {
            $date = $this->normalizeDate($params['fecha']);
            if (!$date) {
                return ['error' => 'fecha inválida'];
            }
            $filters['fecha_desde'] = $date;
            $filters['fecha_hasta'] = $date;
        }

        if (!empty($params['tipo'])) {
            $tipo = trim((string)$params['tipo']);
            if (!in_array($tipo, ['disponible', 'bloqueo'], true)) {
                return ['error' => 'Tipo inválido'];
            }
            $filters['tipo'] = $tipo;
        }

        if (array_key_exists('disponible', $params)) {
            $filters['activo'] = filter_var($params['disponible'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        return $filters;
    }

    private function numericId($value): ?int {
        if ($value === null || $value === '' || !ctype_digit((string)$value)) {
            return null;
        }

        $id = (int)$value;
        return $id > 0 ? $id : null;
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

    private function validateDisponibilidadBulk(array $body): array {
        $fecha = $this->normalizeDate($body['fecha'] ?? null);
        if (!$fecha) {
            return ['error' => 'fecha obligatoria en formato YYYY-MM-DD'];
        }

        $horaInicio = $this->normalizeBulkTime($body['hora_inicio'] ?? null);
        if (!$horaInicio) {
            return ['error' => 'hora_inicio obligatoria en formato HH:mm'];
        }

        $horaFin = $this->normalizeBulkTime($body['hora_fin'] ?? null);
        if (!$horaFin) {
            return ['error' => 'hora_fin obligatoria en formato HH:mm'];
        }

        if ($horaFin <= $horaInicio) {
            return ['error' => 'hora_fin debe ser mayor que hora_inicio'];
        }

        $intervalo = (int)($body['intervalo_minutos'] ?? 0);
        if (!in_array($intervalo, [15, 30, 45, 60], true)) {
            return ['error' => 'intervalo_minutos debe ser 15, 30, 45 o 60'];
        }

        $estado = trim((string)($body['estado'] ?? ''));
        if (!in_array($estado, ['disponible', 'bloqueo'], true)) {
            return ['error' => 'estado invalido'];
        }

        return [
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'intervalo_minutos' => $intervalo,
            'estado' => $estado,
            'motivo' => $this->optionalText($body['motivo'] ?? null),
        ];
    }

    private function validateHabilitarDisponibilidadBulk(array $body): array {
        $fecha = $this->normalizeDate($body['fecha'] ?? null);
        $horaInicio = $this->normalizeBulkTime($body['hora_inicio'] ?? null);
        $horaFin = $this->normalizeBulkTime($body['hora_fin'] ?? null);

        if (!$fecha || !$horaInicio || !$horaFin) {
            return ['error' => 'fecha, hora_inicio y hora_fin validas son requeridas'];
        }

        if ($horaFin <= $horaInicio) {
            return ['error' => 'hora_fin debe ser mayor que hora_inicio'];
        }

        $intervalo = (int)($body['intervalo_minutos'] ?? 0);
        if (!in_array($intervalo, [15, 30, 45, 60], true)) {
            return ['error' => 'intervalo_minutos debe ser 15, 30, 45 o 60'];
        }

        return [
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'intervalo_minutos' => $intervalo,
            'motivo' => $this->optionalText($body['motivo'] ?? null),
        ];
    }

    private function normalizeBulkTime($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return null;
        }

        return $value . ':00';
    }

    private function buildBulkSlots(string $horaInicio, string $horaFin, int $intervaloMinutos): array {
        $current = DateTime::createFromFormat('H:i:s', $horaInicio);
        $end = DateTime::createFromFormat('H:i:s', $horaFin);
        if (!$current || !$end) {
            return [];
        }

        $slots = [];
        while ($current < $end) {
            $slots[] = $current->format('H:i:s');
            $current->modify('+' . $intervaloMinutos . ' minutes');
        }

        return $slots;
    }

    private function buildReservaSlots(string $fecha, string $hora, int $duracionMin): array {
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

    private function optionalText($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }
}
