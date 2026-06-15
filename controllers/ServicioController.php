<?php
require_once __DIR__ . '/../models/ServicioModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';
require_once __DIR__ . '/../utils/ImageUpload.php';

class ServicioController {
    private $model;
    private $pdo;
    private const IMAGE_FIELD = 'imagen';
    private const UPLOAD_PUBLIC_DIR = '/uploads/servicios';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new ServicioModel($pdo);
    }

    public function handleRequest($method, $params, $body) {
        try {
            AuthGuard::onlyAdminsForMethods($this->pdo, $method);

            switch ($method) {
                case 'GET':
                    $publicOnly = filter_var($params['public'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if ($publicOnly && !isset($params['id']) && !isset($params['categoria'])) {
                        $data = $this->model->getPublicActive($params['seccion'] ?? null);
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
                    if (trim((string)($body['nombre'] ?? '')) === '') {
                        return Response::error("Nombre de servicio requerido", 400);
                    }
                    if ($this->hasServiceImageUpload()) {
                        $this->validateServiceImageUpload($this->serviceImageFile());
                    }
                    $id = $this->model->create($body);
                    $imagePath = $this->storeServiceImage((int)$id);
                    if ($imagePath !== null) {
                        $this->model->updateImageUrl((int)$id, $imagePath);
                    }
                    Response::json([
                        "message" => "Servicio creado correctamente",
                        "id" => $id,
                        "imagen_url" => $imagePath ?? ($body['imagen_url'] ?? '')
                    ]);
                    break;

                case 'PUT':
                case 'PATCH':
                    if (!isset($params['id'])) {
                        return Response::error("ID requerido", 400);
                    }
                    $imagePath = $this->storeServiceImage((int)$params['id']);
                    if ($imagePath !== null) {
                        $body['imagen_url'] = $imagePath;
                    }
                    $this->model->update($params['id'], $body);
                    Response::json([
                        "message" => "Servicio actualizado correctamente",
                        "imagen_url" => $imagePath
                    ]);
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
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Exception $e) {
            Response::error("Error interno: " . $e->getMessage(), 500);
        }
    }

    private function serviceImageFile(): ?array {
        $file = $_FILES[self::IMAGE_FIELD] ?? $_FILES['imagen_servicio'] ?? null;
        return is_array($file) ? $file : null;
    }

    private function hasServiceImageUpload(): bool {
        $file = $this->serviceImageFile();
        return $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function validateServiceImageUpload(?array $file): string {
        return ImageUpload::validateImageUpload($file);
    }

    private function storeServiceImage(int $serviceId): ?string {
        $file = $this->serviceImageFile();
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $extension = $this->validateServiceImageUpload($file);
        $tmpName = (string)$file['tmp_name'];

        return ImageUpload::uploadImageToCloudinary($tmpName, $this->cloudinaryFolder('servicios'), 'servicio-' . $serviceId, $extension);
    }

    private function cloudinaryFolder(string $suffix): string {
        $base = trim((string)(getenv('CLOUDINARY_FOLDER') ?: ($_ENV['CLOUDINARY_FOLDER'] ?? '')), '/');
        if ($base === '') {
            return '';
        }
        return trim($base . '/' . trim($suffix, '/'), '/');
    }

    private function storeServiceImageLocally(int $serviceId, string $tmpName, string $extension): string {
        $uploadDir = $this->serviceUploadDirectory();
        $this->ensureServiceUploadDirectory($uploadDir);

        $filename = 'servicio-' . $serviceId . '-' . time() . '.' . $extension;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $target)) {
            throw new Exception("No se pudo guardar la imagen del servicio");
        }

        return self::UPLOAD_PUBLIC_DIR . '/' . $filename;
    }

    private function serviceUploadDirectory(): string {
        $root = realpath(__DIR__ . '/..');
        return ($root !== false ? $root : dirname(__DIR__)) . self::UPLOAD_PUBLIC_DIR;
    }

    private function ensureServiceUploadDirectory(string $uploadDir): void {
        $existsBefore = is_dir($uploadDir);
        $mkdirError = null;

        if (!$existsBefore) {
            set_error_handler(static function ($severity, $message) use (&$mkdirError) {
                $mkdirError = $message;
            });
            $created = mkdir($uploadDir, 0775, true);
            restore_error_handler();

            if (!$created && !is_dir($uploadDir)) {
                $this->logUploadDirectoryState($uploadDir, $mkdirError ?: 'mkdir returned false');
                throw new Exception("No se pudo preparar la carpeta de imagenes");
            }
        }

        clearstatcache(true, $uploadDir);
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            $this->logUploadDirectoryState($uploadDir, $mkdirError);
            throw new Exception("No se pudo preparar la carpeta de imagenes");
        }
    }

    private function logUploadDirectoryState(string $uploadDir, ?string $mkdirError = null): void {
        $parent = dirname($uploadDir);
        error_log(
            'Servicio image upload directory error'
            . ' | path=' . $uploadDir
            . ' | exists=' . (is_dir($uploadDir) ? 'yes' : 'no')
            . ' | writable=' . (is_writable($uploadDir) ? 'yes' : 'no')
            . ' | parent=' . $parent
            . ' | parent_exists=' . (is_dir($parent) ? 'yes' : 'no')
            . ' | parent_writable=' . (is_writable($parent) ? 'yes' : 'no')
            . ' | mkdir_error=' . ($mkdirError ?: 'none')
        );
    }
}
