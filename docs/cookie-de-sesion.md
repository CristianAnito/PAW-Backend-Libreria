# Cookie de sesión (JWT)

Cuando un usuario se registra o inicia sesión, la API le entrega un **JWT** (un token firmado que dice quién es) dentro de una **cookie `HttpOnly`** llamada `auth_token`. A partir de ahí, el navegador la envía sola en cada petición a la API. El frontend nunca lee ni guarda el token.

## Recorrido de la cookie

```
1. Login OK      →  JwtService::issue($user)              arma y firma el token
                 →  AuthCookie::attach($response, ...)     lo pone en la respuesta
                 →  Response::send()                       envía  Set-Cookie: auth_token=...
2. El navegador guarda la cookie (JavaScript no puede leerla)
3. Petición nueva →  el navegador agrega  Cookie: auth_token=...
                 →  Request::fromGlobals()                 la lee de $_COOKIE
                 →  AuthCookie::read($request)             obtiene el token
                 →  JwtService::verify($token)             comprueba firma y vencimiento → id del usuario
4. Logout        →  AuthCookie::clear($response)           la reemplaza por una vacía y vencida
```

## Archivos del backend

| Archivo | Qué hace con la cookie |
|---|---|
| `config/auth.php` | Configuración: nombre (`auth_token`), `SameSite=Lax`, duración del token (`JWT_TTL`) y secreto de firma (`JWT_SECRET`). Los dos últimos vienen de `.env`. |
| `app/Services/JwtService.php` | Crea el token que va **dentro** de la cookie (`issue`) y lo verifica (`verify`): firma, algoritmo, vencimiento, emisor. No sabe nada de cookies. |
| `app/Core/AuthCookie.php` | Único lugar que define **cómo** es la cookie: `attach()` (al iniciar sesión), `clear()` (al cerrarla), `read()` (en cada petición) y sus opciones: `HttpOnly`, `Secure`, `SameSite`, `path`, `expires`. |
| `app/Core/Response.php` | `withCookie()` agrega una cookie a la respuesta; `send()` la envía con `setcookie()` (cabecera `Set-Cookie`). |
| `app/Core/Request.php` | Guarda las cookies que envió el navegador (`$_COOKIE`) y las entrega con `cookie()`. `isHttps()` indica si la petición llegó por HTTPS, para activar `Secure`. |
| `app/Core/CsrfGuard.php` | No toca la cookie, pero impide que otro sitio la aproveche (ver `docs/seguridad-csrf.md`). |
| `app/Controllers/AuthController.php` | Usa todo lo anterior: registro, inicio y cierre de sesión, usuario actual. |

> `app/Core/Session.php` maneja **otra** cookie, `PHPSESSID`: la sesión de PHP donde vive el carrito. Son independientes: el carrito funciona sin iniciar sesión.

## Archivos del frontend

| Archivo | Qué hace |
|---|---|
| `js/api.js` | `fetch(..., { credentials: 'include' })`: hace que el navegador envíe las cookies a la API (en desarrollo la API está en otro puerto). |
| `js/config.js` | Usa `/api` en producción, así la cookie queda en el mismo dominio que el front. |
| `vercel.json` | Reenvía `/api/*` a Render: para el navegador, front y API están en el mismo dominio y la cookie no es "de terceros". |

## Opciones de la cookie

```
Set-Cookie: auth_token=eyJ...; Max-Age=3600; path=/; secure; HttpOnly; SameSite=Lax
```

| Opción | Para qué |
|---|---|
| `HttpOnly` | JavaScript no puede leerla: un script malicioso inyectado en la página (XSS) no puede robar el token. |
| `Secure` | Solo viaja cifrada (HTTPS). Se activa sola en producción; en `localhost` (HTTP) no, porque el navegador no la guardaría. |
| `SameSite=Lax` | No se envía en POST ni `fetch` iniciados desde otros sitios (protección CSRF). |
| `Max-Age` / `expires` | Igual al vencimiento del token: después el navegador la borra. |
| `path=/` | Se envía a todas las rutas de la API. |

## Limitación conocida

Un JWT no se puede invalidar desde el servidor antes de su vencimiento. Cerrar sesión borra la cookie del navegador, pero si alguien hubiera copiado el token, le seguiría sirviendo hasta que venza. Por eso la duración es corta (1 hora, `JWT_TTL`). Las sesiones de PHP no tienen este problema porque los datos quedan en el servidor.
