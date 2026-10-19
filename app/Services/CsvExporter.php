<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Book;

/**
 * Representación CSV del catálogo (otra "representación de la información", junto al HTML).
 */
final class CsvExporter
{
    /**
     * @param Book[] $books
     */
    public function books(array $books): string
    {
        $out = fopen('php://temp', 'r+');

        // BOM UTF-8: hace que Excel muestre bien los acentos.
        fwrite($out, "\xEF\xBB\xBF");

        $this->row($out, ['ID', 'Título', 'Autor', 'Género', 'Precio']);
        foreach ($books as $book) {
            $this->row($out, [$book->id, $book->title, $book->author, $book->genre, $book->price]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function row($handle, array $fields): void
    {
        // Evita "inyección de fórmulas": Excel ejecuta celdas que empiezan con = + - @.
        $fields = array_map(
            fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v,
            $fields
        );
        // escape: '' sigue el estándar CSV (RFC 4180): las comillas se escapan duplicándolas.
        fputcsv($handle, $fields, ',', '"', '');
    }
}
