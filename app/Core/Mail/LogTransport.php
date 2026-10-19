<?php
declare(strict_types=1);

namespace App\Core\Mail;

use Psr\Log\LoggerInterface;

/**
 * No envía el mail: lo escribe en storage/logs/app.log. Para desarrollo y pruebas.
 */
final class LogTransport implements MailTransport
{
    public function __construct(private LoggerInterface $logger) {}

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): void
    {
        $this->logger->info('Mail (modo log, no enviado)', compact('to', 'replyTo', 'subject', 'body'));
    }
}
