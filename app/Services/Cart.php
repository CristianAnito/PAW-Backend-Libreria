<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Repositories\BookRepositoryInterface;

/**
 * Carrito de compras de un usuario, guardado en la sesión de PHP como [slug => cantidad].
 * Solo se guardan slugs y cantidades: título y precio se leen siempre del catálogo,
 * así el usuario no puede alterar el precio.
 *
 * Cada usuario tiene su propio carrito dentro de la sesión ("cart.{id}"): si en el mismo
 * navegador alguien cierra sesión y entra otra persona, no ve el carrito del anterior.
 * (Con la base de datos de la próxima entrega, el carrito puede pasar a una tabla y
 * conservarse entre dispositivos.)
 */
final class Cart
{
    public const MAX_QUANTITY = 10;   // máximo de ejemplares por libro

    public function __construct(
        private BookRepositoryInterface $books,
        private int $userId,
    ) {}

    // Clave de la sesión donde se guarda el carrito de este usuario.
    private function key(): string
    {
        return 'cart.' . $this->userId;
    }

    // Suma ejemplares de un libro (sin pasar del máximo).
    public function add(string $slug, int $quantity = 1): void
    {
        $items = $this->raw();
        $items[$slug] = min(self::MAX_QUANTITY, ($items[$slug] ?? 0) + $quantity);
        Session::set($this->key(), $items);
    }

    // Reemplaza la cantidad de un libro que ya está en el carrito.
    public function update(string $slug, int $quantity): void
    {
        $items = $this->raw();
        if (isset($items[$slug])) {
            $items[$slug] = $quantity;
            Session::set($this->key(), $items);
        }
    }

    public function remove(string $slug): void
    {
        $items = $this->raw();
        unset($items[$slug]);
        Session::set($this->key(), $items);
    }

    public function clear(): void
    {
        Session::forget($this->key());
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

    // true si el libro ya está en el carrito.
    public function has(string $slug): bool
    {
        return isset($this->raw()[$slug]);
    }

    /**
     * Representación JSON del carrito.
     */
    public function toArray(): array
    {
        $lines = $this->lines();

        return [
            'items' => array_map(fn (array $line) => [
                'book'     => $line['book']->toArray(),
                'quantity' => $line['quantity'],
                'subtotal' => $line['subtotal'],
            ], $lines),
            'total'        => array_sum(array_column($lines, 'subtotal')),
            'count'        => $this->count(),
            'max_quantity' => self::MAX_QUANTITY,
        ];
    }

    private function raw(): array
    {
        return Session::get($this->key(), []);
    }
}
