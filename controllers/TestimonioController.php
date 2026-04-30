<?php
require_once __DIR__ . '/../models/TestimonioModel.php';
require_once __DIR__ . '/../utils/Response.php';

class TestimonioController {
    private $model;

    public function __construct($pdo) {
        $this->model = new TestimonioModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
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
                    Response::json(["message" => "Testimonio creado", "id" => $id]);
                    break;

                case 'PUT':
                    if (!isset($params['id'])) return Response::error("ID requerido");
                    $this->model->update($params['id'], $body);
                    Response::json(["message" => "Testimonio actualizado"]);
                    break;

                case 'DELETE':
                    if (!isset($params['id'])) return Response::error("ID requerido");
                    $this->model->delete($params['id']);
                    Response::json(["message" => "Testimonio eliminado"]);
                    break;

                default:
                    Response::error("Método no permitido", 405);
            }
        } catch (Exception $e) {
            Response::error("Error: " . $e->getMessage(), 500);
        }
    }
}
