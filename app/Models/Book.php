<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Entidad Libro. Inmutable: se crea a partir de los datos del repositorio.
 *
 * Las constantes son las reglas de validación del formulario de "libros nuevos" (POST /api/libros).
 * El frontend (js/validaciones.js, LIBRO) repite los mismos valores: si se cambia una regla,
 * hay que cambiarla en los dos.
 */
final class Book
{
    public const TITLE_MIN = 2;
    public const TITLE_MAX = 120;

    public const AUTHOR_MIN = 2;
    public const AUTHOR_MAX = 80;
    public const AUTHOR_PATTERN = "[\\p{L} .'\\-]+";      // letras (con acentos), espacios, punto, apóstrofo y guion

    public const GENRE_MIN = 3;
    public const GENRE_MAX = 60;
    public const GENRE_PATTERN = "[\\p{L} \\/\\-]+";      // letras, espacios, "/" y guion (ej. "Ensayo / Psicología")

    public const PRICE_MIN = 100;                          // precio en pesos, entero
    public const PRICE_MAX = 1000000;

    public const SUMMARY_MIN = 10;                         // descripción corta (listado)
    public const SUMMARY_MAX = 200;
    public const SYNOPSIS_MIN = 20;                        // descripción larga (detalle)
    public const SYNOPSIS_MAX = 2000;

    public function __construct(
        public readonly int $id,
        public readonly string $slug,      // identificador para la URL: /api/libros/{slug}
        public readonly string $title,
        public readonly string $author,
        public readonly string $genre,
        public readonly float $price,
        public readonly string $summary,   // descripción corta (listado)
        public readonly string $synopsis,  // descripción larga (detalle)
        public readonly ?string $cover,    // nombre del archivo de la tapa (lo sirve el frontend), o null
    ) {
        
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            (string) $data['slug'],
            (string) $data['title'],
            (string) $data['author'],
            (string) ($data['genre'] ?? ''),
            (float) $data['price'],
            (string) ($data['summary'] ?? ''),
            (string) ($data['synopsis'] ?? ''),
            $data['cover'] ?? null,
        );
    }

    /**
     * Representación JSON del libro.
     * La tapa se envía como nombre de archivo: las imágenes son parte del frontend,
     * que arma la URL (media/libros/{cover}) o usa la imagen genérica si es null.
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'slug'     => $this->slug,
            'title'    => $this->title,
            'author'   => $this->author,
            'genre'    => $this->genre,
            'price'    => $this->price,
            'summary'  => $this->summary,
            'synopsis' => $this->synopsis,
            'cover'    => $this->cover,
        ];
    }

    /**
     * Identificador para la URL a partir del título: "El túnel (2ª ed.)" -> "el-tunel-2-ed".
     * No garantiza que sea único: eso lo controla el repositorio (agrega -2, -3...).
     */
    public static function slugify(string $title): string
    {
        $text = mb_strtolower(trim($title), 'UTF-8');
        // Sin acentos (a mano: no depende de extensiones de PHP que el servidor podría no tener).
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
        // Todo lo que no sea letra o número pasa a ser un guion (sin guiones repetidos ni en los extremos).
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
        $slug = rtrim(substr($slug, 0, 80), '-');

        return $slug !== '' ? $slug : 'libro';
    }

    /**
     * Patrón del atributo HTML "pattern" convertido a expresión regular de PHP
     * (el navegador lo compara contra el valor completo, por eso se agregan ^ y $).
     */
    public static function regex(string $pattern): string
    {
        return Order::regex($pattern);
    }
}
