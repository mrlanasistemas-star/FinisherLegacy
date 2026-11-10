# Correos transaccionales a clientes

Este documento explica los correos que reciben los compradores (pago confirmado, reembolso, envío y entrega), cómo se garantiza que cada uno se envíe una sola vez y cómo se configura el envío en producción.

## Qué se envía y cuándo

| Correo | Disparador (evento de dominio) | De dónde sale el evento |
|---|---|---|
| **Pago confirmado** (`OrderPaidMail`) | `App\Events\Commerce\OrderPaid` | `MarkOrderPaid` (webhooks OpenPay/Stripe, `SyncOnlinePayment`, pagos manuales del admin), solo en la transición a `paid` |
| **Reembolso procesado** (`OrderRefundedMail`) | `App\Events\Commerce\OrderRefunded` (`order`, `amountMinor`, `full`) | `ProcessPaymentWebhook::applyRefund`, para reembolsos totales y parciales |
| **Tu pedido va en camino** (`OrderShippedMail`) | `App\Events\Commerce\OrderShipped` | Admin → Pedidos → datos de envío, cuando `shipped_at` pasa de vacío a una fecha |
| **Pedido entregado** (`OrderDeliveredMail`) | `App\Events\Commerce\OrderDelivered` | Admin → Pedidos → «Marcar entregado» (`delivered_at`) |

Los cuatro eventos implementan `ShouldDispatchAfterCommit`: se emiten solo cuando la transacción que cambió el pedido se confirma. Otros módulos, como las alertas al admin y las notificaciones push, escuchan los mismos `OrderPaid` y `OrderRefunded`. Por eso no hay que cambiarles el nombre ni el payload.

**Destinatario:** el correo que se capturó en el checkout (`customer_snapshot.email`) o, si falta, el de la cuenta del comprador. Si el pedido no tiene ningún correo, no se envía nada.

**Contenido del correo de pago:** número de pedido, productos con miniatura (imagen principal del producto o render del tag; para artículos sin imagen se usa el monograma), subtotal, descuento, impuestos y total en la moneda del pedido, método y dirección de entrega, método de pago, próximos pasos («Cuando recibas tu Finisher Legacy, actívalo desde la app o en finisherlegacy.com/activar», solo si el pedido incluye piezas físicas) y el botón «Ver mi pedido».

**Tarjeta:** solo se muestra «Visa terminación 4242» si el proveedor devolvió marca y últimos 4 dígitos y estos se guardaron en `payments.metadata` (`card_brand`/`card_last4` o `card.brand`/`card.last4`). Hoy ninguna integración guarda esos datos, así que el correo muestra «Tarjeta (pago en línea)». Laravel nunca recibe datos completos de tarjeta.

## Envío único (idempotencia)

1. `MarkOrderPaid` solo emite `OrderPaid` cuando el pedido **no** estaba ya pagado. Además, `ProcessPaymentWebhook` ya ignora eventos repetidos gracias a `payment_webhook_receipts` y a que un mismo estado no produce cambios.
2. Cada listener (`app/Listeners/Commerce/SendOrder*Email`) reclama una fila en la tabla `order_email_deliveries`, que tiene un índice único sobre `order_id + kind + reference`, **antes** de encolar el correo. El reclamo usa `insertOrIgnore`, que es atómico. Cualquier reintento, sincronización o doble clic encuentra la fila ya creada y no envía nada.
   - Pago, envío y entrega: una fila por pedido.
   - Reembolso: `reference = full|partial:{monto}`. Un reintento no se repite, pero un reembolso distinto del mismo pedido sí recibe su propio correo.
3. Si falla el encolado, la fila se libera y el error se reporta. El flujo de pago nunca falla por un problema de correo.

## Diseño

- Layout propio en `resources/views/emails/` (`partials/open` y `partials/close`), con base en el manual de marca: fondo Blanco Hueso `#F8F7F3`, tarjeta blanca, texto Negro Carbono `#0B0B0C`, acentos Oro Legacy `#C9AE8B` y una línea dorada delgada arriba de la tarjeta.
  - Arriba va el logo oficial `fl-firma-horizontal-claro.png`.
  - En el pie va la firma `fl-firma-make-it-last.png`, junto con el correo de soporte (Empresa → Contacto o, si no hay, `hola@finisherlegacy.com`) y los enlaces a Aviso de privacidad y Términos.
- HTML apto para correo: tablas, estilos en línea, ancho de 600 px, botón compatible con Outlook (VML), texto alternativo en las imágenes, preheader, modo oscuro (`prefers-color-scheme` y `[data-ogsc]`) y fuentes Montserrat, Manrope y Arial como respaldo. Cada correo trae su versión de texto plano (`*-text.blade.php`).
- **Notificaciones de Laravel:** el tema de mail se sobrescribe en `resources/views/vendor/mail/**` (layout, botón, panel, subcopy, CSS `themes/default.css`). Por eso verificar correo, restablecer contraseña y `TagActivationReminder` usan el mismo encabezado y pie.
- Las imágenes se cargan con URL absoluta (`asset()`), así que `APP_URL` debe ser la URL pública real con https.

## Vista previa

```bash
php artisan mail:preview paid            # o: refunded, refunded-partial, shipped, delivered, verify-email, reset-password
php artisan mail:preview all
php artisan mail:preview paid --order=FL-XXXXXX   # con un pedido real (uuid o número)
```

Genera `storage/app/mail-previews/{tipo}.html` y `.txt`. Si no se indica un pedido, arma uno en memoria (no se guarda) llamado `VISTA-PREVIA`, con un producto real del catálogo. No escribe nada en la base de datos y no funciona en producción.

## Variables de entorno

Los correos se encolan, así que hace falta un worker: `php artisan queue:work`, u Horizon con Redis.

```dotenv
APP_URL=https://finisherlegacy.com        # se usa en las URLs absolutas del logo, las miniaturas y los botones
QUEUE_CONNECTION=redis                    # o database; en local, "sync" envía al momento

MAIL_FROM_ADDRESS="hola@finisherlegacy.com"   # debe ser un remitente verificado en el proveedor
MAIL_FROM_NAME="Finisher Legacy"
```

**Opción A, SMTP** (cualquier proveedor: Google Workspace, Zoho, Mailgun SMTP, SES SMTP…):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps        # smtps para el puerto 465; null o smtp con STARTTLS para el 587
MAIL_HOST=smtp.proveedor.com
MAIL_PORT=465
MAIL_USERNAME=usuario
MAIL_PASSWORD=contraseña-o-api-key
```

**Opción B, Amazon SES** (API; requiere `composer require aws/aws-sdk-php`):

```dotenv
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
```

**Opción C, Postmark o Resend:** `MAIL_MAILER=postmark` con `POSTMARK_API_KEY=…` (requiere `symfony/postmark-mailer` y `symfony/http-client`), o `MAIL_MAILER=resend` con `RESEND_API_KEY=…` (requiere `resend/resend-php`).

En desarrollo, `MAIL_MAILER=log` escribe los correos en `storage/logs/laravel.log`.

Para la entregabilidad, configura SPF, DKIM y DMARC del dominio remitente en el proveedor elegido.

## Pendiente / notas

- Las notificaciones de Laravel (verificar correo, restablecer contraseña) usan textos en inglés («Hello!», «Verify Email Address», «Regards,»). No existe `lang/es.json`. Al agregarlo con esas cadenas traducidas, quedan en español sin tocar el diseño.
- Las miniaturas de producto son `.webp`. Gmail y Apple Mail las muestran, pero Outlook de escritorio no (verá el texto alternativo). Si se quiere compatibilidad total, hay que subir imágenes principales en JPG o PNG.
- Las fuentes Montserrat y Manrope solo se ven si el cliente las tiene; si no, se usa Arial (no se cargan webfonts por compatibilidad).
