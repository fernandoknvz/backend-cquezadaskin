<?php
require_once __DIR__ . '/../models/DisponibilidadModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';

class DisponibilidadController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new DisponibilidadModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            AuthGuard::onlyAdmins($this->pdo, $method);
        }

        try {
            switch ($method) {
                case 'GET':
                    $fecha = $this->normalizeDate($params['fecha'] ?? null);
                    $desde = $this->normalizeDate($params['desde'] ?? null);
                    $hasta = $this->normalizeDate($params['hasta'] ?? null);
                    $modo = strtolower((string)($params['modo'] ?? ''));
                    $includeInactive = filter_var(
                        $params['include_inactive'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    );

                    if ($fecha) {
                        $minHora = null;
                                    
                        // Regla operativa: para hoy, solo mostrar horas con al menos 2 horas de anticipación
                        if ($fecha === date('Y-m-d')) {
                            $minHora = date('H:i:s', time() + (2 * 60 * 60));
                        }
                                    
                        $horas = $this->model->getAvailableTimesByDate($fecha, $minHora);
                                    
                        Response::json([
                            "fecha" => $fecha,
                            "horas" => $horas
                        ]);
                        break;
                    }

                    if ($desde && $hasta) {
                        if ($modo === 'dias') {
                            $dias = $this->model->getAvailableDaysByRange($desde, $hasta);
                            Response::json($dias);
                        } else {
                            $slots = $this->model->listSlotsByRange($desde, $hasta, $includeInactive);
                            Response::json($slots);
                        }
                        break;
                    }

                    Response::error("Parametros invalidos para disponibilidad", 400);
                    break;

                case 'POST':
                    $fechas = $this->normalizeDates($body['fechas'] ?? []);
                    $horas = $this->normalizeTimes($body['horas'] ?? []);
                    if (empty($fechas) || empty($horas)) {
                        return Response::error("Fechas y horas requeridas", 400);
                    }
                    $count = $this->model->createBulk($fechas, $horas);
                    Response::json([
                        "message" => "Horarios habilitados",
                        "total" => $count
                    ]);
                    break;

                case 'DELETE':
                    $fechas = $this->normalizeDates($body['fechas'] ?? []);
                    $horas = $this->normalizeTimes($body['horas'] ?? []);
                    if (empty($fechas) || empty($horas)) {
                        return Response::error("Fechas y horas requeridas", 400);
                    }
                    $count = $this->model->deleteBulk($fechas, $horas);
                    Response::json([
                        "message" => "Horarios bloqueados",
                        "total" => $count
                    ]);
                    break;

                default:
                    Response::error("Metodo no permitido", 405);
                    break;
            }
        } catch (Exception $e) {
            Response::error("Error interno: " . $e->getMessage(), 500);
        }
    }

    private function normalizeDate($value) {
        if (!$value || !is_string($value)) {
            return null;
        }
        $value = trim($value);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value) {
            return $value;
        }
        return null;
    }

    private function normalizeDates($values): array {
        if (!is_array($values)) {
            return [];
        }
        $result = [];
        foreach ($values as $value) {
            $date = $this->normalizeDate($value);
            if ($date) {
                $result[] = $date;
            }
        }
        return array_values(array_unique($result));
    }

    private function normalizeTimes($values): array {
        if (!is_array($values)) {
            return [];
        }
        $result = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            $time = DateTime::createFromFormat('H:i', $value)
                ?: DateTime::createFromFormat('H:i:s', $value);
            if ($time) {
                $result[] = $time->format('H:i:s');
            }
        }
        return array_values(array_unique($result));
    }
}
