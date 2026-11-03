<?php
declare(strict_types=1);

namespace App\Services\Covers;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tapas en Cloudinary (COVERS_DRIVER=cloudinary, producción).
 * https://cloudinary.com/documentation/image_upload_api_reference#upload
 *
 * La API sube la imagen con un POST por HTTPS (como BrevoTransport con los mails). La subida
 * va "firmada": se calcula un hash con el API secret, que nunca sale del servidor. Por eso la
 * imagen pasa por la API y no se sube desde el navegador: ahí la clave quedaría a la vista.
 *
 * Configuración: CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME (la copia el panel
 * de Cloudinary, en "API Keys"). Plan gratis: 25 GB, sin tarjeta.
 */
final class CloudinaryCoverStorage implements CoverStorage
{
    private const FOLDER = 'pawprints/tapas';

    // Transformación al entregar la imagen: hasta 600 px de ancho (en la página se muestra a 300 px,
    // 600 se ve nítida en pantallas de alta densidad), formato y calidad según el navegador (ej. WebP o AVIF).
    private const DELIVERY = 'c_limit,w_600,f_auto,q_auto';

    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct(
        string $cloudinaryUrl,          // config/covers.php: cloudinary_url
        private LoggerInterface $logger,
    ) {
        $parts = parse_url($cloudinaryUrl);
        if (($parts['scheme'] ?? '') !== 'cloudinary' || empty($parts['user']) || empty($parts['pass']) || empty($parts['host'])) {
            // Sin el valor: tiene el API secret.
            $logger->error('CLOUDINARY_URL falta o no es válida: no se pueden guardar tapas.');
            throw new RuntimeException('CLOUDINARY_URL no es válida (formato: cloudinary://API_KEY:API_SECRET@CLOUD_NAME).');
        }
        $this->apiKey = urldecode($parts['user']);
        $this->apiSecret = urldecode($parts['pass']);
        $this->cloudName = $parts['host'];
    }

    public function store(CoverImage $image): string
    {
        // Parámetros firmados: los que se envían, salvo file, api_key y signature, ordenados por nombre.
        $params = ['folder' => self::FOLDER, 'timestamp' => time()];
        ksort($params);
        $signature = sha1(urldecode(http_build_query($params)) . $this->apiSecret);

        $curl = curl_init("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload");
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params + [
                'file'      => $image->toDataUrl(),
                'api_key'   => $this->apiKey,
                'signature' => $signature,      // el API secret no viaja: solo el hash
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,        // si Cloudinary no responde, el dueño no espera para siempre
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $networkError = curl_error($curl);
        $data = is_string($response) ? json_decode($response, true) : null;
        $url = $data['secure_url'] ?? null;

        if ($status !== 200 || !is_string($url) || !str_contains($url, '/image/upload/')) {
            // Cloudinary explica el problema en {"error": {"message": "..."}} (ej. clave inválida).
            // Va al log; al usuario, un mensaje genérico.
            $this->logger->error('Falló la subida de una tapa a Cloudinary', [
                'status' => $status,
                'error'  => $networkError !== '' ? $networkError : ($data['error']['message'] ?? 'sin detalle'),
            ]);
            throw new RuntimeException('No se pudo guardar la tapa.');
        }

        $this->logger->info('Tapa subida a Cloudinary', ['public_id' => $data['public_id'] ?? null]);

        // https://res.cloudinary.com/{nube}/image/upload/v123/pawprints/tapas/abc.jpg
        // -> https://res.cloudinary.com/{nube}/image/upload/c_limit,w_600,f_auto,q_auto/v123/pawprints/tapas/abc.jpg
        return str_replace('/image/upload/', '/image/upload/' . self::DELIVERY . '/', $url);
    }
}
