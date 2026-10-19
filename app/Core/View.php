<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Motor de vistas: renderiza una plantilla dentro de un layout.
 * Devuelve un string (HTML), nunca un Response.
 */
final class View
{
    public function __construct(private string $viewsPath) {}

    public function render(string $view, array $data = [], string $layout = 'layouts/main'): string 
    {
        // 1) Se renderiza la vista de la página.
        $content = $this->capture($view, $data);

        // 2) Se renderiza el layout, que imprime $content con <?= $content.
        return $this->capture($layout, $data + ['content' => $content]);
    }

    /** Ejecuta una plantilla PHP con las variables de $data y devuelve su salida. */
    private function capture(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->viewsPath . '/' . $view . '.php';
        return (string) ob_get_clean();
    }
}
