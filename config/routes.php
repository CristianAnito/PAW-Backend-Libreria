<?php
declare(strict_types=1);

use App\Controllers\{HomeController, AboutController, CatalogController, CartController, CheckoutController};

/**
 * Cada ruta: [método HTTP, patrón de URL, [Controlador::class, 'acción']].
 * Los segmentos {nombre} se capturan y se pasan a la acción como argumento.
 * Se evalúan en orden: las rutas fijas van antes que las que tienen {segmentos}.
 */
return [
    ['GET',  '/',                    [HomeController::class,     'index']],
    ['GET',  '/nosotros',            [AboutController::class,    'index']],

    ['GET',  '/catalogo',            [CatalogController::class,  'index']],
    ['GET',  '/catalogo/export.csv', [CatalogController::class,  'exportCsv']],
    ['GET',  '/catalogo/{slug}',     [CatalogController::class,  'show']],

    ['GET',  '/carrito',             [CartController::class,     'index']],
    ['POST', '/carrito/agregar',     [CartController::class,     'add']],
    ['POST', '/carrito/actualizar',  [CartController::class,     'update']],
    ['POST', '/carrito/eliminar',    [CartController::class,     'remove']],

    ['GET',  '/compra',              [CheckoutController::class, 'form']],
    ['POST', '/compra',              [CheckoutController::class, 'submit']],
    ['GET',  '/compra/confirmada',   [CheckoutController::class, 'confirmed']],
];
