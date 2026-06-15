<?php

class ImageUpload {
    private const MAX_IMAGE_BYTES = 2097152;
    private const CLOUDINARY_UPLOAD_URL_TEMPLATE = 'https://api.cloudinary.com/v1_1/%s/image/upload';
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function validateImageUpload(?array $file): string {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException("Imagen no recibida");
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException("No se pudo subir la imagen");
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

    public static function uploadImageToCloudinary(string $tmpPath, string $folder, string $publicIdPrefix, ?string $extension = null): string {
        $config = self::cloudinaryConfig($folder);
        $timestamp = time();
        $publicId = self::sanitizePublicId($publicIdPrefix) . '-' . $timestamp;
        $paramsToSign = [
            'folder' => $config['folder'],
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        $signature = self::cloudinarySignature($paramsToSign, $config['api_secret']);
        $url = sprintf(self::CLOUDINARY_UPLOAD_URL_TEMPLATE, rawurlencode($config['cloud_name']));

        if (!function_exists('curl_init')) {
            error_log('Cloudinary upload error | reason=curl_extension_missing');
            throw new Exception("No se pudo subir la imagen a Cloudinary");
        }

        $mime = mime_content_type($tmpPath) ?: ($extension ? 'image/' . $extension : 'application/octet-stream');
        $filename = $publicId . ($extension ? '.' . $extension : '');
        $postFields = [
            'file' => new CURLFile($tmpPath, $mime, $filename),
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
                . ' | body=' . self::sanitizeCloudinaryLog($responseBody)
            );
            throw new Exception("No se pudo subir la imagen a Cloudinary");
        }

        $data = json_decode($responseBody, true);
        $secureUrl = is_array($data) ? trim((string)($data['secure_url'] ?? '')) : '';
        if ($secureUrl === '' || !filter_var($secureUrl, FILTER_VALIDATE_URL)) {
            error_log('Cloudinary upload invalid response | body=' . self::sanitizeCloudinaryLog($responseBody));
            throw new Exception("Respuesta invalida de Cloudinary");
        }

        return $secureUrl;
    }

    private static function cloudinaryConfig(string $folderOverride): array {
        $cloudName = self::env('CLOUDINARY_CLOUD_NAME');
        $apiKey = self::env('CLOUDINARY_API_KEY');
        $apiSecret = self::env('CLOUDINARY_API_SECRET');
        $folder = trim($folderOverride !== '' ? $folderOverride : self::env('CLOUDINARY_FOLDER'), '/');

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

    private static function cloudinarySignature(array $params, string $apiSecret): string {
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

    private static function env(string $key): string {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return trim((string)$value);
        }
        return trim((string)($_ENV[$key] ?? ''));
    }

    private static function sanitizeCloudinaryLog(string $message): string {
        $secret = self::env('CLOUDINARY_API_SECRET');
        if ($secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }

        return substr($message, 0, 1000);
    }

    private static function sanitizePublicId(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?: 'imagen';
        return trim($value, '-') ?: 'imagen';
    }
}
