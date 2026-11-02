<?php
declare(strict_types=1);

/**
 * Catálogo de libros.
 */
return [
    'books_file'        => dirname(__DIR__) . '/storage/data/books.json',            // catálogo base (en git)
    'loaded_books_file' => dirname(__DIR__) . '/storage/data/libros-cargados.json',  // cargados por el dueño (fuera de git)
    'per_page'         => 6,     // libros por página (paginado configurable)
    'max_query_length' => 100,   // largo máximo del texto de búsqueda
];
