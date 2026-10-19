<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Protección contra CSRF (Cross-Site Request Forgery).
 *
 * El ataque: una página de otro sitio hace que el navegador del usuario envíe una petición
 * a la API; el navegador agrega solo las cookies (sesión, token) y la API la ejecuta como si
 * la hubiera pedido el usuario.
 *
 * Capas de defensa (si una falla, quedan las otras):
 * 1. Cookies con SameSite=Lax: el navegador no las envía en POST/fetch iniciados desde otro sitio.
 * 2. Este guard exige Content-Type: application/json en POST y PATCH. Un <form> de otro sitio
 *    no puede enviar JSON, y un fetch con JSON a otro origen obliga al navegador a pedir
 *    permiso antes (preflight de CORS), que la API solo da a los orígenes autorizados.
 *    (DELETE y PATCH siempre pasan por el preflight, porque no son métodos "simples".)
 * 3. Este guard rechaza las peticiones que modifican datos si la cabecera Origin (que el
 *    navegador agrega y JavaScript no puede falsificar) no está en CORS_ORIGINS.
 */
final class CsrfGuard
{
    // Métodos que modifican datos. GET nunca debe modificar nada (con Lax, la cookie sí viaja en un GET).
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // Métodos que un <form> o un fetch "simple" de otro sitio pueden usar sin preflight.
    private const JSON_REQUIRED = ['POST', 'PUT', 'PATCH'];

    /** @param string[] $allowedOrigins */
    public function __construct(private array $allowedOrigins) {}

    /**
     * Devuelve una respuesta de error si la petición no pasa los controles, o null si puede seguir.
     */
    public function check(Request $request): ?Response
    {
        if (!in_array($request->method, self::UNSAFE_METHODS, true)) {
            return null;
        }

        // Sin Origin: no la envió un navegador desde una página (ej. curl, otro servidor).
        // CSRF necesita el navegador de la víctima, así que no hay ataque posible.
        $origin = $request->header('origin');
        if ($origin !== null && !in_array($origin, $this->allowedOrigins, true)) {
            return Response::error('Origen no permitido.', 403);
        }

        if (in_array($request->method, self::JSON_REQUIRED, true) && !$request->isJson()) {
            // 415 Unsupported Media Type.
            return Response::error('El cuerpo de la petición debe ser JSON (Content-Type: application/json).', 415);
        }

        return null;
    }
}
