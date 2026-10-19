<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Request, Response};
use App\Models\Book;
use App\Repositories\BookRepositoryInterface;
use App\Services\{CsvExporter, Paginator};

/**
 * Catálogo de libros: listado con búsqueda y paginado, detalle y exportación CSV.
 */
final class CatalogController extends Controller
{
    /**
     * GET /api/libros?q=texto&orden=precio-asc&page=1
     *
     * Respuesta: {"data": [libro...], "meta": {paginado y filtros aplicados}, "sorts": {clave: texto}}
     */
    public function index(Request $request): Response
    {
        $query   = $this->searchQuery($request);
        $sort    = $this->sort($request);
        $perPage = (int) Config::get('catalog.per_page');
        $page    = $this->page($request);

        // ?page=abc o ?page=0: página inexistente.
        if ($page === null) {
            return $this->notFound('La página solicitada no existe.');
        }

        $paginator = new Paginator($this->books()->search($query, $sort), $page, $perPage);

        // ?page=99 con menos páginas: página inexistente.
        if (!$paginator->isValidPage()) {
            return $this->notFound('La página solicitada no existe.');
        }

        return Response::json([
            'data' => array_map(fn (Book $book) => $book->toArray(), $paginator->items()),
            'meta' => [
                'query'       => $query,
                'sort'        => $sort,
                'page'        => $paginator->page,
                'per_page'    => $paginator->perPage,
                'total'       => $paginator->total,
                'total_pages' => $paginator->totalPages,
            ],
            // Órdenes disponibles: el frontend arma el <select> con esta lista.
            'sorts' => BookRepositoryInterface::SORTS,
        ]);
    }

    /**
     * GET /api/libros/{slug}
     */
    public function show(Request $request, string $slug): Response
    {
        $book = $this->books()->findBySlug($slug);

        if ($book === null) {
            return $this->notFound('El libro solicitado no existe.');
        }

        return Response::json(['data' => $book->toArray()]);
    }

    /**
     * GET /api/libros/export.csv?q=texto&orden=precio-asc
     * Exporta todos los resultados de la búsqueda (sin paginar), en el orden elegido.
     */
    public function exportCsv(Request $request): Response
    {
        $books = $this->books()->search($this->searchQuery($request), $this->sort($request));

        return new Response((new CsvExporter())->books($books), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="catalogo.csv"',
        ]);
    }

    // Texto de búsqueda (?q=), sin espacios extremos y con largo máximo.
    private function searchQuery(Request $request): string
    {
        $q = $request->query('q', '');
        if (!is_string($q)) {
            return ''; // ?q[]=... (array) se ignora
        }
        return mb_substr(trim($q), 0, Config::get('catalog.max_query_length'));
    }

    // Orden (?orden=): solo se aceptan las claves definidas; cualquier otro valor se ignora.
    private function sort(Request $request): ?string
    {
        $sort = $request->query('orden');
        return is_string($sort) && array_key_exists($sort, BookRepositoryInterface::SORTS) ? $sort : null;
    }

    // Número de página (?page=): 1 si no viene; null si no es un entero >= 1.
    private function page(Request $request): ?int
    {
        $value = $request->query('page');
        if ($value === null || $value === '') {
            return 1;
        }
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $page === false ? null : $page;
    }
}
