<?php
declare(strict_types=1);

namespace App\Services\Covers;

use RuntimeException;

/**
 * Tapas en el disco del servidor (COVERS_DRIVER=local, para desarrollo).
 *
 * Se guardan en storage/covers/, fuera de public/: no se pueden pedir directamente por URL.
 * Las entrega CoverController (GET /api/tapas/{archivo}), que solo acepta los nombres que
 * genera esta clase. El nombre es aleatorio (nunca el del archivo original): no se puede
 * adivinar ni pisar otro archivo, y no puede contener "../" ni una extensión peligrosa como .php.
 */
final class LocalCoverStorage implements CoverStorage
{
    // Nombres que genera store(): 32 caracteres hexadecimales + extensión de imagen.
    public const FILENAME_PATTERN = '/^[a-f0-9]{32}\.(jpg|png|webp)$/';

    public function __construct(
        private string $directory,   // config/covers.php: local_dir
        private string $baseUrl,     // URL pública de la API (APP_URL), ej. http://localhost:8000
    ) {}

    public function store(CoverImage $image): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('No se pudo crear la carpeta de las tapas.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $image->extension();
        if (file_put_contents($this->directory . '/' . $filename, $image->bytes, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar la tapa.');
        }

        return rtrim($this->baseUrl, '/') . '/api/tapas/' . $filename;
    }

    /**
     * Ruta del archivo de una tapa guardada, o null si el nombre no es uno de los que genera
     * store() o el archivo no existe.
     */
    public function path(string $filename): ?string
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return null;
        }
        $path = $this->directory . '/' . $filename;
        return is_file($path) ? $path : null;
    }
}
