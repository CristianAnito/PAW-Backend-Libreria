<?php
declare(strict_types=1);

namespace App\Core\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Envío por SMTP con PHPMailer (MAIL_HOST, MAIL_PORT, MAIL_USER...).
 * Sirve para probar en local. En el plan gratis de Render no funciona: bloquea los puertos SMTP.
 */
final class SmtpTransport implements MailTransport
{
    public function __construct(
        private array $config,          // config/mail.php
        private LoggerInterface $logger,
    ) {}

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): void
    {
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
        } catch (PHPMailerException $e) {
            // El detalle (servidor, error) va al log, nunca a la pantalla.
            $this->logger->error('Falló el envío de mail por SMTP', ['to' => $to, 'error' => $mail->ErrorInfo]);
            throw new RuntimeException('No se pudo enviar el correo.', 0, $e);
        }
    }
}
