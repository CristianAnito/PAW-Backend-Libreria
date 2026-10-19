<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cookie donde viaja el JWT de la sesión de usuario.
 *
 * El servidor la crea con la cabecera Set-Cookie y, a partir de ahí, el navegador la envía
 * sola en cada petición a la API. El frontend nunca toca el token.
 *
 *   Set-Cookie: auth_token=eyJ...; expires=...; path=/; secure; HttpOnly; SameSite=Lax
 *
 * - HttpOnly: JavaScript no puede leerla (document.cookie no la muestra). Si se colara un
 *   script malicioso en la página (XSS), no podría robar el token.
 * - Secure: solo viaja por HTTPS. Se activa cuando la petición llegó por HTTPS (producción);
 *   en localhost (HTTP) no, porque si no el navegador no la guardaría.
 * - SameSite=Lax: no se envía en POST/fetch que vienen de otros sitios (ver docs/seguridad-csrf.md).
 * - expires: igual que el vencimiento del token; después el navegador la borra.
 * - path=/: se envía a todas las rutas de la API.
 */
final class AuthCookie
{
    public static function name(): string
    {
        return (string) Config::get('auth.cookie.name', 'auth_token');
    }

    // El token que envió el navegador, o null si no hay sesión.
    public static function read(Request $request): ?string
    {
        $token = $request->cookie(self::name());
        return $token !== null && $token !== '' ? $token : null;
    }

    // Agrega a la respuesta la cookie con el token (al registrarse o iniciar sesión).
    public static function attach(Response $response, string $token, int $ttl): Response
    {
        return $response->withCookie(self::name(), $token, self::options(time() + $ttl));
    }

    // Le pide al navegador que borre la cookie (al cerrar sesión): mismo nombre, vacía y ya vencida.
    public static function clear(Response $response): Response
    {
        return $response->withCookie(self::name(), '', self::options(time() - 3600));
    }

    private static function options(int $expires): array
    {
        return [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => Request::isHttps(),
            'httponly' => true,
            'samesite' => (string) Config::get('auth.cookie.samesite', 'Lax'),
        ];
    }
}
