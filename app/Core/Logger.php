<?php
declare(strict_types=1);

namespace App\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;

/**
 * Crea el logger de la aplicación (Monolog, que implementa PSR-3).
 * Escribe en storage/logs/app.log. Uso: $logger->error('mensaje', ['dato' => $valor]).
 */
final class Logger
{
    public static function create(string $file, string $level = 'debug'): LoggerInterface
    {
        $logger = new Monolog('app');
        $logger->pushHandler(new StreamHandler($file, Level::fromName($level)));

        return $logger;
    }
}
