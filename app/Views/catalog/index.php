<?php
/**
 * Listado del catálogo (GET /catalogo).
 * Estructura tomada de catalogo/catalogo.html del frontend.
 *
 * Variables: $books (Book[] de la página actual), $paginator (Paginator),
 *            $query (texto buscado), $sort (orden elegido o null), $sortOptions.
 */

// Búsqueda y orden actuales, sin los vacíos: se conservan al paginar y al exportar.
$filters = array_filter(['q' => $query, 'orden' => $sort], static fn ($v) => $v !== '' && $v !== null);

// URL de una página del listado, conservando búsqueda y orden.
$url = static fn (int $page): string => '/catalogo?' . http_build_query($filters + ['page' => $page]);
?>
<h1>Catálogo</h1>

<aside>
    <h2>Filtros</h2>
    <!-- GET: la búsqueda no modifica datos y así el resultado se puede compartir o guardar como enlace. -->
    <form action="/catalogo" method="get">
        <fieldset>
            <legend>Búsqueda</legend>
            <label for="q-catalogo">Título o autor</label>
            <input type="search" id="q-catalogo" name="q" value="<?= e($query) ?>" maxlength="100">
        </fieldset>
        <fieldset>
            <legend>Orden</legend>
            <label for="orden">Ordenar por</label>
            <select id="orden" name="orden">
                <option value="">Predeterminado</option>
                <?php foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $value === $sort ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </fieldset>
        <button type="submit">Aplicar</button>
    </form>
</aside>

<div class="catalogo-info">
    <p>
        <?php if ($query !== ''): ?>
            <?= e($paginator->total) ?> resultado(s) para "<strong><?= e($query) ?></strong>"
            · <a href="/catalogo">Ver todos</a>
        <?php else: ?>
            <?= e($paginator->total) ?> libros en el catálogo
        <?php endif; ?>
    </p>
    <a href="/catalogo/export.csv<?= $filters ? '?' . e(http_build_query($filters)) : '' ?>" download>
        Descargar CSV
    </a>
</div>

<?php if ($books === []): ?>
    <p class="sin-resultados">No encontramos libros que coincidan con tu búsqueda.</p>
<?php else: ?>
    <section id="catalogo">
        <?php foreach ($books as $book): ?>
            <article>
                <a href="/catalogo/<?= e($book->slug) ?>">
                    <img src="<?= e($book->coverUrl()) ?>" alt="Portada de <?= e($book->title) ?>" width="150">
                </a>
                <h2><?= e($book->title) ?></h2>
                <p><?= e($book->author) ?></p>
                <p><strong>Precio:</strong> <?= e(money($book->price)) ?></p>
                <p><?= e($book->summary) ?></p>
                <form action="/carrito/agregar" method="post">
                    <input type="hidden" name="libro" value="<?= e($book->slug) ?>">
                    <button type="submit">Añadir al carrito</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($paginator->totalPages > 1): ?>
    <nav class="paginacion" aria-label="Páginas del catálogo">
        <ul>
            <?php if ($paginator->hasPrevious()): ?>
                <li><a href="<?= e($url($paginator->page - 1)) ?>" rel="prev">Anterior</a></li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $paginator->totalPages; $i++): ?>
                <li>
                    <?php if ($i === $paginator->page): ?>
                        <span aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= e($url($i)) ?>"><?= $i ?></a>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>

            <?php if ($paginator->hasNext()): ?>
                <li><a href="<?= e($url($paginator->page + 1)) ?>" rel="next">Siguiente</a></li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
