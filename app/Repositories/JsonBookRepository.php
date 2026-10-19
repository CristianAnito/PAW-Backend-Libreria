<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Book;
use RuntimeException;

/**
 * Libros guardados en un archivo JSON (storage/data/books.json).
 */
final class JsonBookRepository implements BookRepositoryInterface
{
    /** @var Book[]|null Se lee el archivo una sola vez por petición. */
    private ?array $books = null;

    public function __construct(private string $file) {}

    public function search(string $query = '', ?string $sort = null): array
    {
        $books = $this->all();

        $needle = self::normalize($query);
        if ($needle !== '') {
            $books = array_values(array_filter(
                $books,
                fn (Book $book) => str_contains(self::normalize($book->title . ' ' . $book->author), $needle)
            ));
        }

        return $this->sort($books, $sort);
    }

    /**
     * @param Book[] $books
     * @return Book[]
     */
    private function sort(array $books, ?string $sort): array
    {
        $compare = match ($sort) {
            'precio-asc'  => fn (Book $a, Book $b) => $a->price <=> $b->price,
            'precio-desc' => fn (Book $a, Book $b) => $b->price <=> $a->price,
            // Comparación sin acentos, para que "Él" no quede después de la "Z".
            'titulo-asc'  => fn (Book $a, Book $b) => strcmp(self::normalize($a->title), self::normalize($b->title)),
            'titulo-desc' => fn (Book $a, Book $b) => strcmp(self::normalize($b->title), self::normalize($a->title)),
            default       => null,
        };

        if ($compare !== null) {
            usort($books, $compare);
        }
        return $books;
    }

    public function findBySlug(string $slug): ?Book
    {
        foreach ($this->all() as $book) {
            if ($book->slug === $slug) {
                return $book;
            }
        }
        return null;
    }

    /** @return Book[] */
    private function all(): array
    {
        if ($this->books === null) {
            $json = @file_get_contents($this->file);
            if ($json === false) {
                throw new RuntimeException("No se pudo leer el archivo de libros: {$this->file}");
            }
            // JSON_THROW_ON_ERROR: un JSON mal formado lanza excepción (y termina en el ErrorHandler).
            $rows = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->books = array_map([Book::class, 'fromArray'], $rows);
        }
        return $this->books;
    }

    /**
     * Minúsculas y sin acentos, para que "garcia" encuentre "García".
     */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        return strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
