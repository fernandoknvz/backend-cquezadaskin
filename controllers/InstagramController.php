<?php
require_once __DIR__ . '/../models/InstagramModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';

class InstagramController {
    private $model;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new InstagramModel($pdo);
    }

    /**
     * @param bool $adminContext  true => protege TODO (incluye GET) y retorna todo
     *                           false => GET público solo activos
     */
    public function handleRequest($method, $params, $body, $adminContext = false) {

        // 🔐 AdminContext: TODO requiere admin (incluye GET)
        if ($adminContext) {
            AuthGuard::onlyAdmins($this->pdo, $method);
        } else {
            // Público: solo protegemos mutaciones
            if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
                AuthGuard::onlyAdmins($this->pdo, $method);
            }
        }

        try {
            switch ($method) {
                case 'GET':
                    if (!empty($params['id'])) {
                        $id = (int)$params['id'];
                        // Público: solo devuelve el post si está activo
                        $data = $adminContext
                            ? $this->model->getById($id)
                            : $this->model->getByIdActive($id);
                    } else {
                        // Público: solo activos
                        $data = $adminContext
                            ? $this->model->getAll()
                            : $this->model->getActive();
                    }
                    Response::json($data);
                    break;

                case 'POST':
                    if (empty($body['embed_url'])) {
                        return Response::error("La URL del post es obligatoria", 400);
                    }
                    $id = $this->model->create($body);
                    Response::json([
                        "success" => true,
                        "message" => "Post de Instagram creado correctamente",
                        "id" => $id
                    ]);
                    break;

                case 'PUT':
                    if (empty($params['id'])) {
                        return Response::error("ID requerido para actualizar", 400);
                    }
                    $ok = $this->model->update((int)$params['id'], $body);
                    Response::json([
                        "success" => $ok,
                        "message" => $ok ? "Post actualizado correctamente" : "No se realizaron cambios"
                    ]);
                    break;

                case 'DELETE':
                    if (empty($params['id'])) {
                        return Response::error("ID requerido para eliminar", 400);
                    }
                    $ok = $this->model->delete((int)$params['id']);
                    Response::json([
                        "success" => $ok,
                        "message" => $ok ? "Post eliminado correctamente" : "No se encontró el post para eliminar"
                    ]);
                    break;

                default:
                    Response::error("Método no permitido", 405);
            }
        } catch (Exception $e) {
            Response::error("Error interno del servidor: " . $e->getMessage(), 500);
        }
    }
}