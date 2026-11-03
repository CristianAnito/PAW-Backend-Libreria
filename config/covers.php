<?php
declare(strict_types=1);

/**
 * Tapas de los libros que carga el dueño (ver app/Services/Covers). Los valores vienen de .env.
 */
return [
    // 'local' (disco del servidor, desarrollo) o 'cloudinary' (producción: el disco de Render se borra).
    'driver'         => env('COVERS_DRIVER', 'local'),

    // Solo para COVERS_DRIVER=local: fuera de public/ (las entrega GET /api/tapas/{archivo}).
    'local_dir'      => dirname(__DIR__) . '/storage/covers',

    // Solo para COVERS_DRIVER=cloudinary: cloudinary://API_KEY:API_SECRET@CLOUD_NAME
    'cloudinary_url' => env('CLOUDINARY_URL'),
];
