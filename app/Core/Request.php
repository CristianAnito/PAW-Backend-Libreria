<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Petición HTTP entrante (inmutable).
 * Inspirada en PSR-7: los datos se leen una sola vez y no se modifican.
 */
final class Request
{
    /**
     * @param string $method  HTTP en mayúsculas (GET, POST...).
     * @param string $path    Ruta sin query string ni barra final (ej. '/api/libros').
     * @param array  $query   Parámetros de la query string ($_GET).
     * @param array  $body    Cuerpo JSON decodificado.
     * @param array  $headers Cabeceras con el nombre en minúsculas (ej. 'origin').
     * @param array  $cookies Cookies que envió el navegador ($_COOKIE).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
    ) {}

    /**
     * Construye la petición a partir de las superglobales de PHP.
     */
    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Sin barra final, salvo la raíz: '/api/libros/' se trata igual que '/api/libros'.
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        // Cabeceras HTTP: PHP las expone como $_SERVER['HTTP_NOMBRE'].
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            self::parseBody($headers['content-type'] ?? ''),
            $headers,
            $_COOKIE,
        );
    }

    /**
     * La API solo acepta JSON (Content-Type: application/json), que PHP no carga en $_POST:
     * hay que leer el cuerpo crudo y decodificarlo. Un JSON inválido se trata como cuerpo vacío
     * (la validación del controlador informa los campos faltantes).
     * Otros formatos (formularios) los rechaza CsrfGuard antes de llegar al controlador.
     */
    private static function parseBody(string $contentType): array
    {
        if (!self::isJsonContentType($contentType)) {
            return [];
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    private static function isJsonContentType(string $contentType): bool
    {
        // Admite parámetros: "application/json; charset=utf-8".
        return strtolower(trim(explode(';', $contentType)[0])) === 'application/json';
    }

    // true si el cuerpo de la petición es JSON.
    public function isJson(): bool
    {
        return self::isJsonContentType((string) $this->header('content-type', ''));
    }

    /**
     * true si la petición llegó por HTTPS. En Render (y otros servicios) PHP corre detrás de un
     * proxy que recibe el HTTPS y le pasa la petición por HTTP: el protocolo original viene en
     * la cabecera X-Forwarded-Proto. Se usa para marcar las cookies como Secure.
     */
    public static function isHttps(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') === 'on'
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    // Un parámetro de la query string (?q=...), o $default si no vino.
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    // Un campo del cuerpo de la petición, o $default si no vino.
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    // Una cookie (solo si es texto), o null si no vino.
    public function cookie(string $name): ?string
    {
        $value = $this->cookies[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    // Una cabecera (nombre sin distinguir mayúsculas), o $default si no vino.
    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }
}
