<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;
use Throwable;

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
        // 1) Configuración (.env + config/*.php), logger y manejo de errores.
        Config::load($this->basePath);
        date_default_timezone_set(Config::get('app.timezone'));
        $logger = Logger::create(Config::get('app.log.file'), Config::get('app.log.level'));
        $view = new View($this->basePath . '/app/Views');
        $errors = new ErrorHandler($logger, $view, (bool) Config::get('app.debug'));
        $errors->register();
        Session::start();

        try {
            $this->handle($view)->send();
        } catch (Throwable $e) {
            // Cualquier error no previsto: se registra y se responde 500 sin detalles internos.
            $errors->handle($e)->send();
        }
    }

    private function handle(View $view): Response
    {
        // 2) Análisis de la petición HTTP (método, ruta, query, body).
        $request = Request::fromGlobals();

        // 3) Registro de las rutas definidas en config/routes.php.
        $router = new Router();
        foreach (require $this->basePath . '/config/routes.php' as [$method, $pattern, $handler]) {
            $router->add($method, $pattern, $handler);
        }

        // 4) Búsqueda de la ruta que coincide con la petición.
        $match = $router->dispatch($request);

        // 5) Sin coincidencia: respuesta 404.
        if ($match === null) {
            return (new ErrorController($view))->notFound();
        }

        // 6) Con coincidencia: se instancia el controlador y se ejecuta la acción.
        //    $params contiene los segmentos dinámicos ({slug}); con claves string,
        //    el spread (...) los pasa como argumentos nombrados (PHP 8.1+).
        [[$class, $action], $params] = $match;
        return (new $class($view))->$action($request, ...$params);
    }
}
