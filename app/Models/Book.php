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
        public readonly string $slug,      // identificador para la URL: /api/libros/{slug}
        public readonly string $title,
        public readonly string $author,
        public readonly string $genre,
        public readonly float $price,
        public readonly string $summary,   // descripción corta (listado)
        public readonly string $synopsis,  // descripción larga (detalle)
        public readonly ?string $cover,    // nombre del archivo de la tapa (lo sirve el frontend), o null
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

    /**
     * Representación JSON del libro.
     * La tapa se envía como nombre de archivo: las imágenes son parte del frontend,
     * que arma la URL (media/libros/{cover}) o usa la imagen genérica si es null.
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'slug'     => $this->slug,
            'title'    => $this->title,
            'author'   => $this->author,
            'genre'    => $this->genre,
            'price'    => $this->price,
            'summary'  => $this->summary,
            'synopsis' => $this->synopsis,
            'cover'    => $this->cover,
        ];
    }
}
