<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;

/**
 * Orquesta el ciclo de una petición:
 * Request -> Router -> Controller -> Response.
 */
final class App
{
    // $basePath: raíz del proyecto (carpeta que contiene app/, config/, public/).
    public function __construct(private string $basePath) {}

    public function run(): void
    {
        // 1) Análisis de la petición HTTP (método, ruta, query, body).
        $request = Request::fromGlobals();

        // 2) Motor de vistas apuntando a app/Views.
        $view = new View($this->basePath . '/app/Views');

        // 3) Registro de las rutas definidas en config/routes.php.
        $router = new Router();
        foreach (require $this->basePath . '/config/routes.php' as [$method, $pattern, $handler]) {
            $router->add($method, $pattern, $handler);
        }

        // 4) Búsqueda de la ruta que coincide con la petición.
        $match = $router->dispatch($request);

        // 5) Sin coincidencia: respuesta 404.
        if ($match === null) {
            (new ErrorController($view))->notFound()->send();
            return;
        }

        // 6) Con coincidencia: se instancia el controlador y se ejecuta la acción.
        //    $params contiene los segmentos dinámicos ({slug}); con claves string,
        //    el spread (...) los pasa como argumentos nombrados (PHP 8.1+).
        [[$class, $action], $params] = $match;
        (new $class($view))->$action($request, ...$params)->send();
    }
}