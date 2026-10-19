<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CORS (Cross-Origin Resource Sharing).
 *
 * El frontend (SPA) se sirve desde otro origen (ej. http://localhost:5501) que la API
 * (http://localhost:8000). Por seguridad, el navegador no deja que JavaScript lea respuestas
 * de otro origen salvo que la API lo autorice con estas cabeceras.
 *
 * - Solo se autorizan los orígenes listados en CORS_ORIGINS (.env), nunca '*':
 *   con credenciales (cookies) el navegador exige el origen exacto.
 * - Allow-Credentials: permite que viaje la cookie de sesión (el carrito vive en la sesión).
 */
final class Cors
{
    /** @param string[] $allowedOrigins */
    public function __construct(private array $allowedOrigins) {}

    /**
     * Petición "preflight": antes de un POST con JSON, PATCH o DELETE, el navegador
     * pregunta con OPTIONS si está permitido. Se responde 204 sin cuerpo.
     */
    public function isPreflight(Request $request): bool
    {
        return $request->method === 'OPTIONS' && $request->header('access-control-request-method') !== null;
    }

    /**
     * Agrega las cabeceras CORS si el origen de la petición está autorizado.
     */
    public function apply(Request $request, Response $response): Response
    {
        $origin = $request->header('origin');
        if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            return $response; // sin cabeceras: el navegador bloquea la lectura de la respuesta
        }

        return $response->withHeaders([
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods'     => 'GET, POST, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type',
            'Access-Control-Max-Age'           => '600',   // cachea el preflight 10 minutos
            'Vary'                             => 'Origin',
        ]);
    }
}
