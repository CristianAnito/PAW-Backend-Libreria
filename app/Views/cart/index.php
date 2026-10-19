<?php
/**
 * Carrito (GET /carrito). Estructura tomada de carrito/carrito.html del frontend.
 *
 * Variables: $lines (renglones: book, quantity, subtotal), $total, $maxQuantity,
 *            $error (mensaje de un solo uso o null).
 */
?>
<section class="seccion-carrito" aria-labelledby="carrito-heading">
    <h1 id="carrito-heading">Carrito de compras</h1>

    <?php if ($error !== null): ?>
        <p class="alerta-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($lines === []): ?>
        <p>Tu carrito está vacío. <a href="/catalogo">Ver el catálogo</a></p>
    <?php endif; ?>

    <?php foreach ($lines as $i => $line): $book = $line['book']; ?>
        <article>
            <figure>
                <img src="<?= e($book->coverUrl()) ?>" alt="Portada de <?= e($book->title) ?>">
            </figure>
            <h3><?= e($book->title) ?> — <?= e($book->author) ?></h3>
            <p>Precio: <data value="<?= e($book->price) ?>"><?= e(money($book->price)) ?></data></p>
            <form action="/carrito/actualizar" method="post">
                <input type="hidden" name="libro" value="<?= e($book->slug) ?>">
                <label for="cantidad-<?= $i ?>">Cantidad</label>
                <input type="number" id="cantidad-<?= $i ?>" name="cantidad"
                       value="<?= e($line['quantity']) ?>" min="1" max="<?= e($maxQuantity) ?>" required>
                <button type="submit">Actualizar</button>
            </form>
            <form action="/carrito/eliminar" method="post">
                <input type="hidden" name="libro" value="<?= e($book->slug) ?>">
                <button type="submit">Eliminar</button>
            </form>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($lines !== []): ?>
    <section class="seccion-resumen" aria-labelledby="resumen-heading">
        <h2 id="resumen-heading">Resumen de compra</h2>
        <dl>
            <?php foreach ($lines as $line): ?>
                <dt><?= e($line['book']->title) ?> × <?= e($line['quantity']) ?></dt>
                <dd><?= e(money($line['subtotal'])) ?></dd>
            <?php endforeach; ?>
            <dt>Total</dt>
            <dd><?= e(money($total)) ?></dd>
        </dl>
        <a href="/compra">Finalizar compra</a>
    </section>
<?php endif; ?>
