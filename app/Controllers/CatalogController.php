<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Request, Response, Validator};
use App\Models\Book;
use App\Repositories\{BookAlreadyExistsException, BookRepositoryInterface};
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
     * GET /api/libros/todos
     * Todos los libros, sin filtrar, ordenar ni paginar: el frontend hace eso en JS.
     *
     * Respuesta: {"data": [libro...], "sorts": {clave: texto}}
     */
    public function all(Request $request): Response
    {
        return Response::json([
            'data'  => array_map(fn (Book $book) => $book->toArray(), $this->books()->search()),
            'sorts' => BookRepositoryInterface::SORTS,
        ]);
    }

    /**
     * POST /api/libros  {"titulo", "autor", "genero", "precio", "resumen", "sinopsis"}
     * Carga un libro nuevo. Solo el dueño de la librería.
     *
     * 201: {"data": libro}. 401: sin sesión. 403: no es el dueño. 422: datos inválidos o libro repetido.
     */
    public function store(Request $request): Response
    {
        $this->requireOwner($request);

        // Las reglas son las constantes de Book; el formulario del frontend valida lo mismo.
        $pattern = static fn (string $regex, string $message) =>
            static fn (string $value) => preg_match(Book::regex($regex), $value) === 1 ? null : $message;

        $errors = Validator::validate($request->body, [
            'titulo'   => ['required', 'min:' . Book::TITLE_MIN, 'max:' . Book::TITLE_MAX],
            'autor'    => ['required', 'min:' . Book::AUTHOR_MIN, 'max:' . Book::AUTHOR_MAX,
                $pattern(Book::AUTHOR_PATTERN, 'Usá solo letras, espacios, punto, apóstrofo y guion.')],
            'genero'   => ['required', 'min:' . Book::GENRE_MIN, 'max:' . Book::GENRE_MAX,
                $pattern(Book::GENRE_PATTERN, 'Usá solo letras, espacios, "/" y guion.')],
            'precio'   => ['required', 'integer', 'between:' . Book::PRICE_MIN . ',' . Book::PRICE_MAX],
            'resumen'  => ['required', 'min:' . Book::SUMMARY_MIN, 'max:' . Book::SUMMARY_MAX],
            'sinopsis' => ['required', 'min:' . Book::SYNOPSIS_MIN, 'max:' . Book::SYNOPSIS_MAX],
        ]);

        $title = self::text($request, 'titulo');
        $author = self::text($request, 'autor');
        if (!isset($errors['titulo']) && !isset($errors['autor'])
            && $this->books()->findByTitleAndAuthor($title, $author) !== null) {
            $errors['titulo'] = 'Ya existe un libro con ese título y autor.';
        }

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        try {
            $book = $this->books()->create(
                $title,
                $author,
                self::text($request, 'genero'),
                (float) self::text($request, 'precio'),
                self::text($request, 'resumen'),
                self::text($request, 'sinopsis'),
                // La tapa se agrega con la carga por arrastre (consigna 03). Sin tapa, el frontend
                // muestra la imagen genérica.
            );
        } catch (BookAlreadyExistsException $e) {
            // Otra carga del mismo libro se guardó entre el control de arriba y este momento.
            return $this->validationError(['titulo' => $e->getMessage()]);
        }

        // 201 Created: el libro nuevo, con su slug (el frontend lleva a /catalogo/{slug}).
        return Response::json(['data' => $book->toArray()], 201);
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
