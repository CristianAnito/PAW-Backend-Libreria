<?php
declare(strict_types=1);

use App\Controllers\{AuthController, CatalogController, CartController, CheckoutController};

/**
 * Rutas de la API. Todas responden JSON (salvo la exportación CSV).
 * Las páginas del sitio (inicio, nosotros, catálogo...) son del frontend (SPA).
 *
 * Cada ruta: [método HTTP, patrón de URL, [Controlador::class, 'acción']].
 * Los segmentos {nombre} se capturan y se pasan a la acción como argumento.
 * Se evalúan en orden: las rutas fijas van antes que las que tienen {segmentos}.
 */
return [
    ['GET',    '/api/libros',              [CatalogController::class,  'index']],
    ['GET',    '/api/libros/todos',        [CatalogController::class,  'all']],
    ['GET',    '/api/libros/export.csv',   [CatalogController::class,  'exportCsv']],
    ['GET',    '/api/libros/{slug}',       [CatalogController::class,  'show']],

    // Carrito y compra: requieren la sesión iniciada (401 si no hay). Un carrito por usuario.
    ['GET',    '/api/carrito',             [CartController::class,     'index']],
    ['POST',   '/api/carrito/items',       [CartController::class,     'add']],
    ['PATCH',  '/api/carrito/items/{slug}', [CartController::class,    'update']],
    ['DELETE', '/api/carrito/items/{slug}', [CartController::class,    'remove']],
    ['POST',   '/api/compra',              [CheckoutController::class, 'submit']],

    // Usuarios: la sesión es un JWT en la cookie HttpOnly "auth_token" (docs/cookie-de-sesion.md).
    ['POST',   '/api/auth/registro',       [AuthController::class,     'register']],
    ['POST',   '/api/auth/login',          [AuthController::class,     'login']],
    ['POST',   '/api/auth/logout',         [AuthController::class,     'logout']],
    ['GET',    '/api/auth/yo',             [AuthController::class,     'me']],
];
