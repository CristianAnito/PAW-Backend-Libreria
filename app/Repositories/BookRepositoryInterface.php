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
}
