<?php
declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

/**
 * Ya hay un libro con el mismo título y autor.
 */
final class BookAlreadyExistsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ya existe un libro con ese título y autor.');
    }
}
