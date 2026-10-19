<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Request, Response, Session, View};
use App\Repositories\{BookRepositoryInterface, JsonBookRepository};
use App\Services\Cart;

/**
 * Clase base de los controladores.
 */
abstract class Controller
{
    private ?BookRepositoryInterface $books = null;

    public function __construct(protected View $view) {}

    /**
     * Renderiza una vista dentro del layout y devuelve una Response.
     *
     * @param string $template Ruta relativa a app/Views, sin extensión (ej. 'home/index').
     * @param array  $data     Variables disponibles en la vista y el layout.
     * @param int    $status   Código HTTP.
     */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        // Ruta actual: la usa nav.php para marcar el enlace activo.
        $data['currentPath'] ??= Request::fromGlobals()->path;
        // Ejemplares en el carrito, para el contador del menú.
        $data['cartCount'] ??= $this->cart()->count();

        return new Response($this->view->render($template, $data), $status);
    }

    /**
     * Respuesta 404 (ruta, libro o página inexistente).
     */
    protected function notFound(): Response
    {
        return $this->render('errors/404', ['title' => 'Página no encontrada'], 404);
    }

    // Acceso a los libros (hoy desde el JSON; se crea una sola vez por petición).
    protected function books(): BookRepositoryInterface
    {
        return $this->books ??= new JsonBookRepository(Config::get('catalog.books_file'));
    }

    /**
     * ¿Hay un usuario con sesión iniciada?
     * Contrato para el inicio de sesión (todavía no implementado): al ingresar,
     * guardar los datos del usuario con Session::set('user', [...]); al salir, Session::forget('user').
     */
    protected function isLoggedIn(): bool
    {
        return Session::get('user') !== null;
    }

    // Carrito del visitante (guardado en la sesión).
    protected function cart(): Cart
    {
        return new Cart($this->books());
    }
}
