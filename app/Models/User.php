<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Usuario registrado. Inmutable: se crea a partir de los datos del repositorio.
 *
 * Las constantes definen las reglas de validación del servidor (registro e inicio de sesión).
 * El formulario del frontend (js/validaciones.js) repite los mismos valores en sus
 * atributos minlength, pattern...: si se cambia una regla, hay que cambiarla en los dos.
 */
final class User
{
    public const USERNAME_MIN = 3;
    public const USERNAME_MAX = 30;
    public const USERNAME_PATTERN = '[A-Za-z0-9_.]+';   // letras sin acentos, números, "_" y "."

    public const EMAIL_MAX = 100;

    // Teléfono opcional, con las mismas reglas que el formulario de compra.
    public const PHONE_MIN = Order::PHONE_MIN;
    public const PHONE_MAX = Order::PHONE_MAX;
    public const PHONE_PATTERN = Order::PHONE_PATTERN;

    public const PASSWORD_MIN = 8;
    // password_hash (bcrypt) solo usa los primeros 72 BYTES de la contraseña e ignora el resto.
    // Una letra con acento ocupa 2 bytes, así que el límite se controla en bytes (ver AuthController).
    public const PASSWORD_MAX_BYTES = 72;
    public const PASSWORD_PATTERN = '(?=.*\p{L})(?=.*\d).+';   // al menos una letra y un número

    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $passwordHash,   // resultado de password_hash(); nunca la contraseña
        public readonly string $createdAt,      // fecha de registro, ISO 8601
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            (string) $data['username'],
            (string) $data['email'],
            isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
            (string) $data['password_hash'],
            (string) $data['created_at'],
        );
    }

    /**
     * Datos para guardar en el repositorio (incluye el hash de la contraseña).
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'username'      => $this->username,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'password_hash' => $this->passwordHash,
            'created_at'    => $this->createdAt,
        ];
    }

    /**
     * Datos que se pueden enviar al frontend: nunca el hash de la contraseña.
     */
    public function toPublicArray(): array
    {
        return [
            'id'       => $this->id,
            'username' => $this->username,
            'email'    => $this->email,
            'phone'    => $this->phone,
        ];
    }

    /**
     * Patrón del atributo HTML "pattern" convertido a expresión regular de PHP
     * (el navegador lo compara contra el valor completo, por eso se agregan ^ y $).
     */
    public static function regex(string $pattern): string
    {
        return Order::regex($pattern);
    }
}
