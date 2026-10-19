<?php
/**
 * Devuelve el atributo aria-current si la URL coincide con la página actual.
 * $currentPath lo provee Controller::render() y está disponible porque
 * este archivo se incluye con require dentro del mismo ámbito de la vista.
 */
$active = static fn (string $url): string =>
    $currentPath === $url ? ' aria-current="page"' : '';
?>
<nav class="nav-principal">
    <div class="nav-grupo-izq">
        <a href="/"<?= $active('/') ?>>Inicio</a>
        <a href="/catalogo"<?= $active('/catalogo') ?>>Catalogo</a>
        <a href="/promociones"<?= $active('/promociones') ?>>Promociones</a>
        <a href="/nosotros"<?= $active('/nosotros') ?>>Nosotros</a>
        <a href="/contacto"<?= $active('/contacto') ?>>Contacto</a>
    </div>

    <!-- GET: la búsqueda es idempotente y enlazable (decisión de la consigna 4).
         El parámetro "q" coincide con el que leerá CatalogController. -->
    <form action="/catalogo" method="GET" role="search">
        <label for="buscar">Buscar</label>
        <input type="search" id="buscar" name="q" placeholder="Buscar libros..." required>
        <button type="submit">Buscar</button>
    </form>

    <div class="nav-grupo-der">
        <a href="/iniciar-sesion"<?= $active('/iniciar-sesion') ?>>Iniciar sesión</a>
        <a href="/registrarse"<?= $active('/registrarse') ?>>Registrarse</a>
        <a href="/carrito" class="nav-carrito<?= $currentPath === '/carrito' ? ' activo' : '' ?>">Carrito 🛒<?= !empty($cartCount) ? ' (' . e($cartCount) . ')' : '' ?></a>
    </div>
</nav>
