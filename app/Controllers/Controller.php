<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuthCookie, Config, HttpException, Logger, Request, Response};
use App\Models\User;
use App\Repositories\{BookRepositoryInterface, JsonBookRepository, JsonUserRepository, UserRepositoryInterface};
use App\Services\{Cart, JwtService, OwnerAccount};

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

    // Acceso a los libros: catálogo base + libros cargados por el dueño (se crea una sola vez por petición).
    protected function books(): BookRepositoryInterface
    {
        return $this->books ??= new JsonBookRepository(
            Config::get('catalog.books_file'),
            Config::get('catalog.loaded_books_file'),
        );
    }

    /**
     * Un campo del cuerpo como texto ('' si no vino o si no es texto, ej. un array).
     * Las contraseñas se usan sin quitar espacios: son parte de la contraseña.
     */
    protected static function text(Request $request, string $field, bool $trim = true): string
    {
        $value = $request->input($field, '');
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            return '';
        }
        return $trim ? trim($value) : $value;
    }

    /**
     * Acceso a los usuarios (hoy desde el JSON; se crea una sola vez por petición).
     * Antes del primer uso se asegura que exista la cuenta del dueño (Services/OwnerAccount):
     * así ningún registro puede ocupar su nombre, ni siquiera después de que Render borre el disco.
     */
    protected function users(): UserRepositoryInterface
    {
        if ($this->users === null) {
            $this->users = new JsonUserRepository(Config::get('auth.users_file'));
            $logger = Logger::create(Config::get('app.log.file'), Config::get('app.log.level'));
            (new OwnerAccount(Config::get('auth.owner', []), $logger))->ensure($this->users);
        }
        return $this->users;
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

    /**
     * Usuario dueño de la librería; si no hay sesión corta con 401, y si es un cliente, con 403.
     * Para las acciones que solo puede hacer el dueño (ej. cargar libros):
     *
     *   $owner = $this->requireOwner($request);
     *
     * El rol se lee del usuario guardado (repositorio), no del token: si cambia, vale enseguida.
     * Ocultar opciones en el frontend es solo estético: este control es el que protege la acción.
     *
     * @throws HttpException 401 (sin sesión) o 403 (con sesión, pero sin permiso).
     */
    protected function requireOwner(Request $request): User
    {
        $user = $this->requireAuth($request);
        if (!$user->isOwner()) {
            // 403 Forbidden: se sabe quién es, pero no tiene permiso (401 es "no se sabe quién es").
            throw new HttpException(403, 'No tenés permiso para realizar esta acción.');
        }
        return $user;
    }

    /**
     * Usuario cliente; si no hay sesión corta con 401, y si es el dueño de la librería, con 403.
     * Para las acciones de compra (carrito y finalizar compra): el dueño administra el catálogo, no compra.
     *
     *   $client = $this->requireClient($request);
     *
     * @throws HttpException 401 (sin sesión) o 403 (es el dueño).
     */
    protected function requireClient(Request $request): User
    {
        $user = $this->requireAuth($request);
        if ($user->isOwner()) {
            throw new HttpException(403, 'El dueño de la librería no puede comprar libros.');
        }
        return $user;
    }

    // Carrito del usuario (guardado en la sesión de PHP, uno por usuario).
    protected function cart(User $user): Cart
    {
        return new Cart($this->books(), $user->id);
    }
}
