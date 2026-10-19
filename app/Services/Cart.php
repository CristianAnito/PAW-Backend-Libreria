<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Repositories\BookRepositoryInterface;

/**
 * Carrito de compras guardado en la sesión como [slug => cantidad].
 * Solo se guardan slugs y cantidades: título y precio se leen siempre del catálogo,
 * así el visitante no puede alterar el precio.
 */
final class Cart
{
    public const MAX_QUANTITY = 10;   // máximo de ejemplares por libro
    private const KEY = 'cart';

    public function __construct(private BookRepositoryInterface $books) {}

    // Suma ejemplares de un libro (sin pasar del máximo).
    public function add(string $slug, int $quantity = 1): void
    {
        $items = $this->raw();
        $items[$slug] = min(self::MAX_QUANTITY, ($items[$slug] ?? 0) + $quantity);
        Session::set(self::KEY, $items);
    }

    // Reemplaza la cantidad de un libro que ya está en el carrito.
    public function update(string $slug, int $quantity): void
    {
        $items = $this->raw();
        if (isset($items[$slug])) {
            $items[$slug] = $quantity;
            Session::set(self::KEY, $items);
        }
    }

    public function remove(string $slug): void
    {
        $items = $this->raw();
        unset($items[$slug]);
        Session::set(self::KEY, $items);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * Renglones del carrito con los datos actuales del catálogo.
     * Si un libro ya no existe en el catálogo, se descarta.
     *
     * @return array<int, array{book: \App\Models\Book, quantity: int, subtotal: float}>
     */
    public function lines(): array
    {
        $lines = [];
        foreach ($this->raw() as $slug => $quantity) {
            $book = $this->books->findBySlug((string) $slug);
            if ($book !== null) {
                $lines[] = ['book' => $book, 'quantity' => $quantity, 'subtotal' => $book->price * $quantity];
            }
        }
        return $lines;
    }

    public function total(): float
    {
        return array_sum(array_column($this->lines(), 'subtotal'));
    }

    // Cantidad total de ejemplares (para el contador del menú).
    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    private function raw(): array
    {
        return Session::get(self::KEY, []);
    }
}
