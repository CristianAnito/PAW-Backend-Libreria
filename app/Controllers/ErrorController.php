<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;

/**
 * Pantallas de error del sitio.
 */
final class ErrorController extends Controller
{
    // Ruta inexistente: HTTP 404.
    public function notFound(): Response
    {
        return parent::notFound();
    }
}