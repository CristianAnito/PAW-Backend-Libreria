# Envío del mail de compra

Cuando un usuario finaliza una compra (`POST /api/compra`), la API valida los datos y envía un mail con el pedido **al personal de la librería**. La dirección de destino es configurable (`MAIL_TO`).

## Flujo de la compra

```
Catálogo → "Añadir al carrito" ──(sin sesión)──→ Iniciar sesión / Registrarse ──→ el libro se agrega solo
        → Carrito → "Finalizar compra" → formulario (email y teléfono precargados)
        → POST /api/compra → validación → mail al personal → se vacía el carrito → "¡Compra registrada!"
```

Carrito y compra requieren la sesión iniciada (ver [`registro-e-inicio-de-sesion.md`](registro-e-inicio-de-sesion.md)).

## Remitente, destinatario y respuesta

| Campo | Valor | Por qué |
|---|---|---|
| **From** | `MAIL_FROM` / `MAIL_FROM_NAME` (ej. `PAWPrints <...>`) | Una dirección del sitio, verificada en el proveedor. |
| **To** | `MAIL_TO` | El personal de la librería. |
| **Reply-To** | Email que el cliente puso en el formulario | Al tocar *Responder*, el personal le escribe al cliente. |

El email del cliente **nunca** se usa como remitente. Sería falsificar su dirección: los servidores de correo verifican quién puede enviar en nombre de cada dominio (SPF, DKIM, DMARC) y lo rechazarían o lo mandarían a spam.

## Cómo se envía: `MAIL_DRIVER`

`Core/Mailer` elige una forma de entrega (patrón *Strategy*, interfaz `Core/Mail/MailTransport`). `CheckoutController` no sabe cuál se usa.

| Driver | Clase | Uso |
|---|---|---|
| `log` | `LogTransport` | Desarrollo: no envía, escribe el mail en `storage/logs/app.log`. |
| `smtp` | `SmtpTransport` (PHPMailer) | Pruebas locales con un servidor SMTP (ej. Mailtrap). |
| `brevo` | `BrevoTransport` | **Producción.** API HTTP de Brevo por HTTPS. |

**¿Por qué no SMTP en producción?** Desde septiembre de 2025, el plan gratis de Render **bloquea las conexiones salientes a los puertos SMTP** (25, 465 y 587); Railway hace lo mismo en sus planes Free y Hobby. La API de Brevo usa HTTPS (puerto 443), que no está bloqueado, y funciona en cualquier hosting.

## Configurar Brevo (una vez)

1. Crear una cuenta gratis en [brevo.com](https://www.brevo.com) (300 mails por día).
2. **Senders, Domains & Dedicated IPs → Senders → Add a sender**: agregar la dirección que va a figurar como remitente (puede ser un Gmail del grupo) y confirmarla con el mail que llega.
3. **SMTP & API → API Keys → Generate a new API key**. Copiarla: se ve una sola vez.
4. En `.env` (local) o en las variables de entorno de Render:
   ```
   MAIL_DRIVER=brevo
   BREVO_API_KEY=xkeysib-...            # secreta: nunca al repositorio
   MAIL_FROM=remitente-verificado@...   # la dirección del paso 2
   MAIL_FROM_NAME=PAWPrints
   MAIL_TO=personal-de-la-libreria@...
   ```

## Errores

| Situación | Respuesta de la API | Qué pasa |
|---|---|---|
| Datos inválidos | 422 con errores por campo | Se muestran debajo de cada campo. |
| Carrito vacío | 409 | No se envía nada. |
| Sin sesión | 401 | El frontend lleva al login y después vuelve a la compra. |
| Brevo rechaza o no responde (clave inválida, remitente sin verificar, límite diario) | 503 "No pudimos registrar tu compra..." | **El carrito no se vacía**: el usuario puede reintentar. El detalle (código y mensaje de Brevo) va al log, **sin la API key**. |
| Falta `BREVO_API_KEY` o `MAIL_DRIVER` desconocido | 500 genérico | Error de configuración del servidor; queda en el log. |

## Probar

- **Local, sin enviar:** `MAIL_DRIVER=log`, hacer una compra y mirar el final de `storage/logs/app.log`.
- **Envío real:** `MAIL_DRIVER=brevo` con la clave, hacer una compra y comprobar que el mail llega a `MAIL_TO` y que *Responder* va al email del cliente. En Brevo, **Transactional → Logs** muestra cada envío.
