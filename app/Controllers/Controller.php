<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response, View};

/**
 * Clase base de los controladores.
 */
abstract class Controller
{
    public function __construct(protected View $view) {}

    /**
     * Renderiza una vista dentro del layout y devuelve una Response.
     *
     * @param string $template Ruta relativa a app/Views, sin extensión (ej. 'home/index').
     * @param array  $data     Variables disponibles en la vista y el layout.
     * @param int    $status   Código HTTP.
     */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        // Ruta actual: la usa nav.php para marcar el enlace activo.
        $data['currentPath'] ??= Request::fromGlobals()->path;

        return new Response($this->view->render($template, $data), $status);
    }
}