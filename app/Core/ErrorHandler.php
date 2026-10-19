<?php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Manejo centralizado de errores.
 * - Registra el detalle completo en el log.
 * - Al cliente le responde un JSON genérico (500), sin rutas, consultas ni stack traces.
 * - Solo en desarrollo (debug = true) se agrega el detalle en la respuesta.
 */
final class ErrorHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $debug,
    ) {}

    /**
     * Configura PHP para que ningún error llegue a la salida sin pasar por acá.
     */
    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0'); // un warning impreso rompería el JSON de la respuesta
        ini_set('log_errors', '0');     // lo registra Monolog
        // Los stack traces no incluyen los argumentos de las funciones: si algo falla durante
        // un registro o un login, la contraseña del usuario no queda escrita en el log.
        ini_set('zend.exception_ignore_args', '1');

        // Warnings y notices se convierten en excepciones: se manejan igual que cualquier error.
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false; // error silenciado con @
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        // Última red: excepciones que nadie atrapó.
        set_exception_handler(function (Throwable $e): void {
            $this->handle($e)->send();
        });
    }

    /**
     * Registra la excepción y devuelve la respuesta de error para el cliente.
     */
    public function handle(Throwable $e): Response
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e::class,
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);

        $error = ['message' => 'Ocurrió un error inesperado. Intentá nuevamente más tarde.'];

        // Solo en desarrollo (APP_ENV=development). En producción nunca se expone el detalle.
        if ($this->debug) {
            $error['debug'] = [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'trace'     => explode("\n", $e->getTraceAsString()),
            ];
        }

        try {
            return Response::json(['error' => $error], 500);
        } catch (Throwable) {
            // Si el detalle no se puede codificar (ej. UTF-8 inválido), mensaje fijo.
            return new Response('{"error":{"message":"Ocurrió un error inesperado."}}', 500);
        }
    }
}
