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
     * @param array  $body    Datos enviados: JSON decodificado o campos de formulario ($_POST).
     * @param array  $headers Cabeceras con el nombre en minúsculas (ej. 'origin').
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
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
        );
    }

    /**
     * El frontend envía JSON (Content-Type: application/json), que PHP no carga en $_POST:
     * hay que leer el cuerpo crudo y decodificarlo. Un JSON inválido se trata como cuerpo vacío
     * (la validación del controlador informa los campos faltantes).
     */
    private static function parseBody(string $contentType): array
    {
        if (str_starts_with(strtolower($contentType), 'application/json')) {
            $data = json_decode((string) file_get_contents('php://input'), true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
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

    // Una cabecera (nombre sin distinguir mayúsculas), o $default si no vino.
    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }
}
