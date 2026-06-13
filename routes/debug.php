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

function debugSmtpFsockopen(string $host, int $port, int $timeoutSeconds = 6): array {
    $errno = 0;
    $errstr = '';
    $start = microtime(true);
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
    $elapsedMs = (int)round((microtime(true) - $start) * 1000);

    if (is_resource($socket)) {
        fclose($socket);
        return ['ok' => true, 'error' => null, 'elapsed_ms' => $elapsedMs];
    }

    return [
        'ok' => false,
        'error' => trim(($errno ? "errno={$errno} " : '') . $errstr),
        'elapsed_ms' => $elapsedMs,
    ];
}

function debugSmtpStreamClient(string $host, int $port, int $timeoutSeconds = 6): array {
    $errno = 0;
    $errstr = '';
    $start = microtime(true);
    $socket = @stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errno,
        $errstr,
        $timeoutSeconds,
        STREAM_CLIENT_CONNECT
    );
    $elapsedMs = (int)round((microtime(true) - $start) * 1000);

    if (is_resource($socket)) {
        fclose($socket);
        return ['ok' => true, 'error' => null, 'elapsed_ms' => $elapsedMs];
    }

    return [
        'ok' => false,
        'error' => trim(($errno ? "errno={$errno} " : '') . $errstr),
        'elapsed_ms' => $elapsedMs,
    ];
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

if ($method === 'GET' && $action === 'smtp-connectivity') {
    $host = 'smtp.gmail.com';
    $dnsRecords = @dns_get_record($host);
    $resolvedIp = @gethostbyname($host);
    $fsock587 = debugSmtpFsockopen($host, 587);
    $fsock465 = debugSmtpFsockopen($host, 465);
    $stream587 = debugSmtpStreamClient($host, 587);
    $stream465 = debugSmtpStreamClient($host, 465);

    Response::json([
        'success' => true,
        'data' => [
            'dns_ok' => is_array($dnsRecords) && count($dnsRecords) > 0,
            'resolved_ip' => ($resolvedIp && $resolvedIp !== $host) ? $resolvedIp : null,
            'port_587_connect' => $fsock587['ok'] || $stream587['ok'],
            'port_465_connect' => $fsock465['ok'] || $stream465['ok'],
            'error_587' => $fsock587['ok'] || $stream587['ok']
                ? null
                : trim('fsockopen: ' . ($fsock587['error'] ?: 'unknown') . ' | stream_socket_client: ' . ($stream587['error'] ?: 'unknown')),
            'error_465' => $fsock465['ok'] || $stream465['ok']
                ? null
                : trim('fsockopen: ' . ($fsock465['error'] ?: 'unknown') . ' | stream_socket_client: ' . ($stream465['error'] ?: 'unknown')),
            'fsockopen_587' => $fsock587,
            'fsockopen_465' => $fsock465,
            'stream_socket_client_587' => $stream587,
            'stream_socket_client_465' => $stream465,
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
