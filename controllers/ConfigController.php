<?php
require_once __DIR__ . '/../models/ConfigModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';

class ConfigController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo; // ✅ FALTABA ESTO
        $this->model = new ConfigModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
        // Verificación de seguridad (solo admins pueden modificar)
        AuthGuard::onlyAdminsForMethods($this->pdo, $method);

        try {
            switch ($method) {
                case 'GET':
                    $data = $this->model->getConfig();
                    Response::json($data);
                    break;

                case 'POST':
                case 'PUT':
                case 'PATCH':
                    if (empty($body)) {
                        return Response::error("Datos de configuración requeridos", 400);
                    }
                    $this->model->updateConfig($body);
                    Response::json(["message" => "Configuración actualizada correctamente"]);
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
