<?php
declare(strict_types=1);

/**
 * Envío de correo (formulario de compra). Los valores vienen de .env.
 */
return [
    'driver'     => env('MAIL_DRIVER', 'smtp'),                     // 'smtp' (envía) o 'log' (solo registra)
    'to'         => env('MAIL_TO', 'admin@libreria.com'),          // destinatario: el personal de la librería
    'from'       => env('MAIL_FROM', 'no-reply@libreria.com'),
    'from_name'  => env('MAIL_FROM_NAME', 'PAWPrints'),
    'host'       => env('MAIL_HOST', 'localhost'),
    'port'       => (int) env('MAIL_PORT', 25),
    'user'       => env('MAIL_USER'),
    'pass'       => env('MAIL_PASS'),
    'encryption' => env('MAIL_ENCRYPTION'),                          // 'tls', 'ssl' o null
];
