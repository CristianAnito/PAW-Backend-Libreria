<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Entidad Libro. Inmutable: se crea a partir de los datos del repositorio.
 */
final class Book
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,      // identificador para la URL: /catalogo/{slug}
        public readonly string $title,
        public readonly string $author,
        public readonly string $genre,
        public readonly float $price,
        public readonly string $summary,   // descripción corta (listado)
        public readonly string $synopsis,  // descripción larga (detalle)
        public readonly ?string $cover,    // archivo en public/assets/media/libros, o null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            (string) $data['slug'],
            (string) $data['title'],
            (string) $data['author'],
            (string) ($data['genre'] ?? ''),
            (float) $data['price'],
            (string) ($data['summary'] ?? ''),
            (string) ($data['synopsis'] ?? ''),
            $data['cover'] ?? null,
        );
    }

    // URL pública de la tapa; si no tiene, la imagen genérica.
    public function coverUrl(): string
    {
        return $this->cover !== null
            ? '/assets/media/libros/' . rawurlencode($this->cover)
            : '/assets/media/sinfoto.png';
    }
}
