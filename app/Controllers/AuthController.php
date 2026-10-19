<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuthCookie, Request, Response, Validator};
use App\Models\User;
use App\Repositories\UserAlreadyExistsException;
use App\Services\JwtService;

/**
 * Registro, inicio y cierre de sesión de usuarios.
 *
 * La sesión es un JWT dentro de la cookie HttpOnly "auth_token" (ver docs/cookie-de-sesion.md):
 * registro y login la crean, logout la borra y "yo" la lee.
 */
final class AuthController extends Controller
{
    // Mismo mensaje si el usuario no existe o si la contraseña es incorrecta: así nadie puede
    // averiguar qué usuarios están registrados probando nombres en el login.
    private const LOGIN_FAILED = 'Usuario o contraseña incorrectos.';

    // Hash bcrypt de una contraseña que nadie usa, con el mismo costo que los reales (ver login()).
    private const DUMMY_HASH = '$2y$12$pKFfWM70vINfzlRGCellhefZGqLjvXBMWPl3QUHIWDL8cELrZ/ZH.';

    /**
     * POST /api/auth/registro
     * {"username", "email", "telefono"?, "contrasenia", "confirmar_contrasenia"}
     *
     * 201: usuario creado y sesión iniciada. 422: datos inválidos o usuario/email ya registrados.
     */
    public function register(Request $request): Response
    {
        $password = self::text($request, 'contrasenia', trim: false);
        $confirmation = self::text($request, 'confirmar_contrasenia', trim: false);

        $errors = Validator::validate($request->body, [
            'username' => ['required', 'min:' . User::USERNAME_MIN, 'max:' . User::USERNAME_MAX,
                fn (string $v) => preg_match(User::regex(User::USERNAME_PATTERN), $v) === 1
                    ? null : 'Usá solo letras sin acentos, números, guion bajo (_) y punto.'],
            'email'    => ['required', 'max:' . User::EMAIL_MAX, 'email'],
            'telefono' => ['min:' . User::PHONE_MIN, 'max:' . User::PHONE_MAX,
                fn (string $v) => preg_match(User::regex(User::PHONE_PATTERN), $v) === 1
                    ? null : 'Solo números y espacios, con + inicial opcional.'],
            'contrasenia' => ['required', 'min:' . User::PASSWORD_MIN,
                // password_hash (bcrypt) ignora lo que pase de 72 bytes: se controla en bytes, no en letras.
                fn (string $v) => strlen($v) > User::PASSWORD_MAX_BYTES
                    ? 'La contraseña es demasiado larga.' : null,
                fn (string $v) => preg_match(User::regex(User::PASSWORD_PATTERN), $v) === 1
                    ? null : 'Debe tener al menos una letra y un número.'],
            'confirmar_contrasenia' => ['required',
                fn () => $password === $confirmation ? null : 'Las contraseñas no coinciden.'],
        ]);

        // Usuario o email ya registrados (se informan los dos a la vez, si corresponde).
        $username = self::text($request, 'username');
        $email = self::text($request, 'email');
        if (!isset($errors['username']) && $this->users()->findByUsername($username) !== null) {
            $errors['username'] = 'Ese nombre de usuario ya está en uso.';
        }
        if (!isset($errors['email']) && $this->users()->findByEmail($email) !== null) {
            $errors['email'] = 'Ya hay una cuenta registrada con ese email.';
        }

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        $phone = self::text($request, 'telefono');

        try {
            $user = $this->users()->create(
                $username,
                $email,
                $phone !== '' ? $phone : null,
                // La contraseña nunca se guarda: solo su hash (bcrypt, con "sal" aleatoria incluida).
                // Se usa tal como la escribió el usuario, sin quitarle espacios.
                password_hash($password, PASSWORD_DEFAULT),
            );
        } catch (UserAlreadyExistsException $e) {
            // Alguien registró el mismo usuario/email entre el control de arriba y el guardado.
            return $this->validationError([$e->field => $e->field === 'email'
                ? 'Ya hay una cuenta registrada con ese email.'
                : 'Ese nombre de usuario ya está en uso.']);
        }

        // Registrarse deja la sesión iniciada. 201 Created: se creó un recurso nuevo.
        return $this->startSession($user, 201);
    }

    /**
     * POST /api/auth/login  {"username": "usuario o email", "contrasenia"}
     *
     * 200: sesión iniciada. 401: usuario o contraseña incorrectos. 422: faltan datos.
     */
    public function login(Request $request): Response
    {
        $errors = Validator::validate($request->body, [
            'username'    => ['required', 'max:' . User::EMAIL_MAX],
            'contrasenia' => ['required'],
        ]);
        if ($errors !== []) {
            return $this->validationError($errors);
        }

        // Se puede entrar con el nombre de usuario o con el email.
        $login = self::text($request, 'username');
        $user = str_contains($login, '@')
            ? $this->users()->findByEmail($login)
            : $this->users()->findByUsername($login);

        $password = self::text($request, 'contrasenia', trim: false);

        if ($user === null) {
            // Se verifica igual contra un hash cualquiera: password_verify tarda lo mismo exista o no
            // el usuario, así el tiempo de respuesta no revela qué usuarios están registrados.
            password_verify($password, self::DUMMY_HASH);
            return Response::error(self::LOGIN_FAILED, 401);
        }

        if (!password_verify($password, $user->passwordHash)) {
            return Response::error(self::LOGIN_FAILED, 401);
        }

        return $this->startSession($user);
    }

    /**
     * POST /api/auth/logout
     * 204: la cookie se borra. Funciona aunque no haya sesión (cerrar sesión dos veces no es un error).
     */
    public function logout(Request $request): Response
    {
        return AuthCookie::clear(Response::empty(204));
    }

    /**
     * GET /api/auth/yo
     * 200: {"data": usuario} si hay sesión. 401 si no hay (o el token venció o fue alterado).
     * El frontend lo consulta al cargar para saber si mostrar "Hola, ..." o "Iniciar sesión".
     */
    public function me(Request $request): Response
    {
        $token = AuthCookie::read($request);
        $claims = $token !== null ? JwtService::fromConfig()->verify($token) : null;

        // Además del token válido, el usuario tiene que seguir existiendo.
        $user = $claims !== null ? $this->users()->findById($claims['id']) : null;

        if ($user === null) {
            $response = Response::error('No hay una sesión iniciada.', 401);
            // Si había una cookie (vencida o inválida), se borra para no seguir enviándola.
            return $token !== null ? AuthCookie::clear($response) : $response;
        }

        return Response::json(['data' => $user->toPublicArray()]);
    }

    // Emite el token, lo pone en la cookie y responde con los datos públicos del usuario.
    private function startSession(User $user, int $status = 200): Response
    {
        $jwt = JwtService::fromConfig();

        return AuthCookie::attach(
            Response::json(['data' => $user->toPublicArray()], $status),
            $jwt->issue($user),
            $jwt->ttl(),
        );
    }

    /**
     * Un campo del cuerpo como texto ('' si no vino o si no es texto, ej. un array).
     * Las contraseñas se usan sin quitar espacios: son parte de la contraseña.
     */
    private static function text(Request $request, string $field, bool $trim = true): string
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
}
