<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuración de la aplicación.
 *
 * 1) Lee el archivo .env (valores que cambian según el entorno y no se suben al repo).
 * 2) Carga cada archivo de config/ como una sección: config/mail.php -> 'mail'.
 * Uso: Config::get('mail.to'), Config::get('app.debug', false).
 */
final class Config
{
    private static array $items = [];

    public static function load(string $basePath): void
    {
        self::loadEnv($basePath . '/.env');

        // Cada archivo de config/ (salvo routes.php) devuelve un array.
        foreach (glob($basePath . '/config/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name !== 'routes') {
                self::$items[$name] = require $file;
            }
        }
    }

    /**
     * Devuelve un valor usando notación con puntos: 'seccion.clave'.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /**
     * Lector mínimo de .env: líneas CLAVE=valor; ignora vacías y las que empiezan con #.
     * Si el archivo no existe (ej. en el servidor se definen como variables de entorno), no hace nada.
     */
    private static function loadEnv(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'"); // permite VALOR="con espacios"

            // Una variable de entorno real (definida en el servidor) tiene prioridad sobre .env.
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }
    }
}
