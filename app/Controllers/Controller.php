<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Response};
use App\Repositories\{BookRepositoryInterface, JsonBookRepository, JsonUserRepository, UserRepositoryInterface};
use App\Services\Cart;

/**
 * Clase base de los controladores de la API.
 * Todas las acciones devuelven una Response con JSON (salvo la exportación CSV).
 */
abstract class Controller
{
    private ?BookRepositoryInterface $books = null;
    private ?UserRepositoryInterface $users = null;

    /**
     * Respuesta 404 (libro o página inexistente).
     */
    protected function notFound(string $message = 'El recurso solicitado no existe.'): Response
    {
        return Response::error($message, 404);
    }

    /**
     * Respuesta 422 Unprocessable Content: la petición llegó bien pero los datos no son válidos.
     *
     * @param array<string, string> $fields Mensaje de error de cada campo.
     */
    protected function validationError(array $fields): Response
    {
        return Response::error('Revisá los campos marcados.', 422, $fields);
    }

    // Acceso a los libros (hoy desde el JSON; se crea una sola vez por petición).
    protected function books(): BookRepositoryInterface
    {
        return $this->books ??= new JsonBookRepository(Config::get('catalog.books_file'));
    }

    // Acceso a los usuarios (hoy desde el JSON; se crea una sola vez por petición).
    protected function users(): UserRepositoryInterface
    {
        return $this->users ??= new JsonUserRepository(Config::get('auth.users_file'));
    }

    // Carrito del visitante (guardado en la sesión).
    protected function cart(): Cart
    {
        return new Cart($this->books());
    }
}
