<?php
declare(strict_types=1);

/**
 * Envío de correo (formulario de compra). Los valores vienen de .env.
 */
return [
    // 'log' (solo registra), 'smtp' (PHPMailer, local) o 'brevo' (API HTTP, producción).
    'driver'        => env('MAIL_DRIVER', 'log'),
    'to'            => env('MAIL_TO', 'admin@libreria.com'),         // destinatario: el personal de la librería
    'from'          => env('MAIL_FROM', 'no-reply@libreria.com'),    // remitente: verificado en el proveedor
    'from_name'     => env('MAIL_FROM_NAME', 'PAWPrints'),

    // Solo para MAIL_DRIVER=brevo.
    'brevo_api_key' => env('BREVO_API_KEY'),

    // Solo para MAIL_DRIVER=smtp.
    'host'          => env('MAIL_HOST', 'localhost'),
    'port'          => (int) env('MAIL_PORT', 25),
    'user'          => env('MAIL_USER'),
    'pass'          => env('MAIL_PASS'),
    'encryption'    => env('MAIL_ENCRYPTION'),                       // 'tls', 'ssl' o null
];
