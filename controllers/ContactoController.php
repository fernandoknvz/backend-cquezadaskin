<?php
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../config/mailer.php';

class ContactoController {
    private const INTERNAL_ERROR = 'No pudimos enviar tu consulta en este momento. Intenta nuevamente mas tarde.';

    public function create(array $body): void {
        $data = [
            'asunto' => $this->stringValue($body['asunto'] ?? ''),
            'nombre' => $this->stringValue($body['nombre'] ?? ''),
            'email' => strtolower($this->stringValue($body['email'] ?? '')),
            'telefono' => $this->stringValue($body['telefono'] ?? ''),
            'mensaje' => $this->stringValue($body['mensaje'] ?? ''),
        ];

        $error = $this->validate($data);
        if ($error !== null) {
            Response::json([
                'success' => false,
                'message' => $error,
            ], 400);
            return;
        }

        $notifyTo = mailEnv('MAIL_NOTIFY_TO') ?: 'info.cquezadaskin@gmail.com';
        if (!filter_var($notifyTo, FILTER_VALIDATE_EMAIL)) {
            error_log('Contacto Error: destinatario invalido para consulta web.');
            Response::json([
                'success' => false,
                'message' => self::INTERNAL_ERROR,
            ], 500);
            return;
        }

        $sent = sendMail(
            $notifyTo,
            'Nueva consulta web - CQUEZADASKIN',
            $this->buildContactMail($data),
            $data['email'],
            $data['nombre']
        );

        if (!$sent) {
            error_log('Contacto Error: no se pudo enviar consulta web. result=' . json_encode(mailLastResult(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            Response::json([
                'success' => false,
                'message' => self::INTERNAL_ERROR,
            ], 500);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'Consulta enviada correctamente.',
        ]);
    }

    private function validate(array $data): ?string {
        $required = [
            'asunto' => 'Ingresa el motivo de consulta.',
            'nombre' => 'Ingresa tu nombre.',
            'email' => 'Ingresa tu correo.',
            'mensaje' => 'Ingresa el detalle de tu consulta.',
        ];

        foreach ($required as $field => $message) {
            if ($data[$field] === '') {
                return $message;
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Ingresa un correo valido.';
        }

        $limits = [
            'asunto' => 150,
            'nombre' => 120,
            'email' => 180,
            'telefono' => 40,
            'mensaje' => 2000,
        ];

        foreach ($limits as $field => $limit) {
            if (strlen($data[$field]) > $limit) {
                return 'El campo ' . $field . ' supera el largo maximo permitido.';
            }
        }

        return null;
    }

    private function buildContactMail(array $data): string {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Santiago'));
        $rows = '';
        $rows .= buildMailDetailRow('Motivo de consulta', $data['asunto']);
        $rows .= buildMailDetailRow('Nombre', $data['nombre']);
        $rows .= buildMailDetailRow('Email', $data['email']);
        $rows .= buildMailDetailRow('Telefono', $data['telefono'] !== '' ? $data['telefono'] : 'No informado');
        $rows .= buildMailDetailRow('Fecha/hora', $now->format('d-m-Y H:i'));
        $rows .= buildMailDetailRow('Origen', 'formulario web cquezadaskin.cl');
        $rows .= $this->buildMessageRow('Mensaje', $data['mensaje']);

        return buildMailLayout(
            'Nueva consulta web',
            '<p style="margin-top:0;">Se recibio una nueva consulta desde el formulario publico del sitio.</p>',
            $rows,
            'Responde este correo para contactar directamente al cliente.'
        );
    }

    private function buildMessageRow(string $label, string $value): string {
        return '
    <tr>
        <td style="padding:10px 12px; border:1px solid #e5e7eb; background:#f9fafb; font-weight:bold; width:180px;">' . mailSafe($label) . '</td>
        <td style="padding:10px 12px; border:1px solid #e5e7eb; white-space:pre-line;">' . nl2br(mailSafe($value)) . '</td>
    </tr>';
    }

    private function stringValue($value): string {
        return trim((string)$value);
    }
}
