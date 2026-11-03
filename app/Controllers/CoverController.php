<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Request, Response};
use App\Services\Covers\{CoverImage, LocalCoverStorage};

/**
 * Tapas guardadas en el disco del servidor (COVERS_DRIVER=local, desarrollo).
 * Con COVERS_DRIVER=cloudinary las tapas las entrega Cloudinary y esta ruta no se usa.
 */
final class CoverController extends Controller
{
    /**
     * GET /api/tapas/{nombre}.{tipo}   ej. /api/tapas/3f2a...9c.webp
     *
     * Solo entrega archivos con los nombres que genera LocalCoverStorage (nunca "../" ni otra
     * carpeta). 404 si no existe.
     */
    public function show(Request $request, string $nombre, string $tipo): Response
    {
        $storage = new LocalCoverStorage(Config::get('covers.local_dir'), Config::get('app.url'));
        $path = $storage->path("{$nombre}.{$tipo}");
        if ($path === null) {
            return $this->notFound('La tapa no existe.');
        }

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type'           => array_search($tipo, CoverImage::TYPES, true),
            // El navegador usa el Content-Type indicado y no "adivina" otro por el contenido
            // (un archivo con código escondido nunca se interpreta como HTML o JavaScript).
            'X-Content-Type-Options' => 'nosniff',
            // El nombre es aleatorio y el archivo nunca cambia: el navegador puede guardarlo un año.
            'Cache-Control'          => 'public, max-age=31536000, immutable',
        ]);
    }
}
