<?php
declare(strict_types=1);

/**
 * Escapa un valor para imprimirlo en HTML de forma segura.
 * Uso en vistas: <?= e($titulo) ?>
 *
 * ENT_QUOTES: escapa comillas simples y dobles (seguro dentro de atributos).
 * ENT_SUBSTITUTE: reemplaza bytes UTF-8 inválidos en lugar de devolver "".
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}