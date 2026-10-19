<?php
declare(strict_types=1);

/**
 * Catálogo de libros.
 */
return [
    'books_file'       => dirname(__DIR__) . '/storage/data/books.json',
    'per_page'         => 6,     // libros por página (paginado configurable)
    'max_query_length' => 100,   // largo máximo del texto de búsqueda
];
