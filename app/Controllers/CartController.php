<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request, Response};
use App\Services\Cart;

/**
 * Carrito de compras (guardado en la sesión del visitante).
 * Todas las acciones responden con el carrito actualizado, así el frontend
 * lo vuelve a mostrar sin hacer otra petición.
 */
final class CartController extends Controller
{
    /**
     * GET /api/carrito
     */
    public function index(Request $request): Response
    {
        return Response::json(['data' => $this->cart()->toArray()]);
    }

    /**
     * POST /api/carrito/items  {"libro": "slug", "cantidad": 1}
     * Suma ejemplares de un libro (cantidad opcional, 1 por defecto).
     */
    public function add(Request $request): Response
    {
        $slug = $request->input('libro');
        if (!$this->exists($slug)) {
            return $this->notFound('El libro solicitado no existe.');
        }

        $quantity = $this->quantity($request->input('cantidad', 1));
        if ($quantity === null) {
            return $this->quantityError();
        }

        $this->cart()->add($slug, $quantity);
        return $this->index($request);
    }

    /**
     * PATCH /api/carrito/items/{slug}  {"cantidad": 3}
     * Reemplaza la cantidad de un libro que ya está en el carrito.
     */
    public function update(Request $request, string $slug): Response
    {
        if (!$this->cart()->has($slug)) {
            return $this->notFound('El libro no está en el carrito.');
        }

        $quantity = $this->quantity($request->input('cantidad'));
        if ($quantity === null) {
            return $this->quantityError();
        }

        $this->cart()->update($slug, $quantity);
        return $this->index($request);
    }

    /**
     * DELETE /api/carrito/items/{slug}
     */
    public function remove(Request $request, string $slug): Response
    {
        if (!$this->cart()->has($slug)) {
            return $this->notFound('El libro no está en el carrito.');
        }

        $this->cart()->remove($slug);
        return $this->index($request);
    }

    // true si $slug es un texto que corresponde a un libro del catálogo.
    private function exists(mixed $slug): bool
    {
        return is_string($slug) && $this->books()->findBySlug($slug) !== null;
    }

    // Cantidad entera entre 1 y el máximo permitido, o null si no es válida.
    private function quantity(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        $quantity = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => Cart::MAX_QUANTITY],
        ]);
        return $quantity === false ? null : $quantity;
    }

    private function quantityError(): Response
    {
        return $this->validationError([
            'cantidad' => 'La cantidad debe ser un número entre 1 y ' . Cart::MAX_QUANTITY . '.',
        ]);
    }
}
