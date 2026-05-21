<?php
require_once __DIR__ . '/../models/CategoriaModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php'; // 👈 nuevo include

class CategoriaController {
    private $model;
    private $pdo; // 👈 guardamos referencia a la conexión

    public function __construct($pdo) {
        $this->model = new CategoriaModel($pdo);
        $this->pdo = $pdo;
    }

    public function handleRequest($method, $params, $body) {
        // 🔒 Protección de rutas administrativas
        // Permite GET público, pero requiere token para POST, PUT, DELETE
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
                    if (trim((string)($body['nombre'] ?? '')) === '') {
                        return Response::error("Nombre de categoría requerido", 400);
                    }
                    $id = $this->model->create($body);
                    Response::json([
                        "message" => "Categoría creada correctamente",
                        "id" => $id
                    ]);
                    break;

                case 'PUT':
                case 'PATCH':
                    if (!isset($params['id'])) 
                        return Response::error("ID requerido", 400);

                    $this->model->update($params['id'], $body);
                    Response::json(["message" => "Categoría actualizada correctamente"]);
                    break;

                case 'DELETE':
                    if (!isset($params['id'])) 
                        return Response::error("ID requerido", 400);

                    $this->model->delete($params['id']);
                    Response::json(["message" => "Categoría eliminada correctamente"]);
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
