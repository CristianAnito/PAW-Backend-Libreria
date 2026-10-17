<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response};

/**
 * Página de inicio (GET /).
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('home/index', [
            'title'    => 'Inicio',                    // Título de la pestaña
            'extraCss' => ['/assets/css/index.css'],   // CSS propio de esta página
        ]);
    }
}
