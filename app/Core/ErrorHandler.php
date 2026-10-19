<?php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Manejo centralizado de errores.
 * - Registra el detalle completo en el log.
 * - Al usuario le muestra una página genérica (500), sin rutas, consultas ni stack traces.
 * - Solo en desarrollo (debug = true) se agrega el detalle en pantalla.
 */
final class ErrorHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private View $view,
        private bool $debug,
    ) {}

    /**
     * Configura PHP para que ningún error llegue a la pantalla sin pasar por acá.
     */
    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug ? '1' : '0');
        ini_set('log_errors', '0'); // lo registra Monolog

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
     * Registra la excepción y devuelve la respuesta de error para el usuario.
     */
    public function handle(Throwable $e): Response
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e::class,
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);

        try {
            $html = $this->view->render('errors/500', [
                'title'       => 'Error del servidor',
                'currentPath' => '',
                'exception'   => $this->debug ? $e : null,
            ]);
        } catch (Throwable) {
            // Si falla incluso la vista de error, texto plano sin detalles.
            $html = 'Ocurrió un error inesperado. Intentá nuevamente más tarde.';
        }

        return new Response($html, 500);
    }
}
