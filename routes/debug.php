<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../utils/AuthGuard.php';
require_once __DIR__ . '/../utils/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
$route = trim((string)($_GET['route'] ?? ''), '/');
$segments = explode('/', $route);
$action = $segments[1] ?? '';

AuthGuard::onlyAdmins($pdo, $method);

function debugMailEnvPresent(string $key): bool {
    $value = mailEnv($key);
    return $value !== null && $value !== false && $value !== '';
}

if ($method === 'GET' && $action === 'mail-env') {
    Response::json([
        'success' => true,
        'data' => [
            'mail_host_present' => debugMailEnvPresent('MAIL_HOST'),
            'mail_port_present' => debugMailEnvPresent('MAIL_PORT'),
            'mail_username_present' => debugMailEnvPresent('MAIL_USERNAME') || debugMailEnvPresent('MAIL_USER'),
            'mail_password_present' => debugMailEnvPresent('MAIL_PASSWORD') || debugMailEnvPresent('MAIL_PASS'),
            'mail_from_present' => debugMailEnvPresent('MAIL_FROM'),
            'mail_from_address_present' => debugMailEnvPresent('MAIL_FROM_ADDRESS'),
            'mail_notify_to_present' => debugMailEnvPresent('MAIL_NOTIFY_TO'),
            'mail_encryption' => mailEnv('MAIL_ENCRYPTION') ?: mailEnv('MAIL_SECURE') ?: null,
            'mail_mailer' => mailEnv('MAIL_MAILER') ?: null,
            'phpmailer_available' => class_exists(\PHPMailer\PHPMailer\PHPMailer::class),
            'app_env' => mailEnv('APP_ENV') ?: null,
        ],
    ]);
    return;
}

if ($method === 'POST' && $action === 'send-test-mail') {
    try {
        $target = mailEnv('MAIL_NOTIFY_TO') ?: mailEnv('MAIL_FROM_ADDRESS') ?: mailEnv('MAIL_FROM');

        if (!$target) {
            mailSetLastResult(false, 'No hay destinatario de diagnostico configurado');
            Response::json([
                'success' => false,
                'message' => 'No hay destinatario de diagnostico configurado',
                'error' => 'MAIL_NOTIFY_TO, MAIL_FROM_ADDRESS o MAIL_FROM no configurado',
                'mail_result' => mailLastResult(),
            ]);
            return;
        }

        $sent = sendMail(
            $target,
            'Diagnostico SMTP - ' . mailBrandName(),
            '<p>Correo de diagnostico temporal de CQuezadaSkin.</p><p>Si recibiste este mensaje, SMTP esta operativo.</p>'
        );

        Response::json([
            'success' => $sent,
            'message' => $sent ? 'Correo de diagnostico enviado' : 'No se pudo enviar correo de diagnostico',
            'mail_result' => mailLastResult(),
        ]);
    } catch (Throwable $e) {
        $safeError = mailSanitizeError($e->getMessage());
        mailSetLastResult(false, 'Error inesperado en diagnostico de correo', ['error' => $safeError]);
        Response::json([
            'success' => false,
            'message' => 'Error inesperado en diagnostico de correo',
            'error' => $safeError,
            'mail_result' => mailLastResult(),
        ]);
    }
    return;
}

Response::error("Ruta debug no encontrada", 404);
