<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response};

/**
 * Sección "Acerca de nosotros" (GET /nosotros).
 */
final class AboutController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('about/index', [
            'title'    => 'Nosotros',
            'extraCss' => ['/assets/css/nosotros.css'],
            'mainClass' => 'nosotros-main',   // clase del <main> usada por nosotros.css
        ]);
    }
}
