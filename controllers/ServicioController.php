<?php
require_once __DIR__ . '/../models/ServicioModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';

class ServicioController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new ServicioModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
        try {
            if ($method !== 'GET') {
                AuthGuard::onlyAdmins($this->pdo, $method);
            }

            switch ($method) {
                case 'GET':
                    $publicOnly = filter_var($params['public'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if ($publicOnly && !isset($params['id']) && !isset($params['categoria'])) {
                        $data = $this->model->getPublicActive();
                    } elseif (isset($params['id'])) {
                        $data = $this->model->getById($params['id']);
                    } elseif (isset($params['categoria'])) {
                        $data = $this->model->getByCategoria($params['categoria']);
                    } else {
                        $data = $this->model->getAll();
                    }
                    Response::json($data);
                    break;

                case 'POST':
                    $id = $this->model->create($body);
                    Response::json([
                        "message" => "Servicio creado correctamente",
                        "id" => $id
                    ]);
                    break;

                case 'PUT':
                    if (!isset($params['id'])) {
                        return Response::error("ID requerido", 400);
                    }
                    $this->model->update($params['id'], $body);
                    Response::json(["message" => "Servicio actualizado correctamente"]);
                    break;

                case 'DELETE':
                    if (!isset($params['id'])) {
                        return Response::error("ID requerido", 400);
                    }
                    $this->model->delete($params['id']);
                    Response::json(["message" => "Servicio eliminado correctamente"]);
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
