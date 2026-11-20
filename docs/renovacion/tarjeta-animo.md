# Tarjeta de ánimo NFC y precios de los mensajes de ánimo

> Extiende «Envíame ánimo» ([entrenamientos.md](./entrenamientos.md)): una
> tarjeta física con chip NFC que abre un enlace de ánimo, y precios
> configurables para los mensajes. Código: `app/Actions/Training`,
> `app/Services/Training/CheerQuota.php`, `app/Support/Training/{CheerCards,CheerQuotaDecision,CheerPresenter}.php`,
> `app/Http/Controllers/Training/{CheerPublicController,CheerPaymentController}.php`,
> `app/Http/Controllers/Api/V1/Training/TrainingCardController.php`,
> `app/Http/Controllers/Admin/Quotas/CheerPricingController.php`. Pruebas:
> `tests/Feature/Training/CheerCardsAndPricingTest.php`.

## Qué es

- **Tarjeta de ánimo NFC**: producto físico de la tienda. Quien la tiene la
  acerca a su celular y se abre en el navegador la página para dejarle un
  mensaje de voz al atleta, **sin cuenta y sin app**. Cada vez que la acercan
  pueden dejar otro mensaje (según los límites de abajo).
- **Precios de los mensajes**: el administrador decide cuántos mensajes son
  gratis por enlace y por tarjeta, y qué pasa después (bloquear, seguir
  gratis o cobrar). Si se cobra, **paga quien envía el mensaje**, en la web,
  con OpenPay.

## Diseño

| Decisión | Por qué |
|---|---|
| La tarjeta es un `LegacyTag` con `purpose = cheer` y `cheer_link_id` | El chip guarda `/t/{code}` como cualquier Tag: se reusan el escritor NFC de la app (`isAllowedWriteUrl` solo permite `/t/{code}`), los intentos de activación, el antiduplicado por UID, los lotes, el inventario y el kardex (`legacy_tag_events`). |
| `purpose` lo decide el producto al crear el lote (`CheerCards::purposeFor`) | El cliente no puede convertir un Tag de recuerdo en tarjeta ni al revés. |
| Una tarjeta nunca crea un recuerdo | `ActivateTag` la rechaza (`cheer_card`); la única forma de dueño es `BindCheerCard`. |
| El origen del mensaje es una marca de sesión | `/t/{code}` guarda en la sesión «esta visita viene de la tarjeta X» (2 h). No se puede falsificar con un parámetro de la URL. |
| Los precios se congelan en el pedido | Cambiar la tarifa nunca toca un pago en curso. |
| Créditos de paquete por cookie | Quien envía no tiene cuenta: una cookie aleatoria `cheer_sender` (solo se guarda su HMAC). Borrar los datos del navegador pierde los créditos, y la página lo dice antes de pagar. |

## Flujo de la tarjeta

1. **Compra** (web o app): al agregarla al carrito el atleta elige el enlace
   que abrirá (`cheer_link_uuid`; en la app hay «Comprar tarjeta para este
   enlace» en cada enlace y se puede crear uno nuevo, p. ej. «Para mamá»).
   Se guarda en la línea del carrito (`metadata.cheer_link_id`) y se congela
   en el pedido (`order_items.metadata.cheer_link = {id, uuid, title}`), igual
   que la personalización del Portarretrato. Envío a cualquier dirección (la
   del atleta o la de su familiar): el checkout ya pide la dirección.
2. **Surtido (admin)**: al surtir la línea (`FulfillOrderItem` →
   `AssignTagsToOrderItem`), cada pieza asignada se **vincula sola** al enlace
   elegido (`BindCheerCard`, `via = fulfillment`) y queda `activated` para el
   dueño del enlace. La tarjeta llega lista. Si el enlace ya no existe o fue
   desactivado, la pieza sale sin vincular (no se bloquea el envío) y el
   atleta la vincula en la app.
3. **Activación en la app** (tarjeta comprada sin enlace, regalada o
   vendida en un punto de venta): Activar → «Tarjeta de ánimo» → elegir
   enlace → escribir el chip. API: `POST /api/v1/tags/activation/start` con
   `purpose: "cheer"` y `cheer_link_uuid` → `confirm`. La respuesta trae
   `purpose: "cheer"`, `story: null` y `cheer_link`.
4. **Uso**: `/t/{code}` →
   - vinculada → redirige a `/animo/{token}` (marca la sesión como tarjeta);
   - sin vincular o desactivada → página amable «Esta Tarjeta de ánimo no
     está activa» (`activate/Unavailable`, `reason = cheer_inactive`);
   - desactivada por staff, extraviada, robada… → la página honesta de siempre.
5. **Cambiar o desactivar** (app): `PUT /api/v1/training/cards/{uuid}`
   `{link_uuid}` y `DELETE /api/v1/training/cards/{uuid}/link`. Solo el
   dueño y solo hacia sus propios enlaces (todo lo ajeno es 404).

### Producción de las tarjetas

- Las tarjetas son **NTAG213 en blanco** (tamaño tarjeta de crédito).
- Se reciben por lote del producto `tarjeta-animo-nfc` en Inventario →
  Lotes: cada pieza es un `LegacyTag` con `purpose = cheer`, su código
  público y su código de activación (CSV del lote, para imprimir en la
  tarjeta o en su sobre).
- **Opción A — Finisher escribe al surtir**: se escribe el chip con
  `https://finisherlegacy.com/t/{code}` (misma herramienta/proceso que los
  Tags) y se surte el pedido: el vínculo con el enlace se hace solo.
- **Opción B — el cliente activa**: la tarjeta sale en blanco con su código
  de activación; el cliente la escribe y la vincula desde la app.
- Nunca escribir otra URL: el servidor solo acepta `/t/{code}` de su propio
  dominio (`legacy_tags.public_base_url`).

## Reglas de cuota y precio (`CheerQuota`)

Ajustes por canal (`cheer_pricing_settings`, un registro para `link` y otro
para `nfc_card`):

| Campo | Significado |
|---|---|
| `free_messages` | Mensajes gratis **por enlace** (canal link) o **por tarjeta** (canal nfc_card). |
| `after_free` | `blocked` (no acepta más) · `free` (gratis sin límite) · `paid` (cobra a quien envía). |
| `hard_limit` | Tope total opcional por enlace / tarjeta (gratis + pagados + pendientes). Vacío = sin tope. |
| `price_minor` | Precio de un mensaje adicional (centavos MXN). **Nunca se inventa**: sin precio, un canal `paid` se comporta como `blocked`. |
| `price_steps` | Banderas: `[{from_paid, amount_minor}]` — desde el mensaje pagado número N de ese enlace/tarjeta, cada uno cuesta otro precio. |
| `packs` | `[{messages, amount_minor}]` — paga varios de una vez; los que sobran quedan como créditos (`cheer_sender_credits`). |
| `allow_anonymous` | El nombre se vuelve opcional; el atleta ve «Anónimo». |

- **Sin registro = comportamiento de siempre**: `free_messages =
  TRAINING_CHEER_MAX_MESSAGES_PER_LINK` (200), luego `blocked`, nombre
  obligatorio, nada se cobra.
- Ámbitos: el canal enlace cuenta los mensajes `source = link` del enlace;
  cada tarjeta cuenta sus mensajes `source = nfc_card` (cambiarla de enlace
  no reinicia su cuenta).
- Un mensaje oculto por el atleta sigue contando (ocultar no libera lugar).
  Un mensaje pendiente de pago aparta su lugar contra el tope mientras dure
  su ventana de pago.
- Cada enlace conserva su tope de seguridad (`cheer_links.max_messages`, 200
  por defecto) contando todos sus mensajes.
- La decisión se toma de nuevo **bajo el bloqueo de la fila del enlace** al
  guardar, así el último lugar gratis nunca se da dos veces.

## Pagos de mensajes adicionales

```
/animo/{token}  (la página muestra el precio ANTES de grabar)
  → POST: mensaje `pending_payment` (invisible, sin notificación) + Order de invitado
          (user_id null, producto interno `mensaje-de-animo`, monto congelado)
  → /animo/{token}/pago/{order}            formulario Openpay.js (OpenPayCardForm)
  → POST …/cargo                           CreateOnlinePayment (monto del Order)
  → [3-D Secure] → …/confirmacion          SOLO estado del servidor; consulta …/estado
  → webhook OpenPay / re-lectura del cargo → MarkOrderPaid → DeliverPaidCheerMessages
          → el mensaje pasa a `visible`, cuenta en el enlace y se notifica al atleta
```

- **Nunca se entrega desde el navegador**: ni el regreso de 3-D Secure ni la
  página de confirmación marcan nada; solo `MarkOrderPaid` (webhook o
  `SyncOnlinePayment`, el mismo camino de la tienda).
- **Idempotente**: la entrega bloquea la fila del mensaje y solo actúa sobre
  `pending_payment`; los créditos de paquete son únicos por `order_item_id`.
  Un webhook repetido no cambia nada (prueba incluida).
- **Acceso sin cuenta**: la página de pago solo abre en el navegador que
  grabó el mensaje (alcance en sesión `cheer_checkout`, por pedido, ventana
  de pago + 30 min). Todo lo demás es 404.
- **Cancelar**: «Cancelar y borrar mi mensaje» cancela el pedido y borra el
  audio (solo si no hay un cargo en curso).
- **Sin pago → se borra**: `php artisan training:purge-unpaid-cheers`
  (programado cada 10 min). Pasada la ventana (`order_payment_expiry_minutes`,
  60 min): si el pedido se pagó, lo entrega; si sigue pendiente, lo expira
  primero (`ExpirePendingOrder`, ya no podrá marcarse pagado) y luego borra
  audio y texto (`status = expired`).
- **Sin llaves de OpenPay**: la página lo avisa y el envío responde «Por
  ahora no podemos cobrar mensajes adicionales. Intenta más tarde.» **antes**
  de guardar nada.
- Es web: no aplica compra dentro de la app (lo paga quien envía, en su
  navegador).

## Anonimato y moderación

- Con `allow_anonymous`, la casilla «Enviar como anónimo» oculta el nombre;
  se guarda `anonymous = true` y la API y la web muestran «Anónimo».
- Bloquear / reportar siguen igual: el bloqueo es el HMAC de la IP del
  remitente para ese enlace (`cheer_blocks`), con o sin nombre.

## API (Sanctum + `user.token`)

| Método | Ruta | Qué |
|---|---|---|
| GET | `/training/links` | Ahora con `state` y `stats {free_used, free_limit, after_free, paid_count, cards_count, card_messages_count}`. |
| GET | `/training/messages` | Cada mensaje con `anonymous` y `source` (`link` / `nfc_card`). |
| GET | `/training/cards` | Mis tarjetas `{uuid, code, active, link, stats, activated_at, bound_at}` + `meta.offer`. |
| GET | `/training/card-offer` | `{available, product_slug, product_variant_uuid, name, price_minor, currency, image_url}`. |
| PUT | `/training/cards/{uuid}` | `{link_uuid}` → vincular / cambiar de enlace. |
| DELETE | `/training/cards/{uuid}/link` | Desactivar (sin enlace). |
| POST | `/cart/items` | Acepta `cheer_link_uuid` para la línea de la tarjeta; el carrito devuelve `cheer_link`. |
| POST | `/tags/activation/start` | Acepta `purpose` (`story`/`cheer`) y `cheer_link_uuid`; responde `purpose` y `cheer_link`. |

## Admin

- **Ventas → Límites y tarifas → Mensajes de ánimo**
  (`/admin/limites-y-tarifas/mensajes-de-animo`, permiso `quotas.manage`):
  ajustes por canal, conteos (gratis, pagados, de paquete, esperando pago,
  cobrado) y aviso si OpenPay no está configurado. Solo conteos: el
  personal nunca lee los mensajes.
- **Pedido**: cada línea de tarjeta muestra el enlace que abrirá y las
  piezas asignadas con su vínculo; un mensaje pagado muestra canal y paquete.
- **Tags**: insignia «Tarjeta de ánimo» en la lista y, en el detalle, a qué
  enlace abre `/t/{code}`.

## Publicar el producto

```bash
php artisan migrate
php artisan catalog:publish-cheer-card              # borrador sin precio
php artisan catalog:publish-cheer-card --price=<MXN> # publicado con el precio que decida el dueño
```

Idempotente: si ya existe no cambia precio, nombre ni estado. Después: subir
fotos en Admin → Productos y recibir un lote en Inventario → Lotes.

## Migración

`2026_11_20_700000_create_cheer_cards_and_pricing.php` (aditiva y
reversible): columnas nuevas en `legacy_tags`, `legacy_tag_activation_intents`
y `cheer_messages`; tablas `cheer_pricing_settings` y `cheer_sender_credits`;
producto interno `mensaje-de-animo`.

## Pendiente (externo)

- Llaves de OpenPay, webhook y compra de prueba en sandbox
  ([pagos.md](./pagos.md)); sin ellas el canal de pago queda cerrado con aviso.
- Precio real de la tarjeta y de los mensajes (los decide el dueño).
- Fotos del producto y el primer lote de tarjetas NTAG213.
- Probar la lectura en iPhone y Android con las tarjetas reales.
