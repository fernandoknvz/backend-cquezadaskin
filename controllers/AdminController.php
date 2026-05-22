<?php
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../utils/Response.php';

class AdminController {
    private ClienteModel $clienteModel;
    private CitaModel $citaModel;

    private const ESTADOS_RESERVA = ['solicitada', 'pendiente', 'confirmada', 'cancelada', 'completada', 'reagendada'];

    public function __construct($pdo) {
        $this->clienteModel = new ClienteModel($pdo);
        $this->citaModel = new CitaModel($pdo);
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

        if (!$this->citaModel->getById($id)) {
            Response::error("Reserva no encontrada", 404);
            return;
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

    private function optionalText($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }
}
