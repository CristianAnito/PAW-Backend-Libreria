<?php
declare(strict_types=1);

namespace App\Services\Covers;

use RuntimeException;

/**
 * Dónde se guardan las tapas que sube el dueño (patrón Strategy, como Core/Mail).
 * CoverStorageFactory elige una según COVERS_DRIVER (.env):
 * - local:      en storage/covers/ del servidor; la API las sirve en GET /api/tapas/{archivo} (desarrollo).
 * - cloudinary: en Cloudinary, un servicio de imágenes (producción: el disco de Render se borra
 *               en cada deploy o reinicio).
 *
 * Quien guarda la tapa (CatalogController) no sabe cuál se usa: cambiar de servicio
 * es agregar una clase que implemente esta interfaz.
 */
interface CoverStorage
{
    /**
     * Guarda la imagen y devuelve la URL pública (absoluta) con la que se muestra.
     *
     * @throws RuntimeException si no se pudo guardar. El mensaje no incluye datos sensibles.
     */
    public function store(CoverImage $image): string;
}
