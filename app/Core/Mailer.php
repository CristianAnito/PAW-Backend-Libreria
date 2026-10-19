<?php
declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Envío de correo con PHPMailer.
 *
 * Dos modos (MAIL_DRIVER en .env):
 * - smtp: envía de verdad usando el servidor configurado (MAIL_HOST, MAIL_USER...).
 * - log:  no envía; escribe el mail en storage/logs/app.log. Útil para desarrollo.
 */
final class Mailer
{
    public function __construct(
        private array $config,          // config/mail.php
        private LoggerInterface $logger,
    ) {}

    /**
     * @param string      $to       Destinatario.
     * @param string|null $replyTo  Dirección a la que se responde (ej. el email del cliente).
     * @throws RuntimeException si el envío falla.
     */
    public function send(string $to, string $subject, string $body, ?string $replyTo = null): void
    {
        if (($this->config['driver'] ?? 'smtp') === 'log') {
            $this->logger->info('Mail (modo log, no enviado)', compact('to', 'replyTo', 'subject', 'body'));
            return;
        }

        $mail = new PHPMailer(true); // true: los errores se lanzan como excepción
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->Port = $this->config['port'];
            if (!empty($this->config['user'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['user'];
                $mail->Password = (string) $this->config['pass'];
            }
            if (!empty($this->config['encryption'])) {
                $mail->SMTPSecure = $this->config['encryption'];
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($this->config['from'], $this->config['from_name']);
            $mail->addAddress($to);
            if ($replyTo !== null) {
                $mail->addReplyTo($replyTo);
            }

            $mail->Subject = $subject;
            $mail->Body = $body; // texto plano: no se interpreta HTML del usuario
            $mail->send();

            $this->logger->info('Mail enviado', ['to' => $to, 'subject' => $subject]);
        } catch (PHPMailerException $e) {
            // El detalle (servidor, credenciales) va al log, nunca a la pantalla.
            $this->logger->error('Fallo el envío de mail', ['to' => $to, 'error' => $mail->ErrorInfo]);
            throw new RuntimeException('No se pudo enviar el correo.', 0, $e);
        }
    }
}
