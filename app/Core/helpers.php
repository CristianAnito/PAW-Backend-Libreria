<?php
declare(strict_types=1);

/**
 * Lee una variable de entorno (del servidor o de .env, ver Config::load).
 * Convierte 'true', 'false' y 'null' a su tipo. Uso en config/*.php: env('APP_ENV', 'production').
 */
function env(string $name, mixed $default = null): mixed
{
    $value = $_ENV[$name] ?? getenv($name);
    if ($value === false) {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true'     => true,
        'false'    => false,
        'null', '' => null,
        default    => $value,
    };
}

/**
 * Formatea un precio en pesos: 30499 -> "$30.499" (se usa en el mail de compra).
 */
function money(float $amount): string
{
    return '$' . number_format($amount, 0, ',', '.');
}
