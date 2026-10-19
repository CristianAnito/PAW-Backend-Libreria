<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use RuntimeException;

/**
 * Usuarios guardados en un archivo JSON (storage/data/users.json).
 *
 * A diferencia de los libros, este archivo se escribe. Si dos peticiones lo escribieran a la vez,
 * una podría pisar los cambios de la otra; por eso se usa flock() (bloqueo de archivo):
 * - lectura: bloqueo compartido (varios pueden leer a la vez);
 * - escritura: bloqueo exclusivo (nadie más lee ni escribe hasta terminar).
 * Una base de datos resuelve esto sola; es una de las razones para migrar a una.
 */
final class JsonUserRepository implements UserRepositoryInterface
{
    public function __construct(private string $file) {}

    public function findById(int $id): ?User
    {
        return $this->findFirst(fn (array $row) => (int) $row['id'] === $id);
    }

    public function findByUsername(string $username): ?User
    {
        $username = self::normalize($username);
        return $this->findFirst(fn (array $row) => self::normalize($row['username']) === $username);
    }

    public function findByEmail(string $email): ?User
    {
        $email = self::normalize($email);
        return $this->findFirst(fn (array $row) => self::normalize($row['email']) === $email);
    }

    public function create(string $username, string $email, ?string $phone, string $passwordHash): User
    {
        $handle = $this->open('c+');   // c+: lo crea si no existe, sin borrar el contenido
        try {
            flock($handle, LOCK_EX);
            $rows = $this->read($handle);

            // El control de duplicados se hace con el archivo bloqueado (ver la interfaz).
            foreach ($rows as $row) {
                if (self::normalize($row['username']) === self::normalize($username)) {
                    throw new UserAlreadyExistsException('username');
                }
                if (self::normalize($row['email']) === self::normalize($email)) {
                    throw new UserAlreadyExistsException('email');
                }
            }

            $user = new User(
                id: max([0, ...array_column($rows, 'id')]) + 1,
                username: $username,
                email: $email,
                phone: $phone,
                passwordHash: $passwordHash,
                createdAt: date(DATE_ATOM),
            );
            $rows[] = $user->toArray();

            $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);

            return $user;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function findFirst(callable $matches): ?User
    {
        foreach ($this->all() as $row) {
            if ($matches($row)) {
                return User::fromArray($row);
            }
        }
        return null;
    }

    /** @return array<int, array> Filas tal como están en el archivo. */
    private function all(): array
    {
        if (!is_file($this->file)) {
            return []; // todavía no se registró nadie
        }

        $handle = $this->open('r');
        try {
            flock($handle, LOCK_SH);
            return $this->read($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource */
    private function open(string $mode)
    {
        $handle = @fopen($this->file, $mode);
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo de usuarios: {$this->file}");
        }
        return $handle;
    }

    /** @param resource $handle */
    private function read($handle): array
    {
        $json = stream_get_contents($handle);
        if ($json === false || trim($json) === '') {
            return [];
        }
        // JSON_THROW_ON_ERROR: un archivo dañado lanza excepción (y termina en el ErrorHandler).
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    // Comparación sin distinguir mayúsculas ni espacios extremos.
    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}
