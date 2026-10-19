<?php
declare(strict_types=1);

/**
 * Autenticación de usuarios con JWT.
 *
 * El token se guarda en una cookie HttpOnly (JavaScript no puede leerla) que el navegador
 * envía sola en cada petición a la API. Los valores sensibles vienen de .env.
 */
return [
    'jwt' => [
        'secret'    => env('JWT_SECRET'),             // clave de firma: nunca en el repositorio
        'ttl'       => (int) env('JWT_TTL', 3600),    // validez del token, en segundos
        'algorithm' => 'HS256',                       // HMAC-SHA256: misma clave para firmar y verificar
        'issuer'    => env('APP_URL', 'http://localhost:8000'), // quién emite el token (claim "iss")
    ],

    // Cookie donde viaja el token. Secure se agrega solo cuando la petición llegó por HTTPS.
    'cookie' => [
        'name'     => 'auth_token',
        'samesite' => 'Lax',   // no viaja en POST/fetch iniciados desde otros sitios (ver README: CSRF)
    ],

    // Usuarios registrados (hasta que haya base de datos).
    'users_file' => dirname(__DIR__) . '/storage/data/users.json',
];
