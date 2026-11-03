<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Book;

/**
 * Contrato de acceso a los libros. El resto de la aplicación depende de esta
 * interfaz y no del origen de los datos (hoy un JSON; más adelante, una base de datos).
 */
interface BookRepositoryInterface
{
    // Órdenes aceptados por search(): clave => texto para mostrar.
    public const SORTS = [
        'precio-asc'  => 'Precio: menor a mayor',
        'precio-desc' => 'Precio: mayor a menor',
        'titulo-asc'  => 'Título: A-Z',
        'titulo-desc' => 'Título: Z-A',
    ];

    /**
     * Libros cuyo título o autor contienen $query (sin distinguir mayúsculas ni acentos).
     * Con $query vacío devuelve todos.
     *
     * @param string|null $sort Una clave de SORTS; null mantiene el orden original.
     * @return Book[]
     */
    public function search(string $query = '', ?string $sort = null): array;

    // El libro con ese slug, o null si no existe.
    public function findBySlug(string $slug): ?Book;

    // El libro con ese título y autor (sin distinguir mayúsculas ni acentos), o null.
    public function findByTitleAndAuthor(string $title, string $author): ?Book;

    /**
     * Guarda un libro nuevo y lo devuelve con su id y su slug (único, generado a partir del título).
     *
     * @throws BookAlreadyExistsException si ya hay un libro con el mismo título y autor.
     *         Se controla en el mismo momento de guardar (como el registro de usuarios).
     */
    public function create(
        string $title,
        string $author,
        string $genre,
        float $price,
        string $summary,
        string $synopsis,
        ?string $cover = null,
    ): Book;

    /**
     * Guarda los cambios de un libro que ya existe (se busca por su id; el slug no cambia).
     *
     * @throws BookAlreadyExistsException si OTRO libro tiene el mismo título y autor.
     * @throws BookNotFoundException si el libro ya no existe.
     */
    public function update(Book $book): void;
}
