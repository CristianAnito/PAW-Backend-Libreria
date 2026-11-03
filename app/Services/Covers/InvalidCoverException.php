<?php
declare(strict_types=1);

namespace App\Services\Covers;

use InvalidArgumentException;

/**
 * La imagen de tapa no es válida (tipo, peso o medidas). El mensaje es para el usuario:
 * el controlador lo devuelve como error del campo "tapa" (422).
 */
final class InvalidCoverException extends InvalidArgumentException
{
}
