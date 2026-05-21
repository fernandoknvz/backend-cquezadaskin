<?php
require_once __DIR__ . '/../models/PostCareTipModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php'; 


class PostCareTipController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new PostCareTipModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
        AuthGuard::onlyAdminsForMethods($this->pdo, $method);
        try {
            switch ($method) {
                case 'GET':
                    if (isset($params['id'])) {
                        $data = $this->model->getById($params['id']);
                    } else {
                        $data = $this->model->getAll();
                    }
                    Response::json($data);
                    break;

                case 'POST':
                    $id = $this->model->create($body);
                    Response::json(["message" => "Cuidado creado correctamente", "id" => $id]);
                    break;

                case 'PUT':
                case 'PATCH':
                    if (!isset($params['id'])) return Response::error("ID requerido", 400);
                    $this->model->update($params['id'], $body);
                    Response::json(["message" => "Cuidado actualizado correctamente"]);
                    break;

                case 'DELETE':
                    if (!isset($params['id'])) return Response::error("ID requerido", 400);
                    $this->model->delete($params['id']);
                    Response::json(["message" => "Cuidado eliminado correctamente"]);
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
