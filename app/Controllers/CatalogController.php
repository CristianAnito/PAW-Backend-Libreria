<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Request, Response, Validator};
use App\Models\Book;
use App\Repositories\{BookAlreadyExistsException, BookNotFoundException, BookRepositoryInterface};
use App\Services\{CsvExporter, Paginator};
use App\Services\Covers\{CoverImage, InvalidCoverException};
use RuntimeException;

/**
 * Catálogo de libros: listado con búsqueda y paginado, detalle, exportación CSV, y carga y edición (solo el dueño).
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
     * POST /api/libros  {"titulo", "autor", "genero", "precio", "resumen", "sinopsis", "tapa"?}
     * Carga un libro nuevo. Solo el dueño de la librería.
     *
     * "tapa" (opcional) es la imagen como data URL ("data:image/webp;base64,...", ver Services/Covers/CoverImage).
     * Se guarda solo si todo lo demás es válido; sin tapa, el frontend muestra la imagen genérica.
     *
     * 201: {"data": libro}. 401: sin sesión. 403: no es el dueño. 422: datos inválidos o libro repetido.
     * 503: no se pudo guardar la tapa (el libro no se crea: se puede reintentar).
     */
    public function store(Request $request): Response
    {
        $this->requireOwner($request);

        $errors = $this->validateBook($request);
        $cover = $this->coverFromRequest($request, $errors);
        if ($errors !== []) {
            return $this->validationError($errors);
        }

        // La tapa se guarda recién ahora, con todo lo demás validado: así no quedan imágenes
        // de libros que no se cargaron.
        try {
            $coverUrl = $cover !== null ? $this->covers()->store($cover) : null;
        } catch (RuntimeException) {
            return $this->coverNotSaved();
        }

        try {
            $book = $this->books()->create(
                self::text($request, 'titulo'),
                self::text($request, 'autor'),
                self::text($request, 'genero'),
                (float) self::text($request, 'precio'),
                self::text($request, 'resumen'),
                self::text($request, 'sinopsis'),
                $coverUrl,   // sin tapa (null), el frontend muestra la imagen genérica
            );
        } catch (BookAlreadyExistsException $e) {
            // Otra carga del mismo libro se guardó entre el control de validateBook() y este momento.
            return $this->validationError(['titulo' => $e->getMessage()]);
        }

        // 201 Created: el libro nuevo, con su slug (el frontend lleva a /catalogo/{slug}).
        return Response::json(['data' => $book->toArray()], 201);
    }

    /**
     * PATCH /api/libros/{slug}  {"titulo", "autor", "genero", "precio", "resumen", "sinopsis", "tapa"?}
     * Edita un libro: cualquiera del catálogo, también los del catálogo base. Solo el dueño.
     *
     * Se envían todos los campos del formulario (mismas reglas que al cargar). La tapa:
     * - sin "tapa" en el cuerpo: se mantiene la actual;
     * - "tapa": data URL -> se reemplaza;
     * - "tapa": null (o "") -> se quita (el frontend muestra la imagen genérica).
     * El slug (la dirección del libro) no cambia aunque cambie el título: así no se rompen los
     * enlaces compartidos ni los carritos que tienen ese libro.
     *
     * 200: {"data": libro}. 401: sin sesión. 403: no es el dueño. 404: el libro no existe.
     * 422: datos inválidos u otro libro con el mismo título y autor. 503: no se pudo guardar la tapa.
     */
    public function update(Request $request, string $slug): Response
    {
        $this->requireOwner($request);

        $current = $this->books()->findBySlug($slug);
        if ($current === null) {
            return $this->notFound('El libro solicitado no existe.');
        }

        $errors = $this->validateBook($request, $current->id);

        $cover = null;
        $coverUrl = $current->cover;
        if (array_key_exists('tapa', $request->body)) {
            $cover = $this->coverFromRequest($request, $errors);
            if ($cover === null && !isset($errors['tapa'])) {
                $coverUrl = null; // "tapa": null -> se quita
            }
        }

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        try {
            if ($cover !== null) {
                $coverUrl = $this->covers()->store($cover);
            }
        } catch (RuntimeException) {
            return $this->coverNotSaved();
        }

        $book = new Book(
            id: $current->id,
            slug: $current->slug,
            title: self::text($request, 'titulo'),
            author: self::text($request, 'autor'),
            genre: self::text($request, 'genero'),
            price: (float) self::text($request, 'precio'),
            summary: self::text($request, 'resumen'),
            synopsis: self::text($request, 'sinopsis'),
            cover: $coverUrl,
        );

        try {
            $this->books()->update($book);
        } catch (BookAlreadyExistsException $e) {
            return $this->validationError(['titulo' => $e->getMessage()]);
        } catch (BookNotFoundException $e) {
            return $this->notFound($e->getMessage());
        }

        return Response::json(['data' => $book->toArray()]);
    }

    /**
     * Valida los campos del formulario de un libro (al cargarlo o al editarlo).
     * Las reglas son las constantes de Book; el formulario del frontend valida lo mismo.
     *
     * @param int|null $bookId Al editar, el id del libro: puede conservar su propio título y autor.
     * @return array<string, string> Mensaje de error de cada campo inválido.
     */
    private function validateBook(Request $request, ?int $bookId = null): array
    {
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

        if (!isset($errors['titulo']) && !isset($errors['autor'])) {
            $same = $this->books()->findByTitleAndAuthor(self::text($request, 'titulo'), self::text($request, 'autor'));
            if ($same !== null && $same->id !== $bookId) {
                $errors['titulo'] = 'Ya existe un libro con ese título y autor.';
            }
        }

        return $errors;
    }

    /**
     * La tapa del cuerpo ("tapa": data URL), validada (tipo, peso y medidas reales de la imagen).
     * null si no vino (o vino vacía). Si es inválida, agrega el error en $errors['tapa'].
     */
    private function coverFromRequest(Request $request, array &$errors): ?CoverImage
    {
        $data = $request->input('tapa');
        if ($data === null || $data === '') {
            return null;
        }
        if (!is_string($data)) {
            $errors['tapa'] = 'El archivo no es una imagen válida.';   // ej. un array o un número
            return null;
        }
        try {
            return CoverImage::fromDataUrl($data);
        } catch (InvalidCoverException $e) {
            $errors['tapa'] = $e->getMessage();
            return null;
        }
    }

    // 503: no se pudo guardar la tapa (el detalle quedó en el log, ej. Cloudinary no responde).
    // El libro no se crea ni se modifica: se puede reintentar.
    private function coverNotSaved(): Response
    {
        return Response::error('No se pudo guardar la tapa. Intentá nuevamente en unos minutos.', 503);
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
