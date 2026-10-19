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
        // Por defecto, storage/logs/app.log. En Render (contenedor): LOG_FILE=php://stderr, así
        // los registros aparecen en la pestaña "Logs" del panel (el disco del contenedor se borra).
        'file'  => env('LOG_FILE', dirname(__DIR__) . '/storage/logs/app.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],
];
