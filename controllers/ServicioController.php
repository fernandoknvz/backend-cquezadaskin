<?php
require_once __DIR__ . '/../models/ServicioModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthGuard.php';

class ServicioController {
    private $model;
    private $pdo;
    private const IMAGE_FIELD = 'imagen';
    private const MAX_IMAGE_BYTES = 2097152;
    private const UPLOAD_PUBLIC_DIR = '/uploads/servicios';
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

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
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException("Imagen no recibida");
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException("No se pudo subir la imagen del servicio");
        }

        if (($file['size'] ?? 0) <= 0 || (int)$file['size'] > self::MAX_IMAGE_BYTES) {
            throw new InvalidArgumentException("La imagen debe pesar maximo 2 MB");
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException("Imagen invalida");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmpName);
        if (!isset(self::ALLOWED_IMAGE_TYPES[$mime])) {
            throw new InvalidArgumentException("Formato de imagen no permitido. Usa JPG, JPEG, PNG o WEBP");
        }

        $originalExt = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($originalExt === 'jpeg') {
            $originalExt = 'jpg';
        }
        $extension = self::ALLOWED_IMAGE_TYPES[$mime];
        if ($originalExt !== '' && $originalExt !== $extension) {
            throw new InvalidArgumentException("La extension del archivo no coincide con el tipo de imagen");
        }

        return $extension;
    }

    private function storeServiceImage(int $serviceId): ?string {
        $file = $this->serviceImageFile();
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $extension = $this->validateServiceImageUpload($file);
        $tmpName = (string)$file['tmp_name'];

        $uploadDir = dirname(__DIR__) . self::UPLOAD_PUBLIC_DIR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new Exception("No se pudo preparar la carpeta de imagenes");
        }

        $filename = 'servicio-' . $serviceId . '-' . time() . '.' . $extension;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $target)) {
            throw new Exception("No se pudo guardar la imagen del servicio");
        }

        return self::UPLOAD_PUBLIC_DIR . '/' . $filename;
    }
}
