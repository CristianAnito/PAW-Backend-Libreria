<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Divide una lista en páginas.
 * Uso: $p = new Paginator($libros, page: 2, perPage: 5); $p->items(); $p->totalPages;
 */
final class Paginator
{
    public readonly int $total;
    public readonly int $totalPages;

    public function __construct(
        private array $all,
        public readonly int $page,
        public readonly int $perPage,
    ) {
        $this->total = count($all);
        // Una lista vacía tiene igual 1 página (que muestra "no hay resultados").
        $this->totalPages = max(1, (int) ceil($this->total / $perPage));
    }

    // false si se pidió una página que no existe (ej. ?page=99): el controlador responde 404.
    public function isValidPage(): bool
    {
        return $this->page >= 1 && $this->page <= $this->totalPages;
    }

    // Elementos de la página actual.
    public function items(): array
    {
        return array_slice($this->all, ($this->page - 1) * $this->perPage, $this->perPage);
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages;
    }
}
