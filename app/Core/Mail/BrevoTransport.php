<?php
declare(strict_types=1);

namespace App\Core\Mail;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Envío con la API HTTP de Brevo (https://developers.brevo.com/reference/sendtransacemail).
 *
 * En lugar de hablar SMTP (puertos 25/465/587, bloqueados en el plan gratis de Render),
 * hace un POST por HTTPS (puerto 443) con el mail en JSON, como un fetch.
 *
 * Requisitos en Brevo: una API key (BREVO_API_KEY) y que MAIL_FROM sea un remitente
 * verificado en la cuenta (Senders). Plan gratis: 300 mails por día.
 */
final class BrevoTransport implements MailTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private array $config,          // config/mail.php
        private LoggerInterface $logger,
    ) {
        if (empty($config['brevo_api_key'])) {
            throw new RuntimeException('Falta BREVO_API_KEY para enviar mails con MAIL_DRIVER=brevo.');
        }
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): void
    {
        $payload = [
            'sender'      => ['name' => $this->config['from_name'], 'email' => $this->config['from']],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'textContent' => $body,   // texto plano: no se interpreta HTML del usuario
        ];
        if ($replyTo !== null) {
            $payload['replyTo'] = ['email' => $replyTo];
        }

        $curl = curl_init(self::ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $this->config['brevo_api_key'],   // la clave nunca se registra en el log
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,    // si Brevo no responde, el usuario no espera para siempre
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $networkError = curl_error($curl);

        // 201 Created: Brevo aceptó el mail para enviarlo.
        if ($response === false || $status < 200 || $status >= 300) {
            // Brevo explica el problema en {"code": "...", "message": "..."} (ej. clave inválida,
            // remitente no verificado, límite diario). Va al log; al usuario, un mensaje genérico.
            $error = is_string($response) ? json_decode($response, true) : null;
            $this->logger->error('Falló el envío de mail con Brevo', [
                'to'     => $to,
                'status' => $status,
                'error'  => $networkError !== '' ? $networkError : ($error['message'] ?? 'sin detalle'),
                'code'   => $error['code'] ?? null,
            ]);
            throw new RuntimeException('No se pudo enviar el correo.');
        }

        $messageId = json_decode((string) $response, true)['messageId'] ?? null;
        $this->logger->info('Mail enviado con Brevo', ['to' => $to, 'subject' => $subject, 'messageId' => $messageId]);
    }
}
