<?php
require_once __DIR__ . '/../models/HomeContentModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';
require_once __DIR__ . '/../utils/Url.php';
require_once __DIR__ . '/../utils/ImageUpload.php';

class HomeContentController {
    private $model;
    private $pdo;
    private const IMAGE_FIELD = 'imagen';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new HomeContentModel($pdo);
    }

    /**
     * Normaliza imagen_url para que si viene relativa (ej: /public/img/banner.jpg)
     * se devuelva absoluta con ASSET_BASE_URL.
     */
    private function normalizeImageUrl($data) {
        if (is_array($data)) {
            // Lista
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data as &$row) {
                    if (isset($row['imagen_url'])) {
                        $row['imagen_url'] = Url::normalizeAssetUrl((string)$row['imagen_url']);
                    }
                }
                unset($row);
                return $data;
            }

            // Objeto (getById)
            if (isset($data['imagen_url'])) {
                $data['imagen_url'] = Url::normalizeAssetUrl((string)$data['imagen_url']);
            }
        }

        return $data;
    }

    public function handleRequest($method, $params, $body) {
        AuthGuard::onlyAdminsForMethods($this->pdo, $method);

        try {
            switch ($method) {
                case 'GET':
                    if (!empty($params['id'])) {
                        $data = $this->model->getById((int)$params['id']);
                    } else {
                        $data = $this->model->getAll();
                    }

                    $data = $this->normalizeImageUrl($data);
                    Response::json($data);
                    break;

                case 'POST':
                    $imageUrl = $this->storeHomeImage('hero-home');
                    if ($imageUrl !== null) {
                        $body['imagen_url'] = $imageUrl;
                    }
                    $id = $this->model->create($body);
                    Response::json([
                        "success" => true,
                        "message" => "Contenido creado correctamente",
                        "id" => $id,
                        "imagen_url" => $imageUrl ?? ($body['imagen_url'] ?? '')
                    ]);
                    break;

                case 'PUT':
                case 'PATCH':
                    if (empty($params['id'])) {
                        return Response::error("ID requerido para actualizar", 400);
                    }
                    $imageUrl = $this->storeHomeImage('hero-home-' . (int)$params['id']);
                    if ($imageUrl !== null) {
                        $body['imagen_url'] = $imageUrl;
                    }
                    $ok = $this->model->update((int)$params['id'], $body);
                    Response::json([
                        "success" => $ok,
                        "message" => $ok ? "Contenido actualizado correctamente" : "No se realizaron cambios",
                        "imagen_url" => $imageUrl
                    ]);
                    break;

                case 'DELETE':
                    if (empty($params['id'])) {
                        return Response::error("ID requerido para eliminar", 400);
                    }
                    $ok = $this->model->delete((int)$params['id']);
                    Response::json([
                        "success" => $ok,
                        "message" => $ok ? "Contenido eliminado correctamente" : "No se encontro el contenido"
                    ]);
                    break;

                default:
                    Response::error("Metodo no permitido", 405);
            }
        } catch (Exception $e) {
            Response::error("Error interno del servidor: " . $e->getMessage(), 500);
        }
    }

    private function homeImageFile(): ?array {
        $file = $_FILES[self::IMAGE_FIELD] ?? $_FILES['hero_image'] ?? null;
        return is_array($file) ? $file : null;
    }

    private function storeHomeImage(string $publicIdPrefix): ?string {
        $file = $this->homeImageFile();
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $extension = ImageUpload::validateImageUpload($file);
        return ImageUpload::uploadImageToCloudinary(
            (string)$file['tmp_name'],
            $this->cloudinaryFolder('home'),
            $publicIdPrefix,
            $extension
        );
    }

    private function cloudinaryFolder(string $suffix): string {
        $base = trim((string)(getenv('CLOUDINARY_FOLDER') ?: ($_ENV['CLOUDINARY_FOLDER'] ?? '')), '/');
        if ($base === '') {
            return '';
        }
        return trim($base . '/' . trim($suffix, '/'), '/');
    }
}
?>
