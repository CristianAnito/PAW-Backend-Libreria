# Cómo probar PAWPrints

PAWPrints — Entrega 4 · Grupo La 25

El proyecto tiene dos partes, en repositorios separados (rama `main`):

| Parte | Repositorio | Qué es |
|---|---|---|
| **Backend** (este repo) | https://github.com/CristianAnito/PAW-Backend-Libreria | API en PHP (MVC, sin framework) que responde JSON |
| **Frontend** | https://github.com/romeroppau/PAW--Frontend | SPA en HTML, CSS y JavaScript que consume la API |

Hay tres formas de probarlo:

- **A. En producción**, sin instalar nada: **https://pawprints-alpha.vercel.app**
- **B. En local, sin Docker** (PHP y Composer)
- **C. En local, con Docker** (no hace falta tener PHP instalado)

Las funcionalidades a probar, con los pasos, están en la sección [Qué probar](#qué-probar-entrega-4).

### Usuarios: cliente y dueño de la librería

- **Cliente:** cualquiera que se registre desde el sitio. Puede comprar; no puede cargar ni editar libros.
- **Dueño de la librería:** el único que puede **cargar y editar libros**, y no compra. No se registra desde el sitio (si se pudiera, cualquiera podría hacerse dueño): la API crea su cuenta con las variables `OWNER_USERNAME`, `OWNER_EMAIL` y `OWNER_PASSWORD_HASH`.
  - En **producción**, el usuario y la contraseña del dueño se envían aparte (por un comentario privado de Classroom), no en el repositorio.
  - En **local**, el dueño se define en `.env` con los datos que elija quien lo pruebe (ver B.2 o C.2).

---

## A. En producción

Abrir **https://pawprints-alpha.vercel.app**.

- El frontend está en **Vercel**; la API, en **Render** (contenedor Docker); las tapas que se suben, en **Cloudinary**.
- El plan gratuito de Render **se suspende tras 15 minutos sin uso**: la primera carga después de eso puede tardar entre 30 y 60 segundos.
- **Importante:** por ahora la API guarda los datos en archivos del disco del servidor, y **el disco de Render se borra cada vez que el servicio se reinicia** (al despertar después de 15 minutos sin uso, o al publicar una versión nueva). Por eso, en producción, **los libros que se carguen o editen y los usuarios que se registren se pierden** al reiniciarse. Las **tapas** sí quedan guardadas (están en Cloudinary), y la cuenta del dueño se vuelve a crear sola. Con la base de datos (próxima entrega) los datos van a persistir.

Para probar todo en una sola sesión, conviene hacer las pruebas de corrido, sin dejar el sitio sin uso más de 15 minutos.

---

## B. En local, sin Docker

### B.1 Requisitos

- **PHP 8.1 o superior**, con las extensiones `curl`, `mbstring` y `openssl` (vienen activadas en la mayoría de las instalaciones).
- **Composer**.
- Un navegador. Para servir el frontend alcanza con PHP (o la extensión *Live Server* de VS Code).

### B.2 Backend (API)

```bash
git clone https://github.com/CristianAnito/PAW-Backend-Libreria.git
cd PAW-Backend-Libreria
composer install
cp .env.example .env          # en Windows (cmd): copy .env.example .env
```

Editar `.env` y completar:

| Variable | Qué poner |
|---|---|
| `JWT_SECRET` | Clave para firmar las sesiones. Generarla con `php -r "echo bin2hex(random_bytes(32));"` |
| `OWNER_USERNAME` | Usuario del dueño de la librería, ej. `dueno` |
| `OWNER_EMAIL` | Su email, ej. `dueno@libreria.com` |
| `OWNER_PASSWORD_HASH` | El **hash** de la contraseña elegida (nunca la contraseña). Generarlo con `php -r "echo password_hash('la-contraseña', PASSWORD_DEFAULT);"` y pegar el resultado completo (empieza con `$2y$`) |

El resto de los valores de `.env.example` ya sirven para probar en local:

- `COVERS_DRIVER=local`: las tapas se guardan en `storage/covers/` (no hace falta Cloudinary).
- `MAIL_DRIVER=log`: el mail de cada compra no se envía; se escribe en `storage/logs/app.log`.

Levantar la API:

```bash
php -S localhost:8000 -t public public/index.php
```

Comprobar que responde: http://localhost:8000/api/libros (devuelve los libros en JSON).

### B.3 Frontend

En otra terminal:

```bash
git clone https://github.com/romeroppau/PAW--Frontend.git
cd PAW--Frontend
php -S localhost:5501
```

(Alternativa: abrir la carpeta en VS Code y usar *Open with Live Server*; ya está configurado en el puerto 5501.)

Abrir **http://localhost:5501**.

> **Importante:** usar `localhost` y no `127.0.0.1`, tanto para el frontend como para la API. El frontend busca la API en el puerto **8000** del mismo host, y las cookies de sesión solo viajan si los dos usan el mismo nombre.

En local, los libros cargados o editados se guardan en `storage/data/libros-cargados.json` y las tapas en `storage/covers/` (los dos fuera de git). Para volver al catálogo original, borrar ese archivo y esa carpeta.

---

## C. En local, con Docker

Requisito: **Docker Desktop** (o Docker Engine) funcionando. Los puertos **8000** y **5501** tienen que estar libres.

### C.1 Descargar los dos repositorios

```bash
git clone https://github.com/CristianAnito/PAW-Backend-Libreria.git
git clone https://github.com/romeroppau/PAW--Frontend.git
```

### C.2 Configuración de la API

```bash
cd PAW-Backend-Libreria
cp .env.example .env          # en Windows (cmd): copy .env.example .env
```

Completar en `.env` las mismas variables que en B.2 (`JWT_SECRET`, `OWNER_USERNAME`, `OWNER_EMAIL`, `OWNER_PASSWORD_HASH`). Si no se tiene PHP instalado, los valores se pueden generar con la imagen oficial de PHP:

```bash
docker run --rm php:8.4-cli php -r "echo bin2hex(random_bytes(32));"
docker run --rm php:8.4-cli php -r "echo password_hash('la-contraseña', PASSWORD_DEFAULT);"
```

> En `.env`, escribir los valores **sin comillas**: Docker los toma tal cual (las comillas pasarían a ser parte del valor).

### C.3 Levantar la API

Desde `PAW-Backend-Libreria`:

```bash
docker build -t pawprints-api .
docker run --rm --name pawprints-api -p 8000:80 --env-file .env pawprints-api
```

Es la misma imagen que corre en Render (PHP 8.4 + Apache, solo `public/` expuesto). La primera construcción tarda unos minutos. Comprobar que responde: http://localhost:8000/api/libros.

### C.4 Levantar el frontend

En otra terminal, desde la carpeta `PAW--Frontend` (el frontend son archivos estáticos: los sirve nginx):

```bash
# PowerShell
docker run --rm --name pawprints-front -p 5501:80 -v "${PWD}:/usr/share/nginx/html:ro" nginx:1.27-alpine

# Linux / macOS / Git Bash
docker run --rm --name pawprints-front -p 5501:80 -v "$(pwd):/usr/share/nginx/html:ro" nginx:1.27-alpine

# Windows cmd
docker run --rm --name pawprints-front -p 5501:80 -v "%cd%:/usr/share/nginx/html:ro" nginx:1.27-alpine
```

Abrir **http://localhost:5501** (con `localhost`, igual que en B.3).

### C.5 Notas sobre Docker

- Los libros cargados, las tapas y los usuarios quedan **dentro del contenedor**: se pierden al detenerlo (`Ctrl + C`). Es lo mismo que pasa en Render.
- El mail de cada compra (consigna 05 de la entrega 3) se ve en el log del contenedor:
  ```bash
  docker exec pawprints-api tail -n 40 storage/logs/app.log
  ```

---

## Qué probar (entrega 4)

En local, las direcciones empiezan con `http://localhost:5501`; en producción, con `https://pawprints-alpha.vercel.app`.

### Consigna 01 — Carrusel propio

En **Inicio**, el carrusel de libros destacados (`js/lib/carousel.js`, sin librerías externas):

- Al cargar, una barra muestra el porcentaje de descarga de las imágenes; al llegar a 100 % empieza a avanzar solo.
- Efectos de transición (deslizar, fundido, zoom, giro), elegidos al azar en cada cambio.
- Se maneja con los botones, las miniaturas, las flechas del teclado y deslizando con el dedo (o arrastrando con el mouse).
- Botón de **pantalla completa**.

### Consigna 02 — Formulario de libros nuevos, con validaciones en línea iguales en todos los navegadores

1. Iniciar sesión con el **dueño** de la librería. En el menú aparece **Cargar libro**.
2. Probar la validación en línea:
   - Tocar *Cargar libro* sin completar nada: aparece el error de cada campo y el foco va al primero.
   - El error de un campo aparece **al salir del campo** y desaparece **mientras se corrige**.
   - **Autor** `R2D2`: rechazado; `Gabriel García Márquez`: aceptado.
   - **Precio** `50`, `2000000` o `1.500`: rechazados (entero entre $100 y $1.000.000, sin puntos).
   - **Resumen** y **Sinopsis** tienen contador de caracteres.
3. **Abrir el mismo formulario en otro navegador** (ej. Chrome y Firefox): los mensajes, el momento en que aparecen y cómo se ven son **los mismos**. El formulario usa `novalidate` y la validación la hace un componente propio (`js/lib/validador.js`), en lugar de los globos de cada navegador.
4. Cargar un libro válido: lleva a su página con el aviso *"El libro se cargó correctamente"*, y aparece en el catálogo.
5. Cargar el **mismo libro** otra vez (aunque sea con otras mayúsculas o sin acentos): la API lo rechaza y el error se muestra en el campo *Título*.
6. **Permisos:** con un **cliente** (cualquier usuario registrado), `#/libros/nuevo` muestra *"No tenés permiso"*, y la API rechaza la carga (403) aunque se intente directamente. Sin sesión, lleva a iniciar sesión. El dueño, en cambio, no ve el carrito ni *Añadir al carrito*: no compra.

### Consigna 03 — Drag & drop para cargar las tapas

En *Cargar libro* (o al editar un libro), grupo **Tapa (opcional)**:

1. **Arrastrar una imagen** desde el explorador de archivos y soltarla en la zona punteada: mientras se arrastra la zona se resalta; al soltar aparece la **vista previa** con el nombre, el peso y las medidas.
2. La misma zona funciona **sin arrastrar**: con clic, o con el teclado (Tab hasta la zona y Enter), se abre el selector de archivos. En el celular dice *"Tocá para elegir una imagen"*.
3. Validación en línea de la imagen: una imagen **chica** (menos de 200 × 200 px), un **GIF o un PDF**, o una imagen de **más de 2 MB** muestran el error en el momento. El tipo y las medidas se leen de la imagen real: un archivo de texto renombrado a `.png` se rechaza.
4. **Quitar imagen** vuelve atrás.
5. Al cargar el libro, la tapa se ve en su página y en el catálogo. En producción, la dirección de la imagen empieza con `https://res.cloudinary.com/...`.
6. Soltar un archivo **fuera** de la zona no hace nada (el navegador no abre la imagen ni se pierde lo escrito en el formulario).

### Consigna 04 — Filtros: orden, rango de precio y paginado configurable

En **Catálogo**, panel *Filtros y orden*:

- **Orden** por precio, título o autor (ascendente y descendente).
- **Rango de precio** (desde / hasta). Si *Desde* es mayor que *Hasta*, se avisa junto a los campos.
- **Libros por página**: 6, 12 o 24. Paginado tradicional (la consigna permite infinito o tradicional).
- Los filtros se aplican mientras se escribe y quedan en la URL (se mantienen al recargar o al volver desde un libro). *Limpiar filtros* los vacía.
- *Descargar CSV* exporta los resultados con los filtros aplicados.

### Consigna 05 — Últimas 5 búsquedas en `localStorage`

Las búsquedas recientes aparecen recién después de buscar algo (con el historial vacío no se muestra nada).

1. Buscar desde el **menú** (ej. `borges`) y desde el buscador del **catálogo** (Enter o al salir del campo).
2. En **Catálogo**, en el panel *Filtros y orden*, **debajo del buscador "Título o autor"**, aparece **Búsquedas recientes**: tocar una la vuelve a aplicar. (En el celular el panel arranca cerrado: tocar *Filtros y orden* para abrirlo.)
3. Los dos buscadores **sugieren** las búsquedas recientes: hacer clic en el buscador vacío y apretar la flecha abajo (↓), o empezar a escribir.
4. Hacer más de 5 búsquedas: quedan las **5 más recientes**, sin repetidas (`Cortázar` y `CORTAZAR` cuentan como una).
5. Cerrar y volver a abrir el navegador: siguen ahí.
6. **Borrar búsquedas recientes** las elimina.
7. Ver lo guardado en `localStorage` (clave `busquedas:recientes`, una lista en JSON):
   - **Chrome / Edge:** F12 → pestaña **Application** (o *Aplicación*; si no se ve, está dentro de `»` en la barra de pestañas) → *Storage* → *Local storage* → el sitio.
   - **Firefox:** F12 → pestaña **Almacenamiento** (*Storage*) → *Almacenamiento local* → el sitio.
   - **Cualquier navegador:** F12 → *Console* → escribir `localStorage.getItem('busquedas:recientes')`.

### Además: editar libros

El dueño ve **Editar libro** en la página de cada libro (y *Editar* en cada tarjeta del catálogo), en lugar de *Añadir al carrito*. Puede editar **cualquier libro**, también los del catálogo original:

- El formulario es el mismo que el de carga (mismas validaciones), con los datos actuales y la **tapa actual**: si no se toca, se mantiene; se puede reemplazar (arrastrando otra) o quitar.
- La dirección del libro no cambia aunque cambie el título (no se rompen enlaces ni carritos).

### Funcionalidades de entregas anteriores

Siguen funcionando: registro e inicio de sesión (sesión con JWT en cookie `HttpOnly`), carrito por usuario, compra con envío de mail al personal, búsqueda, página 404 y errores sin información sensible.

---

## Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| "No pudimos conectar con el servidor" | La API no está corriendo, o se abrió el sitio con `127.0.0.1`. Abrir **http://localhost:5501** con la API en `localhost:8000`. En producción: esperar 30-60 s (el servidor se estaba despertando) y recargar. |
| No aparece *Cargar libro* / "Usuario o contraseña incorrectos" con el dueño | Faltan `OWNER_USERNAME`, `OWNER_EMAIL` u `OWNER_PASSWORD_HASH` en `.env`, o el hash no corresponde a la contraseña. Generar el hash de nuevo, pegarlo **completo** y reiniciar la API. |
| "Ocurrió un error inesperado" al registrarse o iniciar sesión | Falta `JWT_SECRET` en `.env`, o tiene menos de 32 caracteres. |
| "Origen no permitido" | El frontend se abrió en otro puerto u host: debe ser `http://localhost:5501` (o agregarlo a `CORS_ORIGINS` en `.env`). |
| Docker: "port is already allocated" | Otro programa o contenedor usa el puerto 8000 o 5501. Detenerlo (el frontend busca la API en el 8000). |
| `composer install` falla por una extensión | Activar `curl`, `mbstring` y `openssl` en el `php.ini`. |

## Documentación

- `README.md`: arquitectura, módulos, rutas de la API (incluidas `POST` y `PATCH /api/libros`) y decisiones de diseño.
- `docs/registro-e-inicio-de-sesion.md` (incluye los roles cliente y dueño), `docs/cookie-de-sesion.md`, `docs/seguridad-csrf.md`, `docs/envio-de-mail.md`.
