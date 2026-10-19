<?php
declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

/**
 * El usuario o el email ya están registrados.
 * $field indica cuál: 'username' o 'email' (el controlador lo usa para marcar el campo).
 */
final class UserAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly string $field)
    {
        parent::__construct("Ya existe un usuario con ese {$field}.");
    }
}
