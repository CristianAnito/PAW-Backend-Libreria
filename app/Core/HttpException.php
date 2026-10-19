<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Error esperado que termina la petición con un código HTTP y un mensaje para el cliente
 * (ej. 401 "Necesitás iniciar sesión"). App la convierte en la respuesta JSON de error.
 *
 * A diferencia de cualquier otra excepción (que es un error interno: se registra en el log y
 * se responde un 500 genérico), el mensaje de esta SÍ se muestra al usuario: nunca debe
 * contener datos internos (rutas, consultas, nombres de archivos).
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
