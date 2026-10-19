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
| `app/Core/` | Infraestructura: Request, Response, Router, Cors, CsrfGuard, Config, Logger, ErrorHandler, Validator, Mailer, Session, View |
| `app/Controllers/` | Reciben la petición, coordinan el modelo y devuelven JSON |
| `app/Models/` | Entidades (Book, Order) |
| `app/Repositories/` | Acceso a datos detrás de una interfaz |
| `app/Services/` | Lógica auxiliar (carrito, paginado, exportación CSV) |
| `app/Views/emails/` | Plantillas de texto de los mails (lo único que se genera en el servidor) |
| `config/` | Configuración y definición de rutas |
| `storage/` | Datos (`books.json`) y logs |

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
| Envío de correo | `Core/Mailer` (PHPMailer) | |
| Manejo de errores | `Core/ErrorHandler` (JSON genérico) | |
| Carga de clases | Composer | PSR-4 |

### Rutas de la API

| Método | URL | Controlador | Respuesta |
|---|---|---|---|
| GET | `/api/libros?q=&orden=&page=` | `CatalogController@index` | `{data: [libro], meta: {query, sort, page, per_page, total, total_pages}, sorts}` |
| GET | `/api/libros/export.csv?q=&orden=` | `CatalogController@exportCsv` | archivo CSV |
| GET | `/api/libros/{slug}` | `CatalogController@show` | `{data: libro}` |
| GET | `/api/carrito` | `CartController@index` | `{data: carrito}` |
| POST | `/api/carrito/items` `{libro, cantidad?}` | `CartController@add` | `{data: carrito}` |
| PATCH | `/api/carrito/items/{slug}` `{cantidad}` | `CartController@update` | `{data: carrito}` |
| DELETE | `/api/carrito/items/{slug}` | `CartController@remove` | `{data: carrito}` |
| POST | `/api/auth/registro` `{username, email, telefono?, contrasenia, confirmar_contrasenia}` | `AuthController@register` | 201 `{data: usuario}` + cookie de sesión |
| POST | `/api/auth/login` `{username (o email), contrasenia}` | `AuthController@login` | `{data: usuario}` + cookie de sesión; 401 si no coincide |
| POST | `/api/auth/logout` | `AuthController@logout` | 204, borra la cookie |
| GET | `/api/auth/yo` | `AuthController@me` | `{data: usuario}`; 401 sin sesión |
| POST | `/api/compra` `{nombre, apellido, documento, email, telefono}` | `CheckoutController@submit` | 201 (desactivada por ahora) |

`carrito = {items: [{book, quantity, subtotal}], total, count, max_quantity}`

`usuario = {id, username, email, phone}` (nunca incluye la contraseña ni su hash). La sesión es un JWT en la cookie `HttpOnly` `auth_token`: ver [docs/cookie-de-sesion.md](docs/cookie-de-sesion.md).

**Errores:** `{"error": {"message": "...", "fields": {"campo": "mensaje"}}}` con el código HTTP correspondiente
(401 sin sesión o login incorrecto, 403 origen no permitido, 404 recurso inexistente, 409 carrito vacío, 415 cuerpo que no es JSON, 422 datos inválidos con `fields`, 500 error interno, 503 mail no enviado).

## Decisiones de diseño

- **API JSON + SPA:** el backend no genera HTML; el frontend pide los datos con `fetch` y arma las vistas.
- **Búsqueda del catálogo:** `GET` con parámetros `q`, `orden` y `page` en la query string.
- **Paginación:** `per_page` configurable en `config/catalog.php`.
- **Libro o página inexistente:** HTTP 404 con mensaje JSON; el frontend muestra su vista 404.
- **Exportación CSV:** mismos parámetros que el listado; cabeceras `text/csv` y `Content-Disposition: attachment`.
- **Carrito:** se guarda en la sesión de PHP (cookie `HttpOnly`, `SameSite=Lax`). El frontend envía la cookie con `credentials: 'include'`.
- **CORS:** solo se autorizan los orígenes de `CORS_ORIGINS` (nunca `*`, porque se envían cookies). El frontend y la API deben estar en el mismo *sitio* (ej. ambos en `localhost`, distinto puerto) para que la cookie viaje.
- **CSRF:** las peticiones que modifican datos (POST, PATCH, DELETE) pasan por `Core/CsrfGuard`: se rechazan con 403 si la cabecera `Origin` no está en `CORS_ORIGINS` y con 415 si un POST/PATCH no es JSON (un formulario de otro sitio no puede enviar JSON sin pasar por el preflight de CORS). Se suma a las cookies `SameSite=Lax`. Los GET nunca modifican datos. Explicación completa en [docs/seguridad-csrf.md](docs/seguridad-csrf.md).
- **Despliegue (previsto):** front en Vercel, API en Render, base de datos en Aiven. Vercel reenvía `/api/*` a Render (`vercel.json` del frontend), así el navegador ve un solo dominio y las cookies no son "de terceros". `CORS_ORIGINS` debe incluir el dominio de Vercel.
- **Validación:** las reglas del backend (`Models/Order`) se repiten en el frontend (`js/validaciones.js`) para los atributos HTML; el backend siempre valida de nuevo.
- **Correo:** destinatario configurable mediante `MAIL_TO` en `.env`.
- **Errores:** en `production` se responde un mensaje genérico y el detalle se registra en `storage/logs`; en `development` se agrega el detalle en `error.debug`.
- **Persistencia:** `BookRepositoryInterface` aísla el origen de datos (`books.json` ahora, base de datos en el TP siguiente).

## Configuración

Variables en `.env` (ver `.env.example`): `APP_ENV`, `APP_URL`, `CORS_ORIGINS`, `JWT_SECRET`, `JWT_TTL`, `MAIL_DRIVER`, `MAIL_TO`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`.

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
