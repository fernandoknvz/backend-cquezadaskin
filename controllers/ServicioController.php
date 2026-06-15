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
    private const CLOUDINARY_UPLOAD_URL_TEMPLATE = 'https://api.cloudinary.com/v1_1/%s/image/upload';
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

        return $this->uploadServiceImageToCloudinary($serviceId, $tmpName, $extension);
    }

    private function uploadServiceImageToCloudinary(int $serviceId, string $tmpName, string $extension): string {
        $config = $this->cloudinaryConfig();
        $timestamp = time();
        $publicId = 'servicio-' . $serviceId . '-' . $timestamp;
        $paramsToSign = [
            'folder' => $config['folder'],
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        $signature = $this->cloudinarySignature($paramsToSign, $config['api_secret']);
        $url = sprintf(self::CLOUDINARY_UPLOAD_URL_TEMPLATE, rawurlencode($config['cloud_name']));

        if (!function_exists('curl_init')) {
            error_log('Cloudinary upload error | reason=curl_extension_missing');
            throw new Exception("No se pudo subir la imagen a Cloudinary");
        }

        $postFields = [
            'file' => new CURLFile($tmpName, mime_content_type($tmpName) ?: 'image/' . $extension, 'servicio.' . $extension),
            'api_key' => $config['api_key'],
            'timestamp' => (string)$timestamp,
            'folder' => $config['folder'],
            'public_id' => $publicId,
            'signature' => $signature,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => $postFields,
        ]);

        $responseBody = (string)curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === '' || $statusCode < 200 || $statusCode >= 300) {
            error_log(
                'Cloudinary upload HTTP error'
                . ' | status=' . $statusCode
                . ' | curl_error=' . ($curlError ?: 'none')
                . ' | body=' . $this->sanitizeCloudinaryLog($responseBody)
            );
            throw new Exception("No se pudo subir la imagen a Cloudinary");
        }

        $data = json_decode($responseBody, true);
        $secureUrl = is_array($data) ? trim((string)($data['secure_url'] ?? '')) : '';
        if ($secureUrl === '' || !filter_var($secureUrl, FILTER_VALIDATE_URL)) {
            error_log('Cloudinary upload invalid response | body=' . $this->sanitizeCloudinaryLog($responseBody));
            throw new Exception("Respuesta invalida de Cloudinary");
        }

        return $secureUrl;
    }

    private function cloudinaryConfig(): array {
        $cloudName = $this->env('CLOUDINARY_CLOUD_NAME');
        $apiKey = $this->env('CLOUDINARY_API_KEY');
        $apiSecret = $this->env('CLOUDINARY_API_SECRET');
        $folder = trim($this->env('CLOUDINARY_FOLDER'), '/');

        $missing = [];
        if ($cloudName === '') {
            $missing[] = 'CLOUDINARY_CLOUD_NAME';
            error_log('Cloudinary config missing | key=CLOUDINARY_CLOUD_NAME');
        }
        if ($apiKey === '') {
            $missing[] = 'CLOUDINARY_API_KEY';
            error_log('Cloudinary config missing | key=CLOUDINARY_API_KEY');
        }
        if ($apiSecret === '') {
            $missing[] = 'CLOUDINARY_API_SECRET';
            error_log('Cloudinary config missing | key=CLOUDINARY_API_SECRET');
        }
        if ($folder === '') {
            $missing[] = 'CLOUDINARY_FOLDER';
            error_log('Cloudinary config missing | key=CLOUDINARY_FOLDER');
        }

        if (!empty($missing)) {
            throw new Exception('Falta configuracion Cloudinary: ' . implode(', ', $missing));
        }

        return [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'folder' => $folder,
        ];
    }

    private function cloudinarySignature(array $params, string $apiSecret): string {
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $pairs[] = $key . '=' . $value;
        }

        return sha1(implode('&', $pairs) . $apiSecret);
    }

    private function env(string $key): string {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return trim((string)$value);
        }
        return trim((string)($_ENV[$key] ?? ''));
    }

    private function sanitizeCloudinaryLog(string $message): string {
        $secret = $this->env('CLOUDINARY_API_SECRET');
        if ($secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }

        return substr($message, 0, 1000);
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
