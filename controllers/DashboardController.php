<?php
require_once __DIR__ . '/../models/DashboardModel.php';
require_once __DIR__ . '/../utils/Response.php';

class DashboardController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new DashboardModel($pdo);
    }

    /**
     * Maneja las solicitudes al dashboard.
     * Soporta:
     *  - GET /api/dashboard
     *  - GET /api/dashboard/overview
     *  - GET /api/dashboard/citas-hoy
     *  - GET /api/dashboard/top-servicios
     */
    public function handleRequest($method, $params) {
        try {
            // ✅ Solo permite método GET
            if ($method !== 'GET') {
                Response::error("Método no permitido", 405);
                return;
            }

            // ✅ Determina el sub-endpoint solicitado
            $endpoint = isset($params['endpoint']) ? trim($params['endpoint']) : '';

            // ✅ Enrutamiento interno
            switch ($endpoint) {
                case '':
                case null:
                case 'overview':
                    $data = $this->model->getOverview();
                    break;

                case 'citas-hoy':
                    $data = $this->model->getCitasHoy();
                    break;

                case 'top-servicios':
                    $data = $this->model->getTopServicios();
                    break;

                default:
                    Response::error("Endpoint no válido: '$endpoint'", 404);
                    return;
            }

            // ✅ Respuesta exitosa
            Response::json($data);

        } catch (PDOException $e) {
            // Errores SQL explícitos
            error_log("❌ DashboardController SQL Error: " . $e->getMessage());
            Response::error("Error de base de datos: " . $e->getMessage(), 500);

        } catch (Exception $e) {
            // Errores generales
            error_log("❌ DashboardController Error: " . $e->getMessage());
            Response::error("Error interno del servidor: " . $e->getMessage(), 500);
        }
    }
}
