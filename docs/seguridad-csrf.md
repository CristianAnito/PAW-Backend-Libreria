# Protección contra CSRF

## Qué es

CSRF (*Cross-Site Request Forgery*) aprovecha que **el navegador agrega las cookies solo** en cada petición a un sitio, sin importar qué página la originó.

Si un usuario tiene la sesión iniciada en PAWPrints y entra a `sitio-malo.com`, esa página puede hacer que su navegador envíe una petición a nuestra API. La petición lleva la cookie de sesión, así que la API cree que la hizo el usuario. El atacante no necesita leer la respuesta: le alcanza con que la acción se ejecute (comprar, cambiar datos, etc.).

```html
<!-- En sitio-malo.com -->
<form action="https://pawprints.vercel.app/api/carrito/items" method="post">
  <input name="libro" value="monje-ferrari">
</form>
<script>document.forms[0].submit()</script>
```

Conocer la URL de la API no es un obstáculo para el atacante: cualquiera la ve en las DevTools del navegador.

## Cómo lo evitamos

Usamos tres capas, cada una suficiente por sí sola (*defensa en profundidad*):

1. **Cookies con `SameSite=Lax`**: el navegador no las envía en POST ni en `fetch` iniciados desde otro sitio.
2. **Solo JSON** (`Core/CsrfGuard`): un POST o PATCH que no sea `application/json` se rechaza con **415**. Un formulario HTML no puede enviar JSON, y un `fetch` con JSON hacia otro origen obliga al navegador a pedir permiso antes (*preflight* de CORS), que la API solo concede a los orígenes autorizados.
3. **Cabecera `Origin`** (`Core/CsrfGuard`): en POST, PATCH y DELETE, si `Origin` no está en `CORS_ORIGINS`, la API responde **403**. Esa cabecera la pone el navegador y JavaScript no puede modificarla.

| Intento del atacante | Qué lo frena |
|---|---|
| Un `<form>` POST desde su página | **SameSite=Lax**: la petición viene de otro sitio y el navegador no agrega la cookie, así que la API la ve como anónima. Además, **CsrfGuard** la rechaza por no ser JSON (415) y por su `Origin` (403). |
| Un `fetch()` con JSON desde su página | El navegador primero hace el **preflight** (`OPTIONS`). La API no autoriza ese origen, así que el navegador **no envía** la petición real. Si llegara igual, CsrfGuard la frena por el `Origin` (403). |
| Enviar el JSON como `text/plain` para evitar el preflight | CsrfGuard exige `application/json` y responde 415. |
| Falsificar la cabecera `Origin` | JavaScript no puede hacerlo: la pone el navegador. Con herramientas como curl sí se puede, pero esas peticiones no llevan la cookie de la víctima. |
| Un enlace o una imagen (petición GET) | Con `Lax`, en una navegación GET la cookie **sí** viaja. Por eso **ningún GET modifica datos**: carrito, compra y cierre de sesión usan POST, PATCH o DELETE. |

## Por qué nuestro frontend sí puede enviar la cookie

El frontend cumple las tres condiciones:

- **La cookie viaja porque es del mismo sitio.**
  - **En producción**, Vercel reenvía `/api/*` al backend en Render (`vercel.json` del frontend). Para el navegador, el front y la API están en el mismo dominio (`pawprints.vercel.app`), así que la cookie es propia del sitio.
  - **En desarrollo**, `localhost:5501` y `localhost:8000` son el mismo sitio, porque para `SameSite` no importa el puerto.
- **Envía JSON**: `js/api.js` manda siempre `Content-Type: application/json` en POST y PATCH.
- **Su origen está autorizado**: el dominio del frontend figura en `CORS_ORIGINS`.

Un detalle importante: **CORS no decide si se envía la cookie**. Eso lo decide `SameSite`. CORS solo decide si JavaScript puede **leer** la respuesta de otro origen. El frontend necesita las dos cosas: que la cookie viaje (SameSite) y poder leer el JSON (CORS, en desarrollo; en producción todo es el mismo origen).

> **Sin el proxy de Vercel** el front (`*.vercel.app`) y la API (`*.onrender.com`) serían sitios distintos. La cookie sería "de terceros": con `Lax` no viajaría, y con `SameSite=None` la bloquean Safari, el modo incógnito y otros navegadores.
