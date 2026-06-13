<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$phpmailerBase = __DIR__ . '/../libs/PHPMailer/src';
$exceptionPath = $phpmailerBase . '/Exception.php';
$phpmailerPath = $phpmailerBase . '/PHPMailer.php';
$smtpPath = $phpmailerBase . '/SMTP.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
if (
    !class_exists(PHPMailer::class)
    && file_exists($exceptionPath)
    && file_exists($phpmailerPath)
    && file_exists($smtpPath)
) {
    require_once $exceptionPath;
    require_once $phpmailerPath;
    require_once $smtpPath;
}
if (!class_exists(PHPMailer::class)) {
    error_log("Mailer Error: PHPMailer no disponible (libs o vendor ausente).");
}

function mailEnv($key, $default = null)
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    return $_ENV[$key] ?? $default;
}

function mailSafe($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function mailRedact($value)
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }

    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        [$user, $domain] = explode('@', $value, 2);
        return substr($user, 0, 2) . '***@' . $domain;
    }

    return substr($value, 0, 3) . '***';
}

function mailLog($message, array $context = [])
{
    $safe = [];
    foreach ($context as $key => $value) {
        if (preg_match('/pass|password|secret|token|key/i', (string)$key)) {
            $safe[$key] = '[redacted]';
        } elseif (preg_match('/mail|correo|email|to|from|user|username/i', (string)$key)) {
            $safe[$key] = mailRedact((string)$value);
        } else {
            $safe[$key] = is_scalar($value) ? (string)$value : '[complex]';
        }
    }

    $pairs = [];
    foreach ($safe as $key => $value) {
        $pairs[] = $key . '=' . $value;
    }

    error_log('Mailer | ' . $message . ($pairs ? ' | ' . implode(' | ', $pairs) : ''));
}

function mailSetLastResult(bool $success, string $message, array $context = [])
{
    $safe = [];
    foreach ($context as $key => $value) {
        if (preg_match('/pass|password|secret|token|key/i', (string)$key)) {
            $safe[$key] = '[redacted]';
        } elseif (preg_match('/mail|correo|email|to|from|user|username/i', (string)$key)) {
            $safe[$key] = mailRedact((string)$value);
        } else {
            $safe[$key] = is_scalar($value) ? (string)$value : '[complex]';
        }
    }

    $GLOBALS['mail_last_result'] = [
        'success' => $success,
        'message' => $message,
        'context' => $safe,
    ];
}

function mailLastResult(): array
{
    return $GLOBALS['mail_last_result'] ?? [
        'success' => null,
        'message' => 'No mail attempt recorded',
        'context' => [],
    ];
}

function mailSanitizeError($message)
{
    $message = (string)$message;
    foreach (['MAIL_PASSWORD', 'MAIL_PASS', 'MAIL_USERNAME', 'MAIL_USER', 'MAIL_FROM_ADDRESS', 'MAIL_FROM'] as $key) {
        $value = mailEnv($key);
        if ($value) {
            $message = str_replace((string)$value, '[redacted]', $message);
        }
    }

    return $message;
}

function mailBrandName()
{
    return mailEnv('BRAND_NAME')
        ?: mailEnv('APP_NAME')
        ?: mailEnv('MAIL_FROM_NAME')
        ?: 'CQuezadaSkin';
}

function formatMailDate($date)
{
    if (!$date) {
        return '';
    }

    $ts = strtotime((string)$date);
    if ($ts === false) {
        return (string)$date;
    }

    return date('d-m-Y', $ts);
}

function formatMailTime($time)
{
    if (!$time) {
        return '';
    }

    $ts = strtotime((string)$time);
    if ($ts === false) {
        return (string)$time;
    }

    return date('H:i', $ts);
}

function buildMailDetailRow($label, $value)
{
    return '
    <tr>
        <td style="padding:10px 12px; border:1px solid #e5e7eb; background:#f9fafb; font-weight:bold; width:180px;">' . mailSafe($label) . '</td>
        <td style="padding:10px 12px; border:1px solid #e5e7eb;">' . mailSafe($value) . '</td>
    </tr>';
}

function buildMailLayout($title, $introHtml, $detailRowsHtml = '', $footerNote = '')
{
    $brand = mailSafe(mailBrandName());
    $replyTo = mailEnv('MAIL_REPLY_TO_ADDRESS') ?: mailEnv('MAIL_REPLY_TO') ?: mailEnv('MAIL_FROM_ADDRESS') ?: mailEnv('MAIL_FROM');

    $footerExtra = $footerNote
        ? '<p style="margin:0 0 8px 0; color:#555; font-size:14px;">' . $footerNote . '</p>'
        : '';

    $replyLine = $replyTo
        ? '<p style="margin:0; color:#777; font-size:12px;">Contacto: ' . mailSafe($replyTo) . '</p>'
        : '';

    return '
    <div style="margin:0; padding:24px; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
            <tr>
                <td style="padding:24px 24px 16px 24px; background:#111827; color:#ffffff;">
                    <h1 style="margin:0; font-size:22px; line-height:1.3;">' . mailSafe($title) . '</h1>
                    <p style="margin:8px 0 0 0; font-size:14px; color:#d1d5db;">' . $brand . '</p>
                </td>
            </tr>
            <tr>
                <td style="padding:24px;">
                    <div style="font-size:15px; line-height:1.6; color:#374151;">
                        ' . $introHtml . '
                    </div>

                    ' . ($detailRowsHtml ? '
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px; border-collapse:collapse;">
                        ' . $detailRowsHtml . '
                    </table>
                    ' : '') . '
                </td>
            </tr>
            <tr>
                <td style="padding:20px 24px; background:#f9fafb; border-top:1px solid #e5e7eb;">
                    ' . $footerExtra . '
                    <p style="margin:0 0 8px 0; color:#111827; font-size:14px; font-weight:bold;">Atentamente,<br>' . $brand . '</p>
                    ' . $replyLine . '
                </td>
            </tr>
        </table>
    </div>';
}

function buildAdminNewBookingMail(array $data)
{
    $nombre = $data['nombre'] ?? '';
    $correo = $data['correo'] ?? '';
    $telefono = $data['telefono'] ?? '';
    $fecha = formatMailDate($data['fecha'] ?? '');
    $hora = formatMailTime($data['hora'] ?? '');
    $duracion = $data['duracion_min'] ?? '';
    $servicio = trim((string)($data['servicio'] ?? ''));
    $servicioId = $data['servicio_id'] ?? '';

    $intro = '
        <p style="margin-top:0;">Se recibió una nueva solicitud de reserva en el sistema de agendamiento.</p>
        <p style="margin-bottom:0;">Revisa la administración para confirmar o gestionar esta cita.</p>
    ';

    $rows = '';
    $rows .= buildMailDetailRow('Cliente', $nombre);
    $rows .= buildMailDetailRow('Correo', $correo);
    $rows .= buildMailDetailRow('Teléfono', $telefono);
    $rows .= buildMailDetailRow('Fecha', $fecha);
    $rows .= buildMailDetailRow('Hora de inicio', $hora);
    $rows .= buildMailDetailRow('Duración', $duracion . ' minutos');

    if ($servicio !== '') {
        $rows .= buildMailDetailRow('Servicio', $servicio);
    } else {
        $rows .= buildMailDetailRow('Servicio ID', $servicioId);
    }

    return buildMailLayout(
        'Nueva solicitud de reserva',
        $intro,
        $rows,
        'Este mensaje fue generado automáticamente por el sistema de agendamiento.'
    );
}

function buildClientBookingReceivedMail(array $data)
{
    $nombre = $data['nombre'] ?? '';
    $fecha = formatMailDate($data['fecha'] ?? '');
    $hora = formatMailTime($data['hora'] ?? '');
    $horaFin = formatMailTime($data['hora_fin'] ?? '');
    $duracion = $data['duracion_min'] ?? '';
    $servicio = trim((string)($data['servicio'] ?? ''));

    $intro = '
        <p style="margin-top:0;">Hola ' . mailSafe($nombre) . ',</p>
        <p>Hemos recibido correctamente tu solicitud de reserva en <strong>' . mailSafe(mailBrandName()) . '</strong>.</p>
        <p>Tu solicitud quedó registrada y será revisada para su confirmación.</p>
        <p style="margin-bottom:0;">Recibirás un nuevo correo cuando tu cita sea confirmada.</p>
    ';

    $rows = '';
    if ($servicio !== '') {
        $rows .= buildMailDetailRow('Servicio', $servicio);
    }

    $rows .= buildMailDetailRow('Fecha', $fecha);

    if ($hora !== '' && $horaFin !== '') {
        $rows .= buildMailDetailRow('Horario', $hora . ' a ' . $horaFin);
    } else {
        $rows .= buildMailDetailRow('Hora', $hora);
    }

    if ($duracion !== '') {
        $rows .= buildMailDetailRow('Duración', $duracion . ' minutos');
    }

    $rows .= buildMailDetailRow('Estado', 'Solicitud recibida');

    return buildMailLayout(
        'Solicitud de reserva recibida',
        $intro,
        $rows,
        'Este correo confirma la recepción de tu solicitud. La reserva quedará lista una vez validada por el equipo.'
    );
}

function buildClientBookingConfirmedMail(array $data)
{
    $nombre = $data['nombre'] ?? '';
    $fecha = formatMailDate($data['fecha'] ?? '');
    $hora = formatMailTime($data['hora'] ?? '');
    $horaFin = formatMailTime($data['hora_fin'] ?? '');
    $duracion = $data['duracion_min'] ?? '';
    $servicio = $data['servicio'] ?? '';

    $intro = '
        <p style="margin-top:0;">Hola ' . mailSafe($nombre) . ',</p>
        <p>Tu cita ha sido <strong>confirmada</strong> correctamente en <strong>' . mailSafe(mailBrandName()) . '</strong>.</p>
        <p>Te compartimos el detalle de tu reserva:</p>
    ';

    $rows = '';
    if ($servicio !== '') {
        $rows .= buildMailDetailRow('Servicio', $servicio);
    }
    $rows .= buildMailDetailRow('Fecha', $fecha);

    if ($hora !== '' && $horaFin !== '') {
        $rows .= buildMailDetailRow('Horario', $hora . ' a ' . $horaFin);
    } else {
        $rows .= buildMailDetailRow('Hora', $hora);
    }

    if ($duracion !== '') {
        $rows .= buildMailDetailRow('Duración', $duracion . ' minutos');
    }

    $rows .= buildMailDetailRow('Estado', 'Cita confirmada');

    return buildMailLayout(
        'Tu cita ha sido confirmada',
        $intro,
        $rows,
        'Si necesitas reagendar, puedes responder este correo.'
    );
}

function buildClientBookingCancelledMail(array $data)
{
    $nombre = $data['nombre'] ?? '';
    $fecha = formatMailDate($data['fecha'] ?? '');
    $hora = formatMailTime($data['hora'] ?? '');
    $horaFin = formatMailTime($data['hora_fin'] ?? '');
    $duracion = $data['duracion_min'] ?? '';
    $servicio = $data['servicio'] ?? '';
    $motivo = trim((string)($data['motivo'] ?? ''));

    $intro = '
        <p style="margin-top:0;">Hola ' . mailSafe($nombre) . ',</p>
        <p>Te informamos que tu cita ha sido <strong>cancelada</strong> en <strong>' . mailSafe(mailBrandName()) . '</strong>.</p>
        <p>A continuación te compartimos el detalle de la reserva cancelada:</p>
    ';

    $rows = '';
    if ($servicio !== '') {
        $rows .= buildMailDetailRow('Servicio', $servicio);
    }

    $rows .= buildMailDetailRow('Fecha', $fecha);

    if ($hora !== '' && $horaFin !== '') {
        $rows .= buildMailDetailRow('Horario', $hora . ' a ' . $horaFin);
    } else {
        $rows .= buildMailDetailRow('Hora', $hora);
    }

    if ($duracion !== '') {
        $rows .= buildMailDetailRow('Duración', $duracion . ' minutos');
    }

    $rows .= buildMailDetailRow('Estado', 'Cita cancelada');

    if ($motivo !== '') {
        $rows .= buildMailDetailRow('Motivo', $motivo);
    }

    return buildMailLayout(
        'Tu cita ha sido cancelada',
        $intro,
        $rows,
        'Si deseas reagendar, puedes responder este correo o comunicarte por nuestros canales de contacto.'
    );
}

function sendMail($to, $subject, $bodyHtml)
{
    mailSetLastResult(false, 'Mail attempt started');
    error_log('MAIL STEP 6 sendMail entered');
    if (!class_exists(PHPMailer::class)) {
        mailLog('PHPMailer no disponible');
        mailSetLastResult(false, 'PHPMailer no disponible');
        return false;
    }

    $host = mailEnv('MAIL_HOST');
    $username = mailEnv('MAIL_USERNAME') ?: mailEnv('MAIL_USER');
    $password = mailEnv('MAIL_PASSWORD') ?: mailEnv('MAIL_PASS');
    $fromAddress = mailEnv('MAIL_FROM_ADDRESS') ?: mailEnv('MAIL_FROM') ?: $username;

    $missing = [];
    foreach (['MAIL_HOST' => $host, 'MAIL_USERNAME/MAIL_USER' => $username, 'MAIL_PASSWORD/MAIL_PASS' => $password, 'MAIL_FROM_ADDRESS/MAIL_FROM' => $fromAddress] as $key => $value) {
        if ($value === null || $value === false || $value === '') {
            $missing[] = $key;
        }
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mailLog('destinatario invalido', ['to' => $to]);
        mailSetLastResult(false, 'Destinatario invalido', ['to' => $to]);
        return false;
    }

    if (!empty($missing)) {
        mailLog('configuracion SMTP incompleta', ['missing' => implode(',', $missing), 'to' => $to]);
        mailSetLastResult(false, 'Configuracion SMTP incompleta', [
            'missing' => implode(',', $missing),
            'to' => $to,
        ]);
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Timeout = (int)(mailEnv('MAIL_TIMEOUT') ?: 8);
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $secure = strtolower((string)(mailEnv('MAIL_ENCRYPTION') ?: mailEnv('MAIL_SECURE')));
        if ($secure === 'ssl' || $secure === 'smtps') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $port = mailEnv('MAIL_PORT');
        $mail->Port = $port ? (int)$port : 465;
        $mail->CharSet = 'UTF-8';

        $fromName = mailEnv('MAIL_FROM_NAME') ?: $fromAddress;
        $replyToAddress = mailEnv('MAIL_REPLY_TO_ADDRESS') ?: mailEnv('MAIL_REPLY_TO') ?: null;
        $replyToName = mailEnv('MAIL_REPLY_TO_NAME') ?: $fromName;

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);

        if ($replyToAddress) {
            $mail->addReplyTo($replyToAddress, $replyToName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = trim(
            preg_replace(
                '/\s+/',
                ' ',
                strip_tags(
                    str_replace(
                        ['<br>', '<br/>', '<br />', '</p>'],
                        ["\n", "\n", "\n", "\n"],
                        $bodyHtml
                    )
                )
            )
        );

        $mail->send();
        mailLog('correo enviado', ['to' => $to, 'from' => $fromAddress, 'host' => $host, 'port' => $mail->Port]);
        mailSetLastResult(true, 'Correo enviado', [
            'to' => $to,
            'from' => $fromAddress,
            'host' => $host,
            'port' => $mail->Port,
        ]);
        return true;
    } catch (Exception $e) {
        $safeError = mailSanitizeError($mail->ErrorInfo ?: $e->getMessage());
        mailLog('error enviando correo', [
            'to' => $to,
            'from' => $fromAddress,
            'host' => $host,
            'port' => $mail->Port,
            'error' => $safeError,
        ]);
        mailSetLastResult(false, 'Error enviando correo', [
            'to' => $to,
            'from' => $fromAddress,
            'host' => $host,
            'port' => $mail->Port,
            'error' => $safeError,
        ]);
        return false;
    }
}
?>
