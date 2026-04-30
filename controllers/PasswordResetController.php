<?php
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../models/PasswordResetModel.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/mailer.php';

class PasswordResetController {
    private $userModel;
    private $resetModel;

    public function __construct($pdo) {
        $this->userModel = new UsuarioModel($pdo);
        $this->resetModel = new PasswordResetModel($pdo);
    }

    private function envValue($key, $default = null) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        return $_ENV[$key] ?? $default;
    }

    private function buildResetUrl($token) {
        $frontendUrl = $this->envValue('FRONTEND_URL');
        if (!$frontendUrl) {
            $frontendUrl = $this->envValue('APP_URL');
        }

        if (!$frontendUrl) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($origin) {
                $frontendUrl = $origin;
            } else {
                $host = $_SERVER['HTTP_HOST'] ?? '';
                if ($host) {
                    $frontendUrl = 'https://' . $host;
                }
            }
        }

        $frontendUrl = rtrim((string)$frontendUrl, '/');
        return $frontendUrl ? $frontendUrl . '/reset-password?token=' . $token : '';
    }

    public function requestReset($body) {
        $email = trim((string)($body['email'] ?? ''));
        if ($email === '') {
            return Response::error("Correo requerido", 400);
        }

        $user = $this->userModel->getByEmail($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $this->resetModel->create($user['id'], $tokenHash, $expiresAt);

            $resetUrl = $this->buildResetUrl($token);
            $subject = 'Restablecer contraseÃ±a';
            $bodyHtml = '<p>Hola,</p>'
                . '<p>Recibimos una solicitud para restablecer tu contraseÃ±a.</p>'
                . ($resetUrl ? '<p><a href="' . $resetUrl . '">Crear nueva contraseÃ±a</a></p>' : '')
                . '<p>Si no solicitaste este cambio, puedes ignorar este correo.</p>';

            $sent = sendMail($email, $subject, $bodyHtml);
            if (!$sent) {
                error_log("Mailer Error: no se pudo enviar el correo de recuperaciÃ³n.");
            }
        }

        Response::json([
            'message' => 'Si el correo existe, enviaremos instrucciones para restablecer la contraseÃ±a.'
        ]);
    }

    public function resetPassword($body) {
        $token = trim((string)($body['token'] ?? ''));
        $password = trim((string)($body['password'] ?? ''));

        if ($token === '' || $password === '') {
            return Response::error("Token y contraseÃ±a requeridos", 400);
        }

        if (strlen($password) < 6) {
            return Response::error("La contraseÃ±a debe tener al menos 6 caracteres", 400);
        }

        $tokenHash = hash('sha256', $token);
        $record = $this->resetModel->findValid($tokenHash);
        if (!$record) {
            return Response::error("Token invÃ¡lido o expirado", 400);
        }

        $this->userModel->updatePassword($record['user_id'], $password);
        $this->resetModel->markUsed($record['id']);

        Response::json([
            'message' => 'ContraseÃ±a actualizada correctamente'
        ]);
    }
}
