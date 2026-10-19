<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Motor de plantillas mínimo: archivos .php con HTML dentro de app/Views.
 */
final class View
{
    // $viewsPath: carpeta raíz de las plantillas (app/Views).
    public function __construct(private string $viewsPath) {}

    /**
     * Renderiza una plantilla dentro de un layout y devuelve el HTML.
     *
     * @param string      $template Ruta relativa sin extensión (ej. 'home/index').
     * @param array       $data     Variables disponibles en la plantilla y el layout.
     * @param string|null $layout   Layout que envuelve la plantilla; null para no usar ninguno.
     */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }

        // El layout imprime el HTML de la plantilla con la variable $content.
        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    /**
     * Ejecuta un archivo de vista con $data como variables locales y captura su salida.
     */
    private function renderFile(string $template, array $data): string
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
            ob_end_clean(); // descarta el HTML a medio generar
            throw $e;
        }
    }
}
