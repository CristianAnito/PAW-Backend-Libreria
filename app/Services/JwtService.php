<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\User;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Emite y verifica los JWT (JSON Web Token) de la sesión de usuario.
 *
 * Un JWT tiene tres partes separadas por puntos: header.payload.firma
 * - header:  algoritmo de firma ({"alg": "HS256", "typ": "JWT"}).
 * - payload: los datos ("claims"): quién es el usuario y hasta cuándo vale el token.
 * - firma:   HMAC-SHA256(header + payload, JWT_SECRET). Solo el servidor conoce el secreto.
 *
 * El payload NO está cifrado, solo firmado: cualquiera puede leerlo (es Base64), pero nadie
 * puede modificarlo sin que la firma deje de coincidir. Por eso nunca se guardan ahí datos
 * sensibles (contraseña, email, teléfono): solo el id y el nombre de usuario.
 */
final class JwtService
{
    // HS256 necesita una clave de al menos 256 bits = 32 bytes (php-jwt lo exige).
    private const MIN_SECRET_BYTES = 32;

    public function __construct(
        #[\SensitiveParameter] private string $secret,   // no aparece en los stack traces del log
        private int $ttl,
        private string $algorithm = 'HS256',
        private string $issuer = '',
    ) {
        // Sin secreto (o con uno corto) cualquiera podría fabricar tokens: se corta acá, con un
        // error que va al log; el usuario solo ve el 500 genérico.
        if (strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new RuntimeException('JWT_SECRET no está configurado o tiene menos de 32 caracteres (ver .env.example).');
        }
        if ($ttl <= 0) {
            throw new RuntimeException('JWT_TTL debe ser un número de segundos mayor a 0.');
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) Config::get('auth.jwt.secret'),
            (int) Config::get('auth.jwt.ttl'),
            (string) Config::get('auth.jwt.algorithm'),
            (string) Config::get('auth.jwt.issuer'),
        );
    }

    /**
     * Crea el token firmado para un usuario que acaba de registrarse o iniciar sesión.
     */
    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss'  => $this->issuer,           // issuer: quién emitió el token (esta API)
            'sub'  => (string) $user->id,      // subject: a quién identifica (el id del usuario)
            'name' => $user->username,         // para mostrar "Hola, ..." sin consultar el repositorio
            'iat'  => $now,                    // issued at: cuándo se emitió
            'exp'  => $now + $this->ttl,       // expiration: después de esta fecha el token no vale
        ], $this->secret, $this->algorithm);
    }

    /**
     * Verifica un token y devuelve sus datos, o null si no es válido.
     *
     * php-jwt comprueba: que tenga 3 partes, que el algoritmo sea el esperado (rechaza "none" y
     * cualquier otro), que la firma coincida, y que no esté vencido (exp) ni sea "del futuro" (iat).
     * Acá se agrega: que tenga vencimiento (php-jwt acepta tokens sin "exp"), que lo haya emitido
     * esta API (iss) y que el id sea un número.
     *
     * @return array{id: int, username: string, expires: int}|null
     */
    public function verify(string $token): ?array
    {
        try {
            // Key ata el secreto a UN algoritmo: un token que diga {"alg": "HS512"} o
            // {"alg": "none"} se rechaza aunque alguien lo haya armado a mano.
            $claims = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (UnexpectedValueException | DomainException | InvalidArgumentException) {
            // Mal formado, firma inválida (alterado o firmado con otro secreto), vencido, etc.
            // ExpiredException y SignatureInvalidException extienden UnexpectedValueException.
            return null;
        }

        if (!isset($claims->exp, $claims->sub)
            || ($claims->iss ?? null) !== $this->issuer
            || !is_string($claims->sub) || !ctype_digit($claims->sub)) {
            return null;
        }

        return [
            'id'       => (int) $claims->sub,
            'username' => (string) ($claims->name ?? ''),
            'expires'  => (int) $claims->exp,
        ];
    }

    // Duración del token en segundos (la cookie dura lo mismo).
    public function ttl(): int
    {
        return $this->ttl;
    }
}
