# PAW Backend - Librería

API REST en PHP del sitio web de la librería. Patrón MVC, sin framework externo.
Responde **JSON**; las páginas las arma el frontend (SPA en JavaScript), que está en el repositorio `PAW--Frontend--TP1`.

## Requisitos

- PHP 8.1 o superior
- Composer
- Servidor web con `DocumentRoot` en `public/` (o el servidor de desarrollo de PHP)

## Instalación

```bash
composer install
cp .env.example .env
php -S localhost:8000 -t public public/index.php
```

La API queda en `http://localhost:8000/api`. Para usar el sitio, levantar también el frontend
(ver su README) en `http://localhost:5501`.

## Arquitectura

```
Navegador ── SPA (HTML + CSS + JS) ──fetch JSON──▶ API (este repo) ──▶ books.json / sesión
```

Todas las peticiones ingresan por `public/index.php` (front controller).
El flujo es: `Request` → `Router` → `Controller` → `Repository/Model` → `Response` (JSON).

### Estructura

| Carpeta | Contenido |
|---|---|
| `public/` | Único directorio expuesto: front controller y `.htaccess` |
| `app/Core/` | Infraestructura: Request, Response, Router, Cors, CsrfGuard, Config, Logger, ErrorHandler, Validator, Mailer (+ `Mail/`: transportes log, smtp y brevo), Session, View |
| `app/Controllers/` | Reciben la petición, coordinan el modelo y devuelven JSON |
| `app/Models/` | Entidades (Book, Order) |
| `app/Repositories/` | Acceso a datos detrás de una interfaz |
| `app/Services/` | Lógica auxiliar (carrito, paginado, exportación CSV, cuenta del dueño) y `Covers/`: validación y almacenamiento de las tapas (local o Cloudinary) |
| `app/Views/emails/` | Plantillas de texto de los mails (lo único que se genera en el servidor) |
| `config/` | Configuración y definición de rutas |
| `storage/` | Datos (`books.json`: catálogo base; `libros-cargados.json`: libros cargados por el dueño, fuera de git), `covers/` (tapas en desarrollo, fuera de git) y logs |

### Responsabilidades por módulo

| Problemática | Módulo | Referencia |
|---|---|---|
| Análisis de peticiones HTTP | `Core/Request` (query, cuerpo JSON, cabeceras) | PSR-7 (concepto) |
| Mapeo de URLs a funcionalidades | `Core/Router` + `config/routes.php` | |
| Generación de respuestas HTTP | `Core/Response` (`json()`, `error()`) | PSR-7 (concepto) |
| Acceso desde otro origen (SPA) | `Core/Cors` + `config/cors.php` | CORS |
| Generación de registros | `Core/Logger` (Monolog) | PSR-3 |
| Persistencia | `Repositories/*` + `Models/*` | Repository pattern |
| Configuración | `Core/Config` + `config/*.php` + `.env` | |
| Representaciones de la información | JSON: `Response::json`. CSV: `Services/CsvExporter`. Texto (mail): `Core/View` | |
| Validación de entrada | `Core/Validator` | |
| Envío de correo | `Core/Mailer` + `Core/Mail/*Transport` (log, SMTP con PHPMailer, API HTTP de Brevo) | Strategy |
| Imágenes de tapa | `Services/Covers/CoverImage` (validación) + `CoverStorage` (disco local o Cloudinary) | Strategy |
| Manejo de errores | `Core/ErrorHandler` (JSON genérico) | |
| Carga de clases | Composer | PSR-4 |

### Rutas de la API

| Método | URL | Controlador | Respuesta |
|---|---|---|---|
| GET | `/api/libros?q=&orden=&page=` | `CatalogController@index` | `{data: [libro], meta: {query, sort, page, per_page, total, total_pages}, sorts}` |
| GET | `/api/libros/export.csv?q=&orden=` | `CatalogController@exportCsv` | archivo CSV |
| GET | `/api/libros/todos` | `CatalogController@all` | `{data: [libro], sorts}` (sin filtrar ni paginar: lo hace el frontend) |
| GET | `/api/libros/{slug}` | `CatalogController@show` | `{data: libro}` |
| POST | `/api/libros` `{titulo, autor, genero, precio, resumen, sinopsis, tapa?}` | `CatalogController@store` | 201 `{data: libro}` · **solo el dueño** (401 sin sesión, 403 cliente) · 422 datos inválidos, tapa inválida o libro repetido · 503 no se pudo guardar la tapa |
| PATCH | `/api/libros/{slug}` `{titulo, autor, genero, precio, resumen, sinopsis, tapa?}` | `CatalogController@update` | 200 `{data: libro}` · **solo el dueño** · edita cualquier libro (también los del catálogo base); el slug no cambia · `tapa`: sin el campo se mantiene, *data URL* la reemplaza, `null` la quita · 404 libro inexistente · 422 / 503 como al cargar |
| GET | `/api/tapas/{nombre}.{tipo}` | `CoverController@show` | la imagen de una tapa guardada en el disco (solo con `COVERS_DRIVER=local`) |
| GET | `/api/carrito` | `CartController@index` | `{data: carrito}` |
| POST | `/api/carrito/items` `{libro, cantidad?}` | `CartController@add` | `{data: carrito}` |
| PATCH | `/api/carrito/items/{slug}` `{cantidad}` | `CartController@update` | `{data: carrito}` |
| DELETE | `/api/carrito/items/{slug}` | `CartController@remove` | `{data: carrito}` |
| POST | `/api/auth/registro` `{username, email, telefono?, contrasenia, confirmar_contrasenia}` | `AuthController@register` | 201 `{data: usuario}` + cookie de sesión |
| POST | `/api/auth/login` `{username (o email), contrasenia}` | `AuthController@login` | `{data: usuario}` + cookie de sesión; 401 si no coincide |
| POST | `/api/auth/logout` | `AuthController@logout` | 204, borra la cookie |
| GET | `/api/auth/yo` | `AuthController@me` | `{data: usuario}`; 401 sin sesión |
| POST | `/api/compra` `{nombre, apellido, documento, email, telefono}` | `CheckoutController@submit` | 201 y mail al personal · 409 carrito vacío · 503 mail no enviado |

Carrito y compra son **solo para clientes con la sesión iniciada** (401 sin sesión, 403 si es el dueño: administra el catálogo, no compra).

`carrito = {items: [{book, quantity, subtotal}], total, count, max_quantity}`

`libro = {id, slug, title, author, genre, price, summary, synopsis, cover}`. `cover`: nombre de archivo (catálogo base: la imagen es del frontend, `media/libros/`), URL absoluta (tapa subida por el dueño) o `null` (imagen genérica).

`tapa` (opcional) es la imagen como *data URL* (`data:image/webp;base64,...`): JPG, PNG o WebP, hasta 2 MB, de 200 a 5000 px por lado. Va dentro del JSON (y no como formulario `multipart`) porque la API solo acepta JSON en los POST (protección CSRF). El tipo y las medidas se leen de los bytes de la imagen, no del nombre ni de lo que declara el navegador.

`usuario = {id, username, email, phone, role}` (nunca incluye la contraseña ni su hash). `role`: `cliente` (todo el que se registra) o `duenio` (el dueño de la librería, creado desde la configuración). La sesión es un JWT en la cookie `HttpOnly` `auth_token`: ver [docs/cookie-de-sesion.md](docs/cookie-de-sesion.md).

**Errores:** `{"error": {"message": "...", "fields": {"campo": "mensaje"}}}` con el código HTTP correspondiente
(401 sin sesión o login incorrecto, 403 origen no permitido o acción no permitida para el rol, 404 recurso inexistente, 409 carrito vacío, 415 cuerpo que no es JSON, 422 datos inválidos con `fields`, 500 error interno, 503 mail no enviado o tapa no guardada).

## Decisiones de diseño

- **API JSON + SPA:** el backend no genera HTML; el frontend pide los datos con `fetch` y arma las vistas.
- **Búsqueda del catálogo:** `GET` con parámetros `q`, `orden` y `page` en la query string.
- **Paginación:** `per_page` configurable en `config/catalog.php`.
- **Libro o página inexistente:** HTTP 404 con mensaje JSON; el frontend muestra su vista 404.
- **Exportación CSV:** mismos parámetros que el listado; cabeceras `text/csv` y `Content-Disposition: attachment`.
- **Carrito:** solo clientes con la sesión iniciada (el dueño no compra); uno por usuario, guardado en la sesión de PHP bajo `cart.{id}` (cookie `PHPSESSID`, `HttpOnly`, `SameSite=Lax`). Así, si en el mismo navegador entra otro usuario, no ve el carrito anterior. Con la base de datos podrá pasar a una tabla.
- **CORS:** solo se autorizan los orígenes de `CORS_ORIGINS` (nunca `*`, porque se envían cookies). El frontend y la API deben estar en el mismo *sitio* (ej. ambos en `localhost`, distinto puerto) para que la cookie viaje.
- **CSRF:** las peticiones que modifican datos (POST, PATCH, DELETE) pasan por `Core/CsrfGuard`: se rechazan con 403 si la cabecera `Origin` no está en `CORS_ORIGINS` y con 415 si un POST/PATCH no es JSON (un formulario de otro sitio no puede enviar JSON sin pasar por el preflight de CORS). Se suma a las cookies `SameSite=Lax`. Los GET nunca modifican datos. Explicación completa en [docs/seguridad-csrf.md](docs/seguridad-csrf.md).
- **Despliegue (previsto):** front en Vercel, API en Render, base de datos en Aiven. Vercel reenvía `/api/*` a Render (`vercel.json` del frontend), así el navegador ve un solo dominio y las cookies no son "de terceros". `CORS_ORIGINS` debe incluir el dominio de Vercel. En Render, **`APP_ENV=production`** (obligatorio: con `development` la API agrega el detalle de los errores, con rutas de archivos, en sus respuestas), un `JWT_SECRET` nuevo, `MAIL_DRIVER=brevo` y `BREVO_API_KEY`.
- **Validación:** las reglas del backend (`Models/Order`) se repiten en el frontend (`js/validaciones.js`) para los atributos HTML; el backend siempre valida de nuevo.
- **Correo:** al finalizar la compra se envía un mail al personal (`MAIL_TO`, configurable). Remitente: `MAIL_FROM`, una dirección del sitio verificada en el proveedor; el email del cliente va en `Reply-To` (nunca como remitente: sería falsificarlo y se rechazaría por SPF/DKIM/DMARC). `MAIL_DRIVER`: `log` (desarrollo), `smtp` (local) o `brevo` (producción: Render gratis bloquea los puertos SMTP, la API de Brevo usa HTTPS). Si el envío falla, 503 y el carrito no se vacía. Ver [docs/envio-de-mail.md](docs/envio-de-mail.md).
- **Errores:** en `production` se responde un mensaje genérico y el detalle se registra en `storage/logs`; en `development` se agrega el detalle en `error.debug`. Los mensajes que sí ve el usuario (`HttpException`, validaciones, login) nunca incluyen datos internos; el login responde igual (mensaje y tiempo) exista o no el usuario. No se envía `X-Powered-By` (versión de PHP) y los stack traces del log no incluyen argumentos (contraseñas).
- **Persistencia:** `BookRepositoryInterface` aísla el origen de datos (`books.json` ahora, base de datos en el TP siguiente). Los libros que carga el dueño se guardan aparte, en `storage/data/libros-cargados.json` (fuera de git: probar la carga en local no modifica el catálogo del repositorio); el catálogo es la unión de los dos archivos. Si el dueño **edita** un libro del catálogo base, la versión editada se guarda en `libros-cargados.json` con el mismo id y reemplaza a la original al leer: `books.json` nunca se modifica. En Render el disco se borra al reiniciar, así que los libros cargados (como los usuarios) se pierden hasta usar la base de datos.
- **Tapas:** las que sube el dueño se guardan según `COVERS_DRIVER`: `local` (desarrollo) en `storage/covers/`, fuera de `public/`, con un nombre aleatorio, y las entrega `GET /api/tapas/...`; `cloudinary` (producción) en Cloudinary, porque el disco de Render se borra. La subida a Cloudinary va firmada desde el servidor: el *API secret* nunca llega al navegador. La imagen se guarda recién cuando todo el formulario es válido, así no quedan tapas de libros que no se cargaron.

## Configuración

Variables en `.env` (ver `.env.example`): `APP_ENV`, `APP_URL`, `LOG_FILE`, `CORS_ORIGINS`, `JWT_SECRET`, `JWT_TTL`, `OWNER_USERNAME`, `OWNER_EMAIL`, `OWNER_PASSWORD_HASH`, `COVERS_DRIVER`, `CLOUDINARY_URL`, `MAIL_DRIVER`, `MAIL_TO`, `MAIL_FROM`, `BREVO_API_KEY`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`.

## Despliegue (Docker / Render)

La API se publica como contenedor (`Dockerfile`): PHP 8.4 + Apache, con solo `public/` expuesto, configuración de PHP de producción y Apache sin revelar su versión. El puerto lo indica la variable `PORT` (Render la define).

Probar la imagen en local:

```bash
docker build -t pawprints-api .
docker run --rm -p 8090:80 --env-file .env -e APP_ENV=production -e LOG_FILE=php://stderr pawprints-api
# http://localhost:8090/api/libros
```

En Render: *New → Web Service*, repositorio del backend, rama `main`, runtime **Docker**, plan Free. Variables de entorno:

| Variable | Valor |
|---|---|
| `APP_ENV` | `production` (obligatorio: con `development` se muestra el detalle de los errores) |
| `APP_URL` | URL del servicio en Render (ej. `https://pawprints-api.onrender.com`) |
| `LOG_FILE` | `php://stderr` (los registros aparecen en la pestaña *Logs*) |
| `CORS_ORIGINS` | URL del frontend en Vercel (ej. `https://pawprints.vercel.app`) |
| `JWT_SECRET` | Uno **nuevo** (`php -r "echo bin2hex(random_bytes(32));"`), distinto del de desarrollo |
| `JWT_TTL` | `3600` |
| `OWNER_USERNAME`, `OWNER_EMAIL`, `OWNER_PASSWORD_HASH` | Cuenta del dueño de la librería (único que puede cargar libros). `OWNER_PASSWORD_HASH` es el hash: `php -r "echo password_hash('la-contraseña', PASSWORD_DEFAULT);"` |
| `COVERS_DRIVER` | `cloudinary` |
| `CLOUDINARY_URL` | `cloudinary://API_KEY:API_SECRET@CLOUD_NAME` (Cloudinary → *Settings → API Keys*). Secreta |
| `MAIL_DRIVER` | `brevo` |
| `BREVO_API_KEY`, `MAIL_FROM`, `MAIL_FROM_NAME`, `MAIL_TO` | Ver [docs/envio-de-mail.md](docs/envio-de-mail.md) |

Limitaciones del plan gratis: el servicio se duerme tras 15 minutos sin uso (la primera petición tarda 30-60 s) y el disco se borra en cada reinicio o deploy (`users.json`, sesiones y carritos), hasta que se use la base de datos.

## Flujo de trabajo del equipo

- Rama principal protegida; un cambio por rama y por pull request.
- Convención de ramas: `feat/<consigna>-<descripcion>`.

| Consigna | Rama sugerida | Responsable |
|---|---|---|
| 2 y 3 | `feat/home-nosotros` | |
| 4 | `feat/catalogo` | |
| 5 | `feat/reserva` | |
| 6 | `feat/errores` | |
| 7 | `feat/deploy` | |
