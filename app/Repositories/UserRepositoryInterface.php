<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * Contrato de acceso a los usuarios. El resto de la aplicación depende de esta
 * interfaz y no del origen de los datos (hoy un JSON; más adelante, la base de datos).
 *
 * Usuario y email se comparan sin distinguir mayúsculas: "Vale" y "vale" son el mismo usuario.
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByUsername(string $username): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * Guarda un usuario nuevo y lo devuelve con su id y fecha de registro.
     *
     * @throws UserAlreadyExistsException si el usuario o el email ya están registrados.
     *         Se controla en el mismo momento de guardar: si dos personas se registran a la vez
     *         con el mismo nombre, solo una lo consigue.
     */
    public function create(string $username, string $email, ?string $phone, string $passwordHash): User;
}
