<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use RuntimeException;

/**
 * Usuarios guardados en un archivo JSON (storage/data/users.json).
 *
 * A diferencia de los libros, este archivo se escribe, y cada petición HTTP es un proceso aparte.
 * Guardar un usuario es "leer el archivo -> agregar al array -> escribir el archivo entero".
 * Si dos registros ocurren a la vez, los dos leen la misma versión y el que escribe último
 * pisa al otro: un usuario desaparece ("actualización perdida") o dos quedan con el mismo id.
 *
 * flock() (file lock) le pide al sistema operativo un bloqueo sobre el archivo, como la llave
 * de un baño: mientras un proceso la tiene, los demás que llaman a flock() esperan su turno.
 * - LOCK_SH (compartido), para leer: varios pueden leer a la vez, pero nadie escribe mientras tanto.
 * - LOCK_EX (exclusivo), para escribir: uno solo; los demás esperan hasta que termine.
 * - LOCK_UN: devuelve la llave.
 * El bloqueo es cooperativo: solo lo respeta el código que también usa flock() (un editor de
 * texto no espera a nadie). Una base de datos resuelve esto sola con transacciones; es una de
 * las razones para migrar a una.
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
            // Bloqueo exclusivo: si otra petición está leyendo o escribiendo el archivo, esta espera.
            // Desde acá hasta LOCK_UN nadie más lo toca, así que "leer -> controlar duplicados ->
            // agregar -> escribir" ocurre completo, sin que otro registro se meta en el medio.
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
            // Libera el bloqueo (en finally: también si hubo un error o un usuario duplicado),
            // así la siguiente petición que estaba esperando puede seguir.
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
            // Bloqueo compartido: otras lecturas pueden ocurrir a la vez, pero si alguien está
            // escribiendo, se espera a que termine (así nunca se lee un archivo a medio escribir).
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
