<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Pedido de compra: datos del cliente + renglones del carrito.
 * El pago es solo con Mercado Pago, por eso no se piden datos de pago.
 *
 * Las constantes definen las reglas de validación del servidor (Validator).
 * El formulario del frontend (js/validaciones.js) repite los mismos valores en sus
 * atributos minlength, pattern...: si se cambia una regla, hay que cambiarla en los dos.
 */
final class Order
{
    public const NAME_MIN = 2;
    public const NAME_MAX = 50;
    public const NAME_PATTERN = "[\\p{L} ']+";      // letras (con acentos), espacios y apóstrofo

    public const DNI_PATTERN = '[0-9]{7,8}';        // igual que el formulario del frontend

    public const EMAIL_MAX = 100;

    public const PHONE_MIN = 8;
    public const PHONE_MAX = 20;
    public const PHONE_PATTERN = '\\+?[0-9 ]+';     // dígitos y espacios, con + inicial opcional

    public const PAYMENT_METHOD = 'Mercado Pago';

    /**
     * @param array<int, array{book: Book, quantity: int, subtotal: float}> $lines
     */
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $dni,
        public readonly string $email,
        public readonly string $phone,
        public readonly array $lines,
        public readonly float $total,
    ) {}

    /**
     * Patrón del atributo HTML "pattern" convertido a expresión regular de PHP.
     * El navegador lo compara contra el valor completo, por eso se agregan ^ y $.
     */
    public static function regex(string $pattern): string
    {
        return '/^(?:' . $pattern . ')$/u';
    }
}
