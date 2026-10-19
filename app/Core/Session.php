<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sesión de PHP: datos que el servidor recuerda entre peticiones de un mismo visitante
 * (ej. el carrito). El navegador solo guarda una cookie con el identificador de sesión.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = ($_SERVER['HTTPS'] ?? '') === 'on'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_start([
            'cookie_httponly' => true,     // JavaScript no puede leer la cookie (mitiga robo por XSS)
            'cookie_secure'   => $https,   // en producción (HTTPS) la cookie solo viaja cifrada
            'cookie_samesite' => 'Lax',    // no se envía en POSTs desde otros sitios (mitiga CSRF)
            'use_strict_mode' => true,     // rechaza identificadores de sesión inventados
        ]);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Mensaje de un solo uso: se guarda antes de redirigir y se lee (y borra) en la página siguiente.
     */
    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function pullFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }
}
