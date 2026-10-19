# Registro e inicio de sesión

Resumen de lo que hace el sitio con las cuentas de usuario. Detalles específicos en:

- [`cookie-de-sesion.md`](cookie-de-sesion.md): cómo viaja la sesión (JWT en una cookie `HttpOnly`).
- [`seguridad-csrf.md`](seguridad-csrf.md): protección contra peticiones falsificadas desde otros sitios.

## Funcionalidades para el usuario

| Funcionalidad | Cómo se comporta |
|---|---|
| **Registrarse** (`#/registrarse`) | Usuario, email, teléfono (opcional), contraseña y su confirmación. Al terminar, la sesión queda iniciada y vuelve al inicio. |
| **Iniciar sesión** (`#/iniciar-sesion`) | Se puede entrar con el **nombre de usuario o con el email**. |
| **Cerrar sesión** | Botón en el menú. Borra la cookie de sesión. |
| **Sesión persistente** | Recargar la página o volver más tarde (dentro de la hora de validez) mantiene la sesión. |
| **Menú según la sesión** | Sin sesión: *Iniciar sesión / Registrarse*. Con sesión: *Hola, usuario / Cerrar sesión*. |
| **Redirecciones** | Con la sesión iniciada, las páginas de login y registro llevan al inicio. `?volver=/ruta` vuelve a esa ruta después de entrar (ej. `#/iniciar-sesion?volver=/compra`). Solo acepta rutas del sitio. |
| **Errores claros** | El navegador marca los campos inválidos antes de enviar. Los errores que detecta el servidor (ej. "Ese nombre de usuario ya está en uso") aparecen debajo de cada campo. |
| **Carrito y compra con sesión** | Para agregar al carrito hay que tener la sesión iniciada. Sin sesión, "Añadir al carrito" lleva al login (o registro) y, al entrar, el libro se agrega solo. Cada usuario tiene su propio carrito. |

## Reglas de validación

Iguales en el frontend (`js/validaciones.js`, atributos HTML) y en el backend (`app/Models/User.php`, `Validator`). El backend siempre valida de nuevo: la validación del navegador se puede saltear.

| Campo | Regla |
|---|---|
| Usuario | Obligatorio, 3 a 30 caracteres: letras sin acentos, números, `_` y `.`. Único (sin distinguir mayúsculas: `Vale` = `vale`). |
| Email | Obligatorio, formato válido, hasta 100 caracteres. Único (sin distinguir mayúsculas). |
| Teléfono | Opcional, 8 a 20 caracteres: números y espacios, con `+` inicial opcional. |
| Contraseña | Obligatoria, mínimo 8 caracteres, al menos una letra y un número, máximo 72 bytes (límite de bcrypt). |
| Confirmación | Tiene que coincidir con la contraseña (en el navegador, con `setCustomValidity`). |

## Seguridad

| Medida | Para qué |
|---|---|
| Contraseñas con `password_hash` (bcrypt) | Nunca se guarda la contraseña, solo su hash. Las contraseñas no se recortan: los espacios son parte de ellas. |
| Mismo mensaje de error en el login | "Usuario o contraseña incorrectos" en los dos casos: no revela qué usuarios existen. |
| Mismo **tiempo** de respuesta en el login | Si el usuario no existe, igual se ejecuta `password_verify` contra un hash fijo. Así el tiempo de respuesta tampoco revela qué usuarios existen. |
| Datos públicos del usuario | La API nunca devuelve el hash (`User::toPublicArray()`). El token solo lleva el id y el nombre. |
| Sesión en cookie `HttpOnly` | JavaScript no puede leer el token (ver `cookie-de-sesion.md`). |
| Protección CSRF | Solo JSON y orígenes autorizados en POST/PATCH/DELETE (ver `seguridad-csrf.md`). |
| Duplicados sin carreras | El control de usuario o email repetido se hace con el archivo bloqueado (`flock`): dos registros simultáneos no pueden crear el mismo usuario. |
| Errores sin información sensible | Los errores internos responden un mensaje genérico (el detalle va al log). Los stack traces del log no incluyen argumentos (contraseñas). No se envía la versión de PHP (`X-Powered-By`). |
| Campos mal formados | Un campo enviado como array u objeto responde 422, nunca un error 500. |

## API

| Método | URL | Respuesta |
|---|---|---|
| POST | `/api/auth/registro` | 201 + cookie · 422 con errores por campo |
| POST | `/api/auth/login` | 200 + cookie · 401 · 422 si faltan datos |
| POST | `/api/auth/logout` | 204, borra la cookie |
| GET | `/api/auth/yo` | Usuario actual · 401 sin sesión (y borra una cookie vencida o alterada) |

Usuario devuelto: `{id, username, email, phone}`.

## Archivos

**Backend**

| Archivo | Función |
|---|---|
| `app/Controllers/AuthController.php` | Registro, login, logout y usuario actual. |
| `app/Controllers/Controller.php` | `currentUser()` (usuario o null) y `requireAuth()` (401 si no hay sesión), para cualquier controlador. |
| `app/Models/User.php` | Entidad usuario y reglas de validación. |
| `app/Repositories/UserRepositoryInterface.php` | Qué operaciones existen sobre los usuarios. |
| `app/Repositories/JsonUserRepository.php` | Implementación con `storage/data/users.json` y `flock`. |
| `app/Repositories/UserAlreadyExistsException.php` | Usuario o email repetido (indica el campo). |
| `app/Services/JwtService.php`, `app/Core/AuthCookie.php` | Token y cookie (ver `cookie-de-sesion.md`). |
| `app/Core/HttpException.php` | Errores esperados (ej. 401) con mensaje para el usuario. |
| `config/auth.php` | Configuración: secreto y duración del token, cookie, archivo de usuarios. |

**Frontend**

| Archivo | Función |
|---|---|
| `js/sesion.js` | Estado de la sesión: consulta `/api/auth/yo` al abrir el sitio; login, registro y logout; evento `sesion:cambio`. |
| `js/vistas/registrarse.js`, `js/vistas/iniciarSesion.js` | Formularios, validación, errores por campo y redirecciones. |
| `js/validaciones.js` (`USUARIO`) | Reglas del formulario, iguales a las del backend. |
| `js/app.js`, `index.html` | Menú según la sesión y botón *Cerrar sesión*. |

## Persistencia y limitaciones

- Los usuarios se guardan en `storage/data/users.json` (fuera del repositorio). Gracias al patrón Repository, pasar a la base de datos en la próxima entrega es escribir un `PdoUserRepository` y cambiar una línea en `Controller::users()`.
- En el plan gratis de Render el disco se borra en cada reinicio o deploy: los usuarios registrados se pierden hasta que se use la base de datos.
- Un token no se puede invalidar antes de vencer (1 hora): cerrar sesión borra la cookie del navegador, pero un token copiado seguiría sirviendo hasta su vencimiento.
- No hay límite de intentos de login (protección contra fuerza bruta): queda como mejora.
