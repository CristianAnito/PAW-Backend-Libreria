<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response, Session};
use App\Services\Cart;

/**
 * Carrito de compras. Las acciones que lo modifican son POST y terminan
 * en una redirección al carrito (Post/Redirect/Get).
 */
final class CartController extends Controller
{
    /**
     * GET /carrito
     */
    public function index(Request $request): Response
    {
        $cart = $this->cart();

        return $this->render('cart/index', [
            'title'       => 'Carrito',
            'extraCss'    => ['/assets/css/carrito.css'],
            'lines'       => $cart->lines(),
            'total'       => $cart->total(),
            'maxQuantity' => Cart::MAX_QUANTITY,
            'error'       => Session::pullFlash('cart_error'),
        ]);
    }

    /**
     * POST /carrito/agregar  (libro)
     */
    public function add(Request $request): Response
    {
        $slug = $this->bookSlug($request);
        if ($slug === null) {
            return $this->notFound();
        }

        $this->cart()->add($slug);
        return $this->backToCart();
    }

    /**
     * POST /carrito/actualizar  (libro, cantidad)
     */
    public function update(Request $request): Response
    {
        $slug = $this->bookSlug($request);
        if ($slug === null) {
            return $this->notFound();
        }

        $quantity = filter_var($request->input('cantidad'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => Cart::MAX_QUANTITY],
        ]);
        if ($quantity === false) {
            Session::flash('cart_error', 'La cantidad debe ser un número entre 1 y ' . Cart::MAX_QUANTITY . '.');
        } else {
            $this->cart()->update($slug, $quantity);
        }

        return $this->backToCart();
    }

    /**
     * POST /carrito/eliminar  (libro)
     */
    public function remove(Request $request): Response
    {
        $slug = $this->bookSlug($request);
        if ($slug === null) {
            return $this->notFound();
        }

        $this->cart()->remove($slug);
        return $this->backToCart();
    }

    // Slug enviado en el campo "libro", solo si corresponde a un libro del catálogo.
    private function bookSlug(Request $request): ?string
    {
        $slug = $request->input('libro');
        return is_string($slug) && $this->books()->findBySlug($slug) !== null ? $slug : null;
    }

    private function backToCart(): Response
    {
        // 303 See Other: después de un POST, el navegador pide /carrito con GET.
        return Response::redirect('/carrito', 303);
    }
}
