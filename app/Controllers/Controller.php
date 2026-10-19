<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuthCookie, Config, HttpException, Request, Response};
use App\Models\User;
use App\Repositories\{BookRepositoryInterface, JsonBookRepository, JsonUserRepository, UserRepositoryInterface};
use App\Services\{Cart, JwtService};

/**
 * Clase base de los controladores de la API.
 * Todas las acciones devuelven una Response con JSON (salvo la exportación CSV).
 */
abstract class Controller
{
    private ?BookRepositoryInterface $books = null;
    private ?UserRepositoryInterface $users = null;

    // Usuario de la sesión: se averigua una sola vez por petición (ver currentUser()).
    private ?User $currentUser = null;
    private bool $currentUserLoaded = false;

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

    /**
     * Usuario con la sesión iniciada, o null si no hay sesión.
     *
     * Lee el JWT de la cookie, lo verifica (firma, vencimiento...) y busca al usuario:
     * un token válido de un usuario que ya no existe no cuenta como sesión.
     * Para acciones que pueden usarse con o sin sesión (ej. precargar datos si hay usuario).
     */
    protected function currentUser(Request $request): ?User
    {
        if (!$this->currentUserLoaded) {
            $token = AuthCookie::read($request);
            $claims = $token !== null ? JwtService::fromConfig()->verify($token) : null;
            $this->currentUser = $claims !== null ? $this->users()->findById($claims['id']) : null;
            $this->currentUserLoaded = true;
        }
        return $this->currentUser;
    }

    /**
     * Usuario con la sesión iniciada; si no hay sesión, corta la petición con 401.
     * Para acciones que solo pueden usar usuarios registrados:
     *
     *   $user = $this->requireAuth($request);   // si no hay sesión, no se ejecuta lo que sigue
     *
     * @throws HttpException 401 (App la convierte en la respuesta y borra una cookie inválida).
     */
    protected function requireAuth(Request $request): User
    {
        return $this->currentUser($request)
            ?? throw new HttpException(401, 'Necesitás iniciar sesión.');
    }

    // Carrito del usuario (guardado en la sesión de PHP, uno por usuario).
    protected function cart(User $user): Cart
    {
        return new Cart($this->books(), $user->id);
    }
}
