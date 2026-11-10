# Campañas push

<!-- Administración → Contenido → Campañas push: componer, segmentar, programar y medir notificaciones push masivas. -->

Panel para que el equipo componga y envíe notificaciones push a la comunidad.
Ruta: **`/admin/notificaciones/campanas`** (Contenido → Campañas push).
Permiso: **`notifications.broadcast`** (admin y super_admin, migración
`2026_11_11_700100_create_notifications_broadcast_permission`).

El envío real usa la infraestructura de push de `docs/renovacion/notificaciones.md`
(`App\Support\Notifications\PushSender` + `PushMessage`). Este módulo no tiene
su propio emisor.

## Flujo

1. **Componer** (`Compose.vue`): título (≤65) y mensaje (≤240), con contador.
   Solo texto: se rechaza cualquier `<etiqueta>` y además se limpian las
   etiquetas en el servidor. La vista previa (iPhone en pantalla de bloqueo +
   Android colapsada y expandida) se actualiza mientras escribes.
   - **Imagen grande** (opcional): JPG/PNG ≤1 MB, disco `public`
     (`push-campaigns/`). Se ve en Android; en iPhone solo cuando la app
     incluya su Notification Service Extension.
   - **Incluir logo**: si no hay imagen, el monograma oficial
     (`/images/brand/logo/fl-monograma.png`) va como imagen grande (Android lo
     muestra también como ícono grande al estar colapsada) y en
     `data.large_icon`. Se aplica donde la plataforma lo permite.
   - **Color de acento**: no se elige. Expo no envía un color por mensaje; el
     acento es siempre el dorado de la marca (`#C9AE8B`), fijo en la app
     (`app.config.ts`). La columna `push_campaigns.color` se conserva sin uso.
   - **Destino**: Inicio, Tienda, Colección X, Producto X, Entrenamientos,
     Mis recuerdos, Evento X, o un enlace `https://` de `finisherlegacy.com`
     (o subdominio). Ver `CampaignDestination`.
   - **Canal Android**: `avisos` (predeterminado) o `default`.
   - **También guardar en el centro de notificaciones** (activado por defecto).
2. **Audiencia** (`CampaignAudience`) con alcance estimado en vivo
   (personas, dispositivos, copias en el centro):
   - Todos con notificaciones activas · usuarios específicos (búsqueda por
     nombre/correo, hasta 500) · participantes de un evento (opcional edición
     y distancia) · dueños de tags de una colección · compradores de un
     producto (pedido pagado) · por rol · suscritos a novedades y promociones.
3. **Envío**: ahora o programado (fecha y hora de **America/Mexico_City**,
   guardado en UTC). Antes de enviar o programar hay un paso de confirmación
   con el alcance.
4. **Historial** (`Index.vue`) y **detalle** (`Show.vue`): estado, audiencia,
   push en cola, entregadas / fallidas (acuses), copias en el centro, omitidos, aperturas y
   bitácora. Duplicar crea un borrador nuevo (con copia propia de la imagen).

Estados: `draft` Borrador · `scheduled` Programada · `sending` Enviando ·
`sent` Enviada · `cancelled` Cancelada · `failed` Con errores. Solo los
borradores y las programadas se editan, reprograman o cancelan.

## Preferencias y categorías

Una campaña lleva una categoría de `App\Enums\NotificationCategory`:

- `promociones` (Novedades y promociones). Es la única permitida para «Todos»
  y «Suscritos». Su push está **apagado por defecto**: solo llega a quien lo
  activó en Ajustes › Notificaciones. La copia en el centro sí está activa por
  defecto.
- `avisos`: solo para audiencias dirigidas (evento, producto, colección,
  usuarios, rol).
- Las categorías transaccionales (`pedidos`, `comunidad`, `entrenamientos`,
  `soporte`) **nunca** se envían como campaña.

Una persona entra en la audiencia si el push está permitido y tiene un
dispositivo activo, o (si se guarda en el centro) si tiene activado in-app.
Las preferencias se vuelven a leer en cada lote, y `PushSender` las filtra
otra vez. Solo cuentas `active` o `pending`.

## Seguridad

- `notifications.broadcast` en rutas (`can:` middleware) y en el FormRequest.
- **Límite a «Todos»**: como máximo `PUSH_CAMPAIGNS_ALL_PER_HOUR` (1 por
  defecto, `config/push_campaigns.php`) en cualquier ventana de 60 minutos,
  contando las enviadas y las programadas. Se valida al programar o enviar, y
  otra vez justo antes de iniciar una programada. Si se rebasa, la campaña
  queda «Con errores» sin enviarse.
- Prueba «Enviarme una prueba»: solo a los dispositivos del admin, con
  «[Prueba]» en el título y sin filtro de preferencias.
- Bitácora `push_campaign.*` en el activity log: quién creó, editó,
  programó, envió, canceló o duplicó. Nunca guarda el texto ni los tokens.

## Envío por lotes

`CampaignDispatcher`:

1. `start()`: cambia a `sending` de forma atómica (el scheduler y «Enviar
   ahora» nunca la inician dos veces). Congela la audiencia en
   `push_campaign_recipients` (lotes de `PUSH_CAMPAIGNS_BATCH_SIZE`, 500) y
   encola un `App\Jobs\SendPushCampaignBatch` por lote.
2. `sendBatch()`: reclama las filas pendientes del lote (at-most-once: un
   reintento nunca notifica dos veces), guarda la copia en el centro con
   `SendAthleteNotification` (`push: false`) y hace **una** llamada
   `PushSender::sendToUsers()` con todo el lote.
3. Cuando no quedan filas pendientes: `sent`, o `failed` si algún lote agotó
   sus reintentos.

Programadas: `php artisan push-campaigns:dispatch`, cada minuto
(`routes/console.php`). Requiere el scheduler (`schedule:work` o cron) y un
worker de colas.

## Datos que recibe la app

`data`: `{notification_id: null, type: "campaign", target, action_url, campaign_id, large_icon?}`.
`action_url` es el deep link (`finisherlegacy://store`,
`finisherlegacy://collections/{slug}`, `finisherlegacy://products/{slug}`,
`finisherlegacy://events/{slug}`, `finisherlegacy://training`,
`finisherlegacy://my-legacy`, `finisherlegacy://home` o la URL https).

### Aperturas

Al tocar la notificación, la app llama a
**`POST /api/v1/notifications/{id}/opened`** (Sanctum). `{id}` es
`data.campaign_id`, o el id de la notificación del centro. Solo marca la fila
de quien llama, la primera vez (`data.recorded`). Siempre responde 200.

### Entregadas / fallidas

«Push en cola» es el número de dispositivos que se entregaron a `PushSender`.
Cada ticket de Expo de un envío de campaña guarda `push_tickets.push_campaign_id`
(el gateway lo resuelve de `data.campaign_id`; los envíos de prueba no cuentan).
`push:check-receipts` lee el acuse ~15 min después y suma a
`push_campaigns.delivered_count` (acuse `ok`) o `delivery_failed_count` (acuse
con error, o mensaje que Expo rechazó al momento). Son contadores porque los
tickets se borran a los dos días. En cola − entregadas − fallidas = «esperando
acuse» (un acuse que nunca llega se queda ahí).

## Archivos

- Migraciones: `2026_11_11_700000_create_push_campaigns_table`,
  `2026_11_11_700100_create_notifications_broadcast_permission`.
- Modelos: `App\Models\PushCampaign`, `PushCampaignRecipient`.
- Acciones: `App\Actions\Notifications\Campaign{Audience,Destination,Dispatcher,Manager,Message,OpenTracker,RateGuard}`.
- Job: `App\Jobs\SendPushCampaignBatch`.
- Controladores: `App\Http\Controllers\Admin\Notifications\PushCampaign{,Lookup}Controller`,
  `Api\V1\NotificationOpenedController`. Request: `Admin\PushCampaignRequest`.
- Rutas: `routes/web/admin-notifications.php`, `routes/api/push-campaigns.php`,
  `routes/console.php` (`push-campaigns:dispatch`).
- Config: `config/push_campaigns.php`.
- Páginas: `resources/js/pages/admin/notifications/campaigns/{Index,Compose,Show}.vue` + `partials/`.
- Pruebas: `tests/Feature/Admin/PushCampaigns/`.
