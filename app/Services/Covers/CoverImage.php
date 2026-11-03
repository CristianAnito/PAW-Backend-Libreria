<?php
declare(strict_types=1);

namespace App\Services\Covers;

/**
 * Imagen de tapa recibida en POST /api/libros, ya validada.
 *
 * Llega dentro del JSON como "data URL": "data:image/webp;base64,UklGR...". Se usa JSON (y no un
 * formulario multipart) porque la API solo acepta JSON en los POST: es una de las protecciones
 * contra CSRF (ver Core/CsrfGuard).
 *
 * No se confía en lo que dice el navegador (ni el tipo de la data URL ni el nombre del archivo):
 * getimagesizefromstring() lee los bytes y devuelve el tipo real y las medidas. Un archivo que no
 * es una imagen, o que dice ser PNG y es otra cosa, se rechaza.
 *
 * El frontend (js/validaciones.js, TAPA) repite estos límites para validar al soltar el archivo.
 */
final class CoverImage
{
    public const MAX_BYTES = 2 * 1024 * 1024;   // 2 MB
    public const MIN_SIDE = 200;                 // píxeles, ancho y alto mínimos (una tapa más chica se ve pixelada)
    public const MAX_SIDE = 5000;                // píxeles: evita imágenes enormes que ocupan mucha memoria al procesarlas

    // Tipos aceptados => extensión del archivo guardado.
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private function __construct(
        public readonly string $bytes,
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
    ) {}

    public function extension(): string
    {
        return self::TYPES[$this->mime];
    }

    /** La imagen como data URL (así la recibe Cloudinary). */
    public function toDataUrl(): string
    {
        return "data:{$this->mime};base64," . base64_encode($this->bytes);
    }

    /**
     * Valida la data URL y devuelve la imagen.
     *
     * @throws InvalidCoverException con un mensaje para mostrar al usuario.
     */
    public static function fromDataUrl(string $dataUrl): self
    {
        // Antes de decodificar: un texto más largo que una imagen de MAX_BYTES no se procesa
        // (base64 ocupa 4 caracteres por cada 3 bytes, más el encabezado "data:...;base64,").
        if (strlen($dataUrl) > intdiv(self::MAX_BYTES * 4, 3) + 100) {
            throw new InvalidCoverException(self::tooBig());
        }

        if (preg_match('#^data:(image/[a-z+.-]+);base64,([A-Za-z0-9+/]+={0,2})$#', $dataUrl, $m) !== 1) {
            throw new InvalidCoverException('El archivo no es una imagen válida.');
        }

        $bytes = base64_decode($m[2], true);   // true: falla con caracteres que no son base64
        if ($bytes === false || $bytes === '') {
            throw new InvalidCoverException('El archivo no es una imagen válida.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new InvalidCoverException(self::tooBig());
        }

        // Tipo y medidas reales, leídos de los bytes (el "@" evita el aviso de PHP si no es una imagen).
        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? null;
        if ($info === false || !isset(self::TYPES[$mime])) {
            throw new InvalidCoverException('Tiene que ser una imagen JPG, PNG o WebP.');
        }
        // La data URL dice un tipo y los bytes son de otro: el archivo fue renombrado o alterado.
        if ($m[1] !== $mime) {
            throw new InvalidCoverException('El archivo no es una imagen válida.');
        }

        [$width, $height] = $info;
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE) {
            throw new InvalidCoverException('La imagen es muy chica: tiene que medir al menos '
                . self::MIN_SIDE . ' × ' . self::MIN_SIDE . ' píxeles.');
        }
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new InvalidCoverException('La imagen es muy grande: puede medir hasta '
                . self::MAX_SIDE . ' × ' . self::MAX_SIDE . ' píxeles.');
        }

        return new self($bytes, $mime, $width, $height);
    }

    private static function tooBig(): string
    {
        return 'La imagen pesa más de ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.';
    }
}
