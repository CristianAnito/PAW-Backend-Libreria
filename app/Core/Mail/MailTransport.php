<?php
declare(strict_types=1);

namespace App\Core\Mail;

use RuntimeException;

/**
 * Forma de entregar un mail (patrón Strategy). Mailer elige una según MAIL_DRIVER:
 * - log:   no envía; lo escribe en el log (desarrollo).
 * - smtp:  servidor SMTP con PHPMailer (pruebas locales; Render gratis bloquea SMTP).
 * - brevo: API HTTP de Brevo (producción: viaja por HTTPS, que Render no bloquea).
 *
 * Quien envía el mail (CheckoutController) no sabe cuál se usa: cambiar de proveedor
 * es agregar una clase que implemente esta interfaz.
 */
interface MailTransport
{
    /**
     * @param string      $to      Destinatario.
     * @param string      $body    Texto plano (no se interpreta HTML).
     * @param string|null $replyTo Dirección a la que se responde (ej. el email del cliente).
     * @throws RuntimeException si no se pudo enviar. El mensaje no incluye datos sensibles.
     */
    public function send(string $to, string $subject, string $body, ?string $replyTo = null): void;
}
