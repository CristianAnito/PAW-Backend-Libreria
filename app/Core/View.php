<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Motor de plantillas mínimo: archivos .php dentro de app/Views.
 * Las páginas del sitio las arma el frontend (SPA); acá solo quedan plantillas
 * que genera el servidor, como el cuerpo de los mails.
 */
final class View
{
    // $viewsPath: carpeta raíz de las plantillas (app/Views).
    public function __construct(private string $viewsPath) {}

    /**
     * Ejecuta la plantilla con $data como variables locales y devuelve su salida.
     *
     * @param string $template Ruta relativa sin extensión (ej. 'emails/order').
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->viewsPath . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("Vista no encontrada: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean(); // descarta la salida a medio generar
            throw $e;
        }
    }
}
