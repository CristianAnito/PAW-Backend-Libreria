<?php
declare(strict_types=1);

namespace App\Services\Covers;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Crea el almacenamiento de tapas que indica COVERS_DRIVER (config/covers.php).
 */
final class CoverStorageFactory
{
    /**
     * @param array $config config/covers.php
     * @param string $appUrl URL pública de la API (APP_URL), para las tapas locales.
     */
    public static function create(array $config, string $appUrl, LoggerInterface $logger): CoverStorage
    {
        return match ($config['driver'] ?? 'local') {
            'local'      => new LocalCoverStorage($config['local_dir'], $appUrl),
            'cloudinary' => new CloudinaryCoverStorage((string) ($config['cloudinary_url'] ?? ''), $logger),
            default      => throw new RuntimeException("COVERS_DRIVER desconocido: {$config['driver']} (usar local o cloudinary)."),
        };
    }
}
