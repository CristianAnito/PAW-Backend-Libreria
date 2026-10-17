# PAW Backend - Librería

Backend en PHP del sitio web de la librería. Patrón MVC, sin framework externo.
El frontend estático (TP1) está en el repositorio `PAW--Frontend--TP1`.

## Requisitos

- PHP 8.1 o superior
- Composer
- Servidor web con `DocumentRoot` en `public/` (o `php -S localhost:8000 -t public`)

## Instalación

```bash
composer install
cp .env.example .env
php -S localhost:8000 -t public
```

## Arquitectura

Todas las peticiones ingresan por `public/index.php` (front controller).
El flujo es: `Request` → `Router` → `Controller` → `Repository/Model` → `View` → `Response`.

### Estructura

| Carpeta | Contenido |
|---|---|
| `public/` | Único directorio expuesto: front controller, `.htaccess`, assets |
| `app/Core/` | Infraestructura: Request, Response, Router, View, Config, Logger, ErrorHandler, Validator, Mailer |
| `app/Controllers/` | Reciben la petición, coordinan modelo y vista |
| `app/Models/` | Entidades (Book, Reservation) |
| `app/Repositories/` | Acceso a datos detrás de una interfaz |
| `app/Services/` | Lógica auxiliar (exportación CSV) |
| `app/Views/` | Plantillas HTML, layouts y parciales |
| `config/` | Configuración y definición de rutas |
| `storage/` | Datos (`books.json`) y logs |

### Responsabilidades por módulo

| Problemática | Módulo | Referencia |
|---|---|---|
| Análisis de peticiones HTTP | `Core/Request` | PSR-7 (concepto) |
| Mapeo de URLs a funcionalidades | `Core/Router` + `config/routes.php` | |
| Generación de respuestas HTTP | `Core/Response` | PSR-7 (concepto) |
| Generación de registros | `Core/Logger` (Monolog) | PSR-3 |
| Persistencia | `Repositories/*` + `Models/*` | Repository pattern |
| Configuración | `Core/Config` + `config/*.php` + `.env` | |
| Representaciones de la información | HTML: `Core/View`. CSV: `Services/CsvExporter`. Otros formatos: nuevos servicios | |
| Validación de entrada | `Core/Validator` | |
| Envío de correo | `Core/Mailer` (PHPMailer) | |
| Manejo de errores | `Core/ErrorHandler` + vistas `errors/` | |
| Carga de clases | Composer | PSR-4 |

### Rutas

| Método | URL | Controlador |
|---|---|---|
| GET | `/` | `HomeController@index` |
| GET | `/nosotros` | `AboutController@index` |
| GET | `/catalogo` | `CatalogController@index` |
| GET | `/catalogo/export.csv` | `CatalogController@exportCsv` |
| GET | `/catalogo/{slug}` | `CatalogController@show` |
| GET | `/reservar/{slug}` | `ReservationController@form` |
| POST | `/reservar` | `ReservationController@submit` |

## Decisiones de diseño

- **Búsqueda del catálogo:** `GET` con parámetros `q`, `page` y `per_page` en la query string.
- **Paginación:** `per_page` restringido a 5, 10 o 20 (por defecto 10).
- **Libro o página inexistente:** respuesta HTTP 404 con vista `errors/404`.
- **Exportación CSV:** mismos parámetros que el listado; cabeceras `text/csv` y `Content-Disposition: attachment`.
- **Reserva:** `POST` con `application/x-www-form-urlencoded`; redirección tras el éxito (Post/Redirect/Get). Las reglas de validación del backend replican las del frontend.
- **Correo:** destinatario configurable mediante `MAIL_TO` en `.env`.
- **Errores:** en `production` se muestra un mensaje genérico y el detalle se registra en `storage/logs`; en `development` se muestra el detalle.
- **Persistencia:** `BookRepositoryInterface` aísla el origen de datos (`books.json` ahora, base de datos en el TP siguiente).

## Configuración

Variables en `.env` (ver `.env.example`): `APP_ENV`, `APP_URL`, `MAIL_TO`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`.

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
