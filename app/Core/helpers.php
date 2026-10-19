<?php
declare(strict_types=1);

/**
 * Escapa un valor para imprimirlo en HTML de forma segura.
 * Uso en vistas: <?= e($titulo) ?>
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}