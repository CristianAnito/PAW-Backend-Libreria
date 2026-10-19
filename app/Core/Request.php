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
     * @param string $method HTTP en mayúsculas (GET, POST...).
     * @param string $path   Ruta sin query string ni barra final (ej. '/catalogo').
     * @param array  $query  Parámetros de la query string ($_GET).
     * @param array  $body   Campos del formulario ($_POST).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
    ) {}

    /**
     * Construye la petición a partir de las superglobales de PHP.
     */
    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Sin barra final, salvo la raíz: '/nosotros/' se trata igual que '/nosotros'.
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
        );
    }

    // Un parámetro de la query string (?q=...), o $default si no vino.
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    // Un campo del formulario (POST), o $default si no vino.
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }
}
