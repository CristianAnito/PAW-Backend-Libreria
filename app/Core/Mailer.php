<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Mail\{BrevoTransport, LogTransport, MailTransport, SmtpTransport};
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Envío de correo. Elige la forma de entrega según MAIL_DRIVER (.env):
 * - log:   no envía; escribe el mail en storage/logs/app.log (desarrollo).
 * - smtp:  servidor SMTP con PHPMailer (pruebas locales).
 * - brevo: API HTTP de Brevo (producción en Render, que bloquea SMTP en el plan gratis).
 *
 * El remitente (From) es siempre MAIL_FROM, una dirección del sitio autorizada en el proveedor.
 * Nunca se usa el email del cliente como remitente: sería falsificarlo y los servidores de correo
 * lo rechazarían (SPF/DKIM/DMARC). El cliente va en Reply-To.
 */
final class Mailer
{
    private MailTransport $transport;

    public function __construct(
        array $config,                  // config/mail.php
        LoggerInterface $logger,
    ) {
        $this->transport = match ($config['driver'] ?? 'log') {
            'log'   => new LogTransport($logger),
            'smtp'  => new SmtpTransport($config, $logger),
            'brevo' => new BrevoTransport($config, $logger),
            default => throw new RuntimeException("MAIL_DRIVER desconocido: {$config['driver']} (usar log, smtp o brevo)."),
        };
    }

    /**
     * @param string      $to       Destinatario.
     * @param string|null $replyTo  Dirección a la que se responde (ej. el email del cliente).
     * @throws RuntimeException si el envío falla (el detalle queda en el log).
     */
    public function send(string $to, string $subject, string $body, ?string $replyTo = null): void
    {
        $this->transport->send($to, $subject, $body, $replyTo);
    }
}
