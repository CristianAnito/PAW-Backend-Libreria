<?php
/**
 * Vista de inicio (GET /).
 * Contenido estático tomado de index/index.html del frontend.
 * El <main>, el header y el footer los aporta layouts/main.php.
 * El CSS (index.css) se carga desde HomeController mediante 'extraCss'.
 */
?>
<div class="hero-banner">
    <h1>Bienvenidos a tu librería de confianza</h1>
    <p>En PAWPrints vas a encontrar tus libros y colecciones favoritas de manera simple, rápida y segura.</p>
</div>

<section class="info-callout reserva-callout">
    <h2>Comprá desde donde estés</h2>
    <p>
        Nuestra tienda online está disponible las 24 horas para que explores el catálogo completo y compres tus próximas lecturas sin salir de casa.
    </p>
</section>

<section>
    <h2>Nuestros libros más vendidos del mes</h2>
    <div class="grid-mas-vendidos">
        <!-- Rutas absolutas: ../media/ pasa a /assets/media/ -->
        <article>
            <img src="/assets/media/librosmasvendidos/libro1.png" alt="Portada de La psicología del dinero" width="100" height="100">
            <p>La psicología del dinero - Morgan Housel</p>
        </article>
        <article>
            <img src="/assets/media/librosmasvendidos/libro2.png" alt="Portada de La paciente silenciosa" width="100" height="100">
            <p>La paciente silenciosa - Alex Michaelides</p>
        </article>
        <article>
            <img src="/assets/media/librosmasvendidos/libro3.png" alt="Portada de El principito" width="100" height="100">
            <p>El principito - Antoine de Saint-Exupéry</p>
        </article>
    </div>
</section>

<div class="grid-dos-columnas">
    <section class="info-callout">
        <h2>Visitanos</h2>
        <p>
            Además de nuestra tienda online, podés encontrarnos en nuestra tienda física en:
            <strong>Av. Principal 123, Luján</strong>. ¡Vení a conocernos y charlar de libros!
        </p>
    </section>
    <section class="info-callout">
        <h2>Descubrí</h2>
        <p>
            Tenemos novedades todas las semanas. Descubrí las últimas incorporaciones a nuestro catálogo y promociones exclusivas de la página para nuestra comunidad lectora.
        </p>
    </section>
</div>