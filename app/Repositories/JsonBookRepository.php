<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Book;
use RuntimeException;

/**
 * Libros guardados en dos archivos JSON:
 * - storage/data/books.json: el catálogo base. Está en el repositorio y la API solo lo LEE.
 * - storage/data/libros-cargados.json: los libros que carga el dueño. Lo crea y escribe la API,
 *   y está fuera de git (.gitignore): probar la carga en local no modifica el catálogo del repo.
 * El catálogo es la unión de los dos.
 *
 * Como users.json, el archivo de libros cargados se escribe con flock() (ver JsonUserRepository).
 * En Render el disco se borra al reiniciar: los libros cargados se pierden hasta usar una base de datos.
 */
final class JsonBookRepository implements BookRepositoryInterface
{
    /** @var Book[]|null Se leen los archivos una sola vez por petición. */
    private ?array $books = null;

    /**
     * @param string      $file       Catálogo base (solo lectura).
     * @param string|null $loadedFile Libros cargados por el dueño; null: no se pueden cargar libros.
     */
    public function __construct(
        private string $file,
        private ?string $loadedFile = null,
    ) {}

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

    public function findByTitleAndAuthor(string $title, string $author): ?Book
    {
        foreach ($this->all() as $book) {
            if (self::sameBook($book->title, $book->author, $title, $author)) {
                return $book;
            }
        }
        return null;
    }

    public function create(
        string $title,
        string $author,
        string $genre,
        float $price,
        string $summary,
        string $synopsis,
        ?string $cover = null,
    ): Book {
        if ($this->loadedFile === null) {
            throw new RuntimeException('No hay un archivo configurado para guardar libros cargados.');
        }

        $handle = @fopen($this->loadedFile, 'c+');   // c+: lo crea si no existe, sin borrar el contenido
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo de libros cargados: {$this->loadedFile}");
        }
        try {
            // Bloqueo exclusivo: "leer -> controlar duplicados -> elegir id y slug -> escribir" ocurre
            // completo, sin que otra carga simultánea se meta en el medio (mismo id o mismo slug).
            flock($handle, LOCK_EX);
            $loaded = self::readRows($handle);
            $rows = [...$this->readBaseRows(), ...$loaded];

            foreach ($rows as $row) {
                if (self::sameBook((string) $row['title'], (string) $row['author'], $title, $author)) {
                    throw new BookAlreadyExistsException();
                }
            }

            $book = new Book(
                id: max([0, ...array_column($rows, 'id')]) + 1,
                slug: self::uniqueSlug(Book::slugify($title), array_column($rows, 'slug')),
                title: $title,
                author: $author,
                genre: $genre,
                price: $price,
                summary: $summary,
                synopsis: $synopsis,
                cover: $cover,
            );
            $loaded[] = $book->toArray();

            $json = json_encode($loaded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);

            $this->books = null; // la próxima lectura incluye el libro nuevo
            return $book;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return Book[] Catálogo base + libros cargados. */
    private function all(): array
    {
        if ($this->books === null) {
            $rows = [...$this->readBaseRows(), ...$this->readLoadedRows()];
            $this->books = array_map([Book::class, 'fromArray'], $rows);
        }
        return $this->books;
    }

    private function readBaseRows(): array
    {
        $json = @file_get_contents($this->file);
        if ($json === false) {
            throw new RuntimeException("No se pudo leer el archivo de libros: {$this->file}");
        }
        // JSON_THROW_ON_ERROR: un JSON mal formado lanza excepción (y termina en el ErrorHandler).
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function readLoadedRows(): array
    {
        if ($this->loadedFile === null || !is_file($this->loadedFile)) {
            return []; // todavía no se cargó ningún libro
        }
        $handle = @fopen($this->loadedFile, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo de libros cargados: {$this->loadedFile}");
        }
        try {
            // Bloqueo compartido: si alguien está escribiendo, se espera (nunca se lee a medio escribir).
            flock($handle, LOCK_SH);
            return self::readRows($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private static function readRows($handle): array
    {
        $json = stream_get_contents($handle);
        if ($json === false || trim($json) === '') {
            return [];
        }
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    // "el-tunel" ya existe -> "el-tunel-2" (o -3, -4...).
    private static function uniqueSlug(string $slug, array $existing): string
    {
        $candidate = $slug;
        for ($n = 2; in_array($candidate, $existing, true); $n++) {
            $candidate = "{$slug}-{$n}";
        }
        return $candidate;
    }

    // Mismo libro: mismo título y mismo autor, sin distinguir mayúsculas, acentos ni espacios extremos.
    private static function sameBook(string $titleA, string $authorA, string $titleB, string $authorB): bool
    {
        return self::normalize($titleA) === self::normalize($titleB)
            && self::normalize($authorA) === self::normalize($authorB);
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
