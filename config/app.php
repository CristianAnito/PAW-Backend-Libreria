<?php
declare(strict_types=1);

/**
 * Configuración general. Los valores vienen de .env (ver .env.example).
 * Por defecto se asume 'production': si falta .env, nunca se muestran detalles de errores.
 */
$env = env('APP_ENV', 'production');

return [
    'name'  => 'PAWPrints',
    'env'   => $env,
    'debug' => $env === 'development',   // true: muestra el detalle de los errores en pantalla
    'url'   => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'America/Argentina/Buenos_Aires',
    'log'   => [
        'file'  => dirname(__DIR__) . '/storage/logs/app.log',
        'level' => env('LOG_LEVEL', 'debug'),
    ],
];
