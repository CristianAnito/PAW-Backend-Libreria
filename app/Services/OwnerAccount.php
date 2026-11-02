<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\{UserAlreadyExistsException, UserRepositoryInterface};
use Psr\Log\LoggerInterface;

/**
 * Cuenta del dueño de la librería: el único usuario que puede cargar libros.
 *
 * El dueño no se registra desde el sitio (todo registro crea un cliente). Sus datos están en la
 * configuración (OWNER_USERNAME, OWNER_EMAIL, OWNER_PASSWORD_HASH en .env o en Render) y
 * ensure() crea la cuenta si no existe.
 *
 * Se llama antes de usar los usuarios en cada petición (Controller::users()), por lo que:
 * - En Render, donde el disco se borra al reiniciar, la cuenta se vuelve a crear sola.
 * - El nombre del dueño queda ocupado antes de que se procese cualquier registro:
 *   nadie puede registrarse con ese nombre o ese email.
 * - La contraseña es siempre la de la configuración: si se cambia el hash, se actualiza.
 *
 * Si algo no está bien configurado, se registra en el log y el sitio sigue funcionando sin dueño.
 */
final class OwnerAccount
{
    /**
     * @param array{username: ?string, email: ?string, password_hash: ?string} $config config/auth.php "owner"
     */
    public function __construct(
        private array $config,
        private LoggerInterface $logger,
    ) {}

    public function ensure(UserRepositoryInterface $users): void
    {
        $username = trim((string) ($this->config['username'] ?? ''));
        $email = trim((string) ($this->config['email'] ?? ''));
        $hash = (string) ($this->config['password_hash'] ?? '');

        if ($username === '' && $email === '' && $hash === '') {
            return; // sin dueño configurado (ej. en una copia local que no lo necesita)
        }
        if (!$this->isValid($username, $email, $hash)) {
            return;
        }

        $existing = $users->findByUsername($username);

        if ($existing === null) {
            try {
                $users->create($username, $email, null, $hash, User::ROLE_OWNER);
                $this->logger->info('Cuenta del dueño creada desde la configuración', ['username' => $username]);
            } catch (UserAlreadyExistsException $e) {
                // El email ya lo usa un cliente (o dos peticiones crearon la cuenta a la vez:
                // en ese caso ya existe y en la próxima petición se encuentra).
                $this->logger->warning('No se pudo crear la cuenta del dueño: dato ya registrado', ['campo' => $e->field]);
            }
            return;
        }

        if (!$existing->isOwner()) {
            // Ya hay un CLIENTE con ese nombre. No se lo convierte en dueño: sería darle permisos
            // a otra persona. Hay que elegir otro OWNER_USERNAME.
            $this->logger->error('OWNER_USERNAME pertenece a un cliente: no se le dan permisos de dueño', ['username' => $username]);
            return;
        }

        if (!hash_equals($existing->passwordHash, $hash)) {
            $users->updatePasswordHash($existing->id, $hash);
            $this->logger->info('Contraseña del dueño actualizada desde la configuración', ['username' => $username]);
        }
    }

    private function isValid(string $username, string $email, string $hash): bool
    {
        $errors = [];
        if (preg_match(User::regex(User::USERNAME_PATTERN), $username) !== 1
            || mb_strlen($username) < User::USERNAME_MIN || mb_strlen($username) > User::USERNAME_MAX) {
            $errors[] = 'OWNER_USERNAME';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'OWNER_EMAIL';
        }
        // Tiene que ser un hash de password_hash() (bcrypt o argon2), no la contraseña en texto.
        if (password_get_info($hash)['algo'] === null) {
            $errors[] = 'OWNER_PASSWORD_HASH';
        }

        if ($errors !== []) {
            // Se nombran las variables mal configuradas, nunca sus valores.
            $this->logger->error('Configuración del dueño incompleta o inválida: no se crea la cuenta', ['variables' => $errors]);
            return false;
        }
        return true;
    }
}
