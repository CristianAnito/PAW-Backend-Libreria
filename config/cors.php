<?php
declare(strict_types=1);

/**
 * Orígenes (esquema + host + puerto) desde los que el frontend puede llamar a la API.
 * En .env: CORS_ORIGINS=http://localhost:5501,http://127.0.0.1:5501
 */
return [
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ORIGINS', 'http://localhost:5501'))
    ))),
];
