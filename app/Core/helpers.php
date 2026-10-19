<?php
declare(strict_types=1);

/**
 * Escapa un valor para imprimirlo en HTML de forma segura.
 * Uso en vistas: <?= e($titulo) ?>
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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
 * Formatea un precio en pesos: 30499 -> "$30.499".
 */
function money(float $amount): string
{
    return '$' . number_format($amount, 0, ',', '.');
}
