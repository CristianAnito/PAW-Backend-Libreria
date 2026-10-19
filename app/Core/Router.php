<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mapea (método HTTP + URL) a un controlador y acción.
 * Solo resuelve la ruta: quien la ejecuta es App.
 */
final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = [];

    /**
     * @param string $pattern URL con segmentos dinámicos opcionales, ej. '/catalogo/{slug}'.
     * @param array  $handler [Controlador::class, 'acción'].
     */
    public function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[strtoupper($method)][$pattern] = $handler;
    }

    /**
     * Devuelve [handler, params] de la ruta que coincide, o null si ninguna coincide.
     * params es un array asociativo: ['slug' => 'rayuela'].
     */
    public function dispatch(Request $request): ?array
    {
        foreach ($this->routes[$request->method] ?? [] as $pattern => $handler) {
            if (preg_match($this->toRegex($pattern), $request->path, $matches)) {
                // Se quedan solo las capturas con nombre (descarta las numéricas).
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, $params];
            }
        }

        return null;
    }

    /**
     * '/catalogo/{slug}' -> '#^/catalogo/(?P<slug>[A-Za-z0-9_-]+)$#'
     * Las rutas fijas se comparan antes porque se registran antes en routes.php.
     */
    private function toRegex(string $pattern): string
    {
        // preg_quote escapa la parte fija (ej. el '.' de 'export.csv'); deja '{slug}' como '\{slug\}'.
        $regex = preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>[A-Za-z0-9_-]+)', preg_quote($pattern, '#'));
        return '#^' . $regex . '$#';
    }
}
