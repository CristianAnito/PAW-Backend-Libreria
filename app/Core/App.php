<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Orquesta el ciclo de una petición:
 * Request -> Router -> Controller -> Response (JSON).
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
        $errors = new ErrorHandler($logger, (bool) Config::get('app.debug'));
        $errors->register();
        Session::start();

        // 2) Análisis de la petición HTTP (método, ruta, query, body, cabeceras).
        $request = Request::fromGlobals();
        $allowedOrigins = Config::get('cors.allowed_origins', []);
        $cors = new Cors($allowedOrigins);
        $csrf = new CsrfGuard($allowedOrigins);

        try {
            // Preflight de CORS: se responde sin ejecutar nada.
            // Después, CsrfGuard frena las peticiones que modifican datos desde otros sitios.
            $response = $cors->isPreflight($request)
                ? Response::empty()
                : ($csrf->check($request) ?? $this->handle($request));
        } catch (Throwable $e) {
            // Cualquier error no previsto: se registra y se responde 500 sin detalles internos.
            $response = $errors->handle($e);
        }

        // Las cabeceras CORS van en todas las respuestas (también en los errores),
        // así el frontend puede leer el mensaje de error.
        $cors->apply($request, $response)->send();
    }

    private function handle(Request $request): Response
    {
        // 3) Registro de las rutas definidas en config/routes.php.
        $router = new Router();
        foreach (require $this->basePath . '/config/routes.php' as [$method, $pattern, $handler]) {
            $router->add($method, $pattern, $handler);
        }

        // 4) Búsqueda de la ruta que coincide con la petición.
        $match = $router->dispatch($request);

        // 5) Sin coincidencia: respuesta 404.
        if ($match === null) {
            return Response::error('El recurso solicitado no existe.', 404);
        }

        // 6) Con coincidencia: se instancia el controlador y se ejecuta la acción.
        //    $params contiene los segmentos dinámicos ({slug}); con claves string,
        //    el spread (...) los pasa como argumentos nombrados (PHP 8.1+).
        [[$class, $action], $params] = $match;
        return (new $class())->$action($request, ...$params);
    }
}
