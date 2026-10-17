<?php
/**
 * Vista "Acerca de nosotros" (GET /nosotros).
 * Contenido estático tomado de nosotros/nosotros.html del frontend.
 * El <main> (con la clase nosotros-main), el header y el footer
 * los aporta layouts/main.php. El CSS lo carga AboutController ('extraCss').
 */
?>
<section class="presentacion-nosotros">
    <h1>Conocé PAWprints</h1>
    <p>
        Una librería creada por lectores, para lectores.
    </p>
</section>

<section class="informacion-nosotros">
    <h2>Conocé nuestra historia</h2>
    <div class="tarjetas-nosotros">
        <div class="tarjeta-interior">
            <h3>Nuestra historia</h3>
            <p>
                PAWprints nació como un proyecto del grupo
                "La 25", con el objetivo de crear un espacio
                dedicado a los amantes de la lectura.
            </p>
            <p>
                Buscamos ofrecer una selección variada de
                libros y generar un lugar donde los lectores
                puedan descubrir nuevas historias.
            </p>
        </div>

        <div class="tarjeta-interior">
            <h3>Nuestra misión</h3>
            <p>
                Nuestra misión es fomentar la lectura y
                acercar los libros a nuestra comunidad,
                ofreciendo una experiencia de compra
                sencilla y accesible.
            </p>
        </div>

        <div class="tarjeta-interior">
            <h3>Qué ofrecemos</h3>
            <ul>
                <li>Venta de libros</li>
                <li>Recomendaciones personalizadas</li>
                <li>Novedades literarias</li>
                <li>Pedidos de libros</li>
                <li>Actividades relacionadas con la lectura</li>
            </ul>
        </div>
    </div>
</section>

<section class="historia-timeline">
    <h2>El comienzo de PAWprints</h2>
    <div class="timeline">
        <article class="evento-timeline">
            <div class="punto-timeline"></div>
            <div class="contenido-timeline">
                <span>2026</span>
                <h3>Nace la idea</h3>
                <p>
                    Surge el proyecto de crear una librería
                    pensada para los amantes de la lectura.
                </p>
            </div>
        </article>

        <article class="evento-timeline">
            <div class="punto-timeline"></div>
            <div class="contenido-timeline">
                <span>LA 25</span>
                <h3>Se forma el equipo</h3>
                <p>
                    El proyecto comienza a tomar forma como
                    trabajo del grupo "La 25".
                </p>
            </div>
        </article>

        <article class="evento-timeline">
            <div class="punto-timeline"></div>
            <div class="contenido-timeline">
                <span>PAWPRINTS</span>
                <h3>Nuestro proyecto</h3>
                <p>
                    La idea se transforma en una experiencia
                    web para descubrir y comprar libros.
                </p>
            </div>
        </article>
    </div>
</section>

<section class="equipo">
    <h2>Conocé a La 25</h2>
    <p class="introduccion-equipo">
        Detrás de PAWprints está el equipo que hizo posible
        este proyecto.
    </p>

    <div class="tarjetas-equipo">
        <?php
        // Datos del equipo en un arreglo: agregar o quitar integrantes
        // no requiere tocar el HTML. Se escapan con e() por buena práctica.
        $integrantes = [
            ['nombre' => 'Trinidad Lopez',         'rol' => 'Desarrollador del proyecto'],
            ['nombre' => 'Valentino Aimale',       'rol' => 'Desarrollador del proyecto'],
            ['nombre' => 'Cristian Tomás Garcia',  'rol' => 'Desarrollador del proyecto'],
            ['nombre' => 'Ana Paula Romero',       'rol' => 'Desarrollador del proyecto'],
        ];
        ?>
        <?php foreach ($integrantes as $integrante): ?>
            <article class="integrante">
                <div class="foto-integrante">
                    <img src="/assets/media/sinfoto.png" alt="">
                </div>
                <h3><?= e($integrante['nombre']) ?></h3>
                <p><?= e($integrante['rol']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
