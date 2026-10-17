<?php
declare(strict_types=1);

use App\Controllers\{HomeController, AboutController};

/**
 * Cada ruta: [método HTTP, patrón de URL, [Controlador::class, 'acción']].
 * Los segmentos {nombre} se capturan y se pasan a la acción como argumento.
 */
return [
    ['GET', '/',         [HomeController::class,  'index']],
    ['GET', '/nosotros', [AboutController::class, 'index']],

    // Consignas siguientes (agregar cuando exista el controlador):
    // ['GET',  '/catalogo',            [CatalogController::class,     'index']],
    // ['GET',  '/catalogo/export.csv', [CatalogController::class,     'exportCsv']],
    // ['GET',  '/catalogo/{slug}',     [CatalogController::class,     'show']],
    // ['POST', '/reservar',            [ReservationController::class, 'submit']],
];
