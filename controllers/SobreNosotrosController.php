<?php
require_once __DIR__ . '/../models/SobreNosotrosModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php'; // 👈 nuevo include


class SobreNosotrosController {
    private $model;
    private $pdo; // 👈 guardamos el pdo para el AuthGuard

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new SobreNosotrosModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
           // 🔒 Protección: solo los admins pueden crear, editar o eliminar testimonios
        AuthGuard::onlyAdminsForMethods($this->pdo, $method);
        try {
            switch ($method) {
                case 'GET':
                    $data = $this->model->getActivo();
                    Response::json($data);
                    break;

                case 'POST':
                    $id = $this->model->create($body);
                    Response::json(["message" => "Contenido creado", "id" => $id]);
                    break;

                case 'PUT':
                case 'PATCH':
                    if (!isset($params['id'])) return Response::error("ID requerido", 400);
                    $this->model->update($params['id'], $body);
                    Response::json(["message" => "Contenido actualizado"]);
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
