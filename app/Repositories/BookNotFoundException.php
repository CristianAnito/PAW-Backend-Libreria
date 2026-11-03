<?php
declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

/**
 * Se quiso modificar un libro que no existe (ej. lo borró otra petición, o se reinició el servidor
 * y se perdió el archivo de libros cargados). El controlador responde 404.
 */
final class BookNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El libro solicitado no existe.');
    }
}
