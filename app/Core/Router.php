<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mapea método HTTP + URL a un controlador y acción.
 * Soporta segmentos dinámicos: /catalogo/{slug}
 */
final class Router
{
    /** @var array<int, array{0: string, 1: string, 2: array}> */
    private array $routes = [];

    /**
     * Registra una ruta.
     *
     * @param string $method  Método HTTP (GET, POST...)
     * @param string $pattern URL, con segmentos {nombre} opcionales
     * @param array  $handler [Controlador::class, 'acción']
     */
    public function add(string $method, string $pattern, array $handler): void
    {
        // Convierte {slug} en un grupo con nombre: (?P<slug>[^/]+)
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [strtoupper($method), $regex, $handler];
    }

    /**
     * Busca la ruta que coincide con la petición.
     *
     * @return array{0: array, 1: array}|null  [handler, parámetros] o null si no hay coincidencia
     */
    public function dispatch(Request $request): ?array
    {
        foreach ($this->routes as [$method, $regex, $handler]) {
            if ($method === $request->method && preg_match($regex, $request->path, $matches)) {
                // Se conservan solo los grupos con nombre (los parámetros de la ruta).
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, $params];
            }
        }

        return null;
    }
}