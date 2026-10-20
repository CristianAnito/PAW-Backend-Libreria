# Cómo probar el proyecto en local

PAWPrints — Entrega 3 · Grupo La 25

El proyecto tiene dos partes, en repositorios separados:

| Parte | Repositorio | Qué es |
|---|---|---|
| **Backend** (este repo) | https://github.com/CristianAnito/PAW-Backend-Libreria | API en PHP (MVC, sin framework) que responde JSON |
| **Frontend** | https://github.com/romeroppau/PAW--Frontend | SPA en HTML, CSS y JavaScript que consume la API |

> Versión publicada (sin instalar nada): **https://pawprints-alpha.vercel.app**
> El servidor gratuito de la API se suspende tras 15 minutos sin uso: la primera carga puede tardar entre 30 y 60 segundos.

## 1. Requisitos

- **PHP 8.1 o superior**, con las extensiones `curl`, `mbstring` y `openssl` (vienen activadas en la mayoría de las instalaciones).
- **Composer**.
- Un navegador. Para servir el frontend alcanza con PHP (o la extensión *Live Server* de VS Code).

## 2. Backend (API)

```bash
git clone https://github.com/CristianAnito/PAW-Backend-Libreria.git
cd PAW-Backend-Libreria
composer install
cp .env.example .env          # en Windows (cmd): copy .env.example .env
```

Editar `.env` y completar **una sola variable**, `JWT_SECRET` (clave para firmar las sesiones). Generarla con:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

El resto de los valores de `.env.example` ya sirven para probar en local. En particular, `MAIL_DRIVER=log`: el mail de cada compra **no se envía**, se escribe en `storage/logs/app.log` para poder verlo sin configurar un servidor de correo.

Levantar la API:

```bash
php -S localhost:8000 -t public public/index.php
```

Comprobar que responde: http://localhost:8000/api/libros (devuelve los libros en JSON).

## 3. Frontend

En otra terminal:

```bash
git clone https://github.com/romeroppau/PAW--Frontend.git
cd PAW--Frontend
php -S localhost:5501
```

(Alternativa: abrir la carpeta en VS Code y usar *Open with Live Server*; ya está configurado en el puerto 5501.)

Abrir **http://localhost:5501**.

> **Importante:** usar `localhost` y no `127.0.0.1`, tanto para el frontend como para la API. El frontend se comunica con la API en el puerto 8000 del mismo host y las cookies de sesión solo viajan si ambos usan el mismo nombre.

## 4. Qué probar

### Consignas 02 y 03 — Inicio y "Acerca de nosotros"
- Inicio con el menú de navegación; *Nosotros* con la maquetación del TP1.

### Consigna 04 — Catálogo
- **Catálogo**: listado paginado (6 libros por página, configurable en `config/catalog.php`).
- **Búsqueda** por título o autor desde el menú o desde los filtros, y **orden** por precio o título. Los filtros quedan en la URL.
- **Detalle** de un libro: clic en la tapa.
- **Libro o página inexistente**: `http://localhost:5501/#/catalogo/no-existe` y `http://localhost:5501/#/catalogo?page=99` muestran la página 404.
- **Descarga CSV**: botón *Descargar CSV*. Exporta los resultados de la búsqueda actual.

### Registro e inicio de sesión
- **Registrarse** (menú): el navegador valida los campos y la API valida lo mismo. Al registrarse, la sesión queda iniciada y el menú muestra *Hola, usuario*.
- Recargar la página: la sesión se mantiene.
- **Cerrar sesión** y volver a **Iniciar sesión** (con usuario o email).
- Probar un usuario o email ya registrado, o una contraseña incorrecta: los errores se muestran sin revelar si el usuario existe.

### Consigna 05 — Compra con envío de mail al personal
1. Sin sesión, tocar *Añadir al carrito* en un libro: pide iniciar sesión y, al entrar, el libro se agrega solo.
2. En el carrito, cambiar cantidades o eliminar libros.
3. **Finalizar compra**: el email y el teléfono vienen de la cuenta. Probar datos inválidos (ej. DNI con letras): el navegador y la API los rechazan con las mismas reglas.
4. Completar y enviar: aparece *¡Compra registrada!* y el carrito se vacía.
5. **Ver el mail**: al final de `storage/logs/app.log` (en el backend) está el mail con el pedido, dirigido a `MAIL_TO` (la dirección del personal, configurable en `.env`) y con `Reply-To` del cliente.

### Consigna 06 — Errores sin información sensible
- Una URL inexistente de la API (http://localhost:8000/api/no-existe) devuelve un error genérico en JSON.
- Con `APP_ENV=production` en `.env`, cualquier error interno responde solo *"Ocurrió un error inesperado"*. El detalle queda en `storage/logs/app.log`.
- Los archivos privados no son accesibles: http://localhost:8000/.env devuelve 404 (solo la carpeta `public/` es pública).

### Consigna 07 — Publicado
- https://pawprints-alpha.vercel.app (frontend en Vercel; la API corre en Render en un contenedor Docker).

## 5. Opcional: ver la sesión en las herramientas del navegador

Con la sesión iniciada, en DevTools (F12):
- **Application → Cookies → http://localhost:5501**: la cookie `auth_token` (el JWT) tiene **HttpOnly** y `SameSite=Lax`.
- **Console**: `document.cookie` no muestra `auth_token`; JavaScript no puede leerla.

## 6. Opcional: probar la API con Docker (como en producción)

```bash
docker build -t pawprints-api .
docker run --rm -p 8000:80 --env-file .env pawprints-api
```

## Documentación

- `README.md`: arquitectura, módulos, rutas de la API y decisiones de diseño.
- `docs/registro-e-inicio-de-sesion.md`, `docs/cookie-de-sesion.md`, `docs/seguridad-csrf.md`, `docs/envio-de-mail.md`.

## Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| "No pudimos conectar con el servidor" | La API no está corriendo, o se abrió el sitio con `127.0.0.1`. Abrir **http://localhost:5501** con la API en `localhost:8000`. |
| "Ocurrió un error inesperado" al registrarse o iniciar sesión | Falta `JWT_SECRET` en `.env`, o tiene menos de 32 caracteres (paso 2). |
| "Origen no permitido" | El frontend se abrió en otro puerto u host: debe ser `http://localhost:5501` (o agregarlo a `CORS_ORIGINS` en `.env`). |
| `composer install` falla por una extensión | Activar `curl`, `mbstring` y `openssl` en el `php.ini`. |
