<?php
/**
 * Detalle de un libro (GET /catalogo/{slug}).
 * Estructura tomada de libros/*.html del frontend.
 *
 * Variables: $book (Book).
 */
?>
<article>
    <section>
        <img src="<?= e($book->coverUrl()) ?>" alt="Portada de <?= e($book->title) ?>" width="300">
    </section>
    <section>
        <h1><?= e($book->title) ?></h1>
        <h2><?= e($book->author) ?></h2>
        <p><?= e($book->summary) ?></p>

        <h3>Sinopsis</h3>
        <p><?= e($book->synopsis) ?></p>

        <h3>Información del libro</h3>
        <ul>
            <li>Autor: <?= e($book->author) ?></li>
            <li>Género: <?= e($book->genre) ?></li>
        </ul>
        <p>Precio: <?= e(money($book->price)) ?></p>

        <form action="/carrito/agregar" method="post">
            <input type="hidden" name="libro" value="<?= e($book->slug) ?>">
            <button type="submit" class="btn-carrito">Añadir al carrito</button>
        </form>
        <p><a href="/catalogo">← Volver al catálogo</a></p>
    </section>
</article>
