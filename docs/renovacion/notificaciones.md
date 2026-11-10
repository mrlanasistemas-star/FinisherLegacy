<!--
  Notificaciones de Finisher Legacy: push (Expo), centro de notificaciones
  web + app, preferencias por categoría y alertas para administradores.
  Fuente de verdad para operar, probar y extender (API pública PushSender).
-->

# Notificaciones: push, centro de notificaciones y alertas admin

## 1. Cómo funciona (resumen)

```
Evento de negocio ─► SendAthleteNotification ─► fila en `notifications` (centro web + app)
                                               ├► realtime `notification.created` (Reverb, si está activo)
                                               └► PushSender ─► SendPushBatchJob (≤100 por request) ─► Expo Push ─► FCM v1 / APNs
                                                                   └► push_tickets ─► push:check-receipts (cada 15 min)

Evento operativo ─► NotifyAdmins ─► AdminAlert (campana admin, correo según preferencia) + PushSender
```

* **Proveedor:** Expo Push Service (`https://exp.host/--/api/v2/push/send`). El servidor **nunca** guarda llaves de FCM ni APNs: se suben a EAS y Expo las usa.
* **Binding:** `PUSH_PROVIDER=expo` → `App\Services\Notifications\ExpoPushNotificationGateway`. Cualquier otro valor → `NullPushNotificationGateway` (no envía, no falla, no simula envíos).
* **Cola:** todo push sale por la cola `realtime` (`config/queue.php` → `queue.names.realtime`). Horizon ya tiene supervisor para ella.

## 2. API pública para otros módulos (campañas push, etc.)

```php
use App\Enums\NotificationCategory;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushSender;

$summary = app(PushSender::class)->sendToUsers($userIds, PushMessage::make('Título', 'Texto')
    ->withDeepLink('/store/products/playera')      // ruta web, finisherlegacy://… o https://…
    ->withImage('https://cdn…/foto.jpg')           // imagen grande (Android); iOS requiere extensión
    ->withCategory(NotificationCategory::Promociones) // respeta la preferencia "push" de cada usuario
    ->withData(['notification_id' => $id, 'type' => 'broadcast', 'campaign' => $uuid]));

$summary->toArray(); // configured, users, skipped_by_preference, devices, batches
```

| Campo de `PushMessage` | Uso |
|---|---|
| `title`, `body` | Lo que muestra el teléfono (todo el payload ≤ 4 KB). |
| `data` | Contrato de enrutamiento de la app: `notification_id`, `type`, `target`, `action_url` (+ llaves extra pequeñas, nunca secretos). |
| `deepLink` | Se envía como `data.action_url`. |
| `imageUrl` | `richContent.image` + `mutableContent: true` (solo `https://`). |
| `channel` | Canal Android (`default`, `pedidos`, `comunidad`, `entrenamientos`, `avisos`). Si es `null` se usa el de la categoría. |
| `badge` | Badge iOS. `null` = no leídas reales del usuario (centro de cliente, sin alertas admin). |
| `category` | Si se indica, se omite a quien apagó el push de esa categoría. |
| `color` | Reservado: el color de acento Android lo fija el plugin en el build (Expo Push no acepta color por mensaje). |
| `sound`, `ttlSeconds` | `default` / caducidad. |

* `sendToUsers()` filtra por preferencias, busca **todos** los dispositivos activos de cada usuario y encola un `SendPushBatchJob` por cada 100 dispositivos. Opcional `delaySeconds`.
* `isConfigured()` = si hay proveedor real (para mostrar u ocultar opciones de push en la UI).
* Para **bandeja + push** de un usuario usa `App\Actions\Notifications\SendAthleteNotification::handle($user, $title, $message, NotificationType::Broadcast, $actionUrl, $metadata, push: true, imageUrl: …, category: NotificationCategory::Promociones)`. Devuelve el id de la notificación guardada (o `null` si el usuario apagó "en la app" para esa categoría).
* Alertas para administradores: `App\Actions\Notifications\NotifyAdmins::handle(AdminAlertCategory, $event, $title, $message, $url, $metadata, throttleKey: …, throttleMinutes: …)`.

## 3. Tipos de notificación de cliente y su categoría

| Tipo (`NotificationType`) | Origen | Categoría / canal |
|---|---|---|
| `order_paid`, `order_shipped`, `order_delivered`, `order_refunded` | `App\Events\Commerce\Order*` → `Listeners\Notifications\NotifyCustomerOfOrder` | Pedidos / `pedidos` |
| `tag_activated` | `LegacyTagEvent` tipo `activated` → `Listeners\AdminAlerts\ModelAlerts` | Pedidos / `pedidos` |
| `payment_pending`, `order_ready`, `legacy_plate_ready` | Formularios admin | Pedidos |
| `new_follower`, `moment_reaction`, `moment_comment` | `NotifySocialActivity` (ahora con push; reacciones máx. 1 push/hora por persona y momento) | Comunidad / `comunidad` |
| `training_cheer` | `SubmitCheerMessage` | Entrenamientos / `entrenamientos` |
| `support_reply` | `SupportCase` cambia de estado o resolución → avisa a quien lo reportó | Soporte / `default` |
| `result_available`, `event_updated`, `custom` | Formularios admin / API admin | Avisos / `avisos` |
| `broadcast` | Campañas push (P6b) | Avisos o Promociones / `avisos` |

Todas se guardan en la tabla `notifications` (clase `AthleteAlert`). **El centro web (`/notifications`) y la app (`GET /api/v1/me/notifications`) leen las mismas filas**, y marcar como leída en cualquiera (`POST …/read`, `…/read-all`) se refleja en la otra. Las alertas admin (`AdminAlert`) quedan fuera de ambos.

Fila de la API: `{id, title, message, type, action_url, category, image_url, target, read_at, created_at}`. `target` trae el formato canónico `{type, id|uuid, slug?, event_slug?}` (igual que `data.target` del push) **y** las llaves antiguas `{kind, username, moment_uuid, order_uuid}` para builds anteriores de la app. `?filter=unread` filtra no leídas; `meta.unread_count` es el total real.

## 4. Preferencias

Tabla `notification_preferences` (`user_id`, `scope` customer|admin, `category`, `in_app`, `push`, `email`). Sin fila = valor por defecto.

* **Cliente** (web: Ajustes › Notificaciones, `/settings/notificaciones`; app: Ajustes › Notificaciones, `GET|PUT /api/v1/me/notifications/preferences`): por categoría, "en la app y la web" y "push". Por defecto todo encendido salvo **push de Promociones** (apagado).
  * `in_app` apagado → no se guarda la fila (excepto mensajes enviados a mano por un admin).
  * `push` apagado → no se envía push.
* **Admin** (Administración › Alertas › Preferencias, `/admin/alertas/preferencias`): por categoría, correo y push. La campana siempre recibe todo. Correo encendido por defecto solo en Inventario, Soporte y Pagos.

Se aplican **en el servidor** (`NotificationPreferences`, usado por `SendAthleteNotification`, `PushSender` y `NotifyAdmins`).

## 5. Alertas para administradores

Campana propia en la barra superior del panel (`AdminAlertBell`, junto a la campana personal) con contador y menú; lista completa en `/admin/alertas`. Solo reciben cada categoría quienes tienen su permiso (o `super_admin`).

| Categoría | Permiso | Eventos |
|---|---|---|
| Ventas | `orders.view` | Pedido pagado (`OrderPaid`), pago rechazado (`Payment` → `failed`), reembolso (`OrderRefunded`) |
| Inventario | `inventory.manage` | Stock bajo / agotado por variante (tras cada movimiento, 1 aviso por estado cada 24 h, se rearma al reabastecer), resumen diario 08:00, lote de Tags recibido, Tag reportado extraviado/robado |
| Activaciones | `tags.view` | Tag activado |
| Soporte | `support.view` | Caso de soporte nuevo, mensaje de contacto, solicitud de eliminación de cuenta |
| Comunidad | `community.moderate` | Reporte nuevo |
| Fotografía | `photos.manage` | Venta de foto |
| Pagos | `payments.view` | Webhook de OpenPay/Stripe que responde 4xx/5xx (máx. 1 cada 30 min por estado), OpenPay activo sin configurar (diario 08:05) |

Stock: usa la misma regla que la pantalla de Inventario (`InventoryStock`): disponible = existencia − reservado en la ubicación de checkout; mínimo = `minimum_stock` de la variante, si no del producto, si no `finisher.commerce.default_minimum_stock`. Revisión manual: `php artisan inventory:check-low-stock [--variant=ID]`.

## 6. App (Expo SDK 57)

* `getExpoPushTokenAsync({ projectId })` con `extra.eas.projectId` de `app.json` (ya existe: `f6dce084-4b31-4f64-980a-917784b1f5c3`, owner `zuriel2026s-team`). Si algún día falta, correr `eas init` (o `EXPO_PUBLIC_EAS_PROJECT_ID` como respaldo).
* Canales Android creados al iniciar la app: `default`, `pedidos`, `comunidad`, `entrenamientos`, `avisos`.
* Ícono de notificación monocromo `assets/images/notification-icon.png` (96×96, blanco sobre transparente, generado del monograma) y color de acento `#C9AE8B` vía opciones del plugin `expo-notifications` en `app.config.ts`.
* Badge iOS: el servidor manda las no leídas; la app además sincroniza `setBadgeCountAsync` con el contador del centro y lo limpia al cerrar sesión.
* Tocar una notificación: misma regla que la fila del centro (`notification-href.ts`): `target` → ruta nativa; si no, `action_url` mapeada; si no, el navegador.

### Requiere un build nativo nuevo (EAS)

* Ícono monocromo y color de acento (opciones del plugin): se aplican en prebuild → **nuevo build**.
* Imagen en iOS: requiere una **Notification Service Extension** (target nativo; no incluida). Sin ella iOS muestra la notificación sin imagen. Android muestra la imagen grande sin cambios nativos.
* Canales, preferencias, badge y enrutamiento: JS, llegan por actualización OTA.

## 7. Configuración externa

| Dónde | Qué |
|---|---|
| `.env` servidor | `PUSH_PROVIDER=expo` (obligatorio para enviar). `EXPO_ACCESS_TOKEN=` (opcional; **obligatorio** si activas *Enhanced push security* en expo.dev › Project › Credentials). Opcionales: `EXPO_PUSH_URL`, `EXPO_RECEIPTS_URL`, `EXPO_ANDROID_CHANNEL_ID`. |
| Scheduler | `php artisan schedule:run` cada minuto (cron): `push:check-receipts` cada 15 min, `inventory:check-low-stock` diario, revisión de pasarela diaria. |
| Cola | Worker/Horizon procesando la cola `realtime`. |
| EAS › Credentials › Android | Subir la **cuenta de servicio de FCM V1** (JSON de Firebase › Configuración › Cuentas de servicio). Y `google-services.json` como variable de archivo `GOOGLE_SERVICES_JSON` (ya leída por `app.config.ts`). |
| EAS › Credentials › iOS | **Llave APNs (.p8)** con Key ID y Team ID (EAS la puede generar en `eas credentials`). |
| Correo | `MAIL_*` configurado para las alertas admin por correo. |

Web push para navegadores (VAPID + service worker): **no existe base en el repo** (no hay service worker ni llaves VAPID). Recomendación: si se quiere, agregar `laravel-notification-channels/webpush`, un service worker propio y un canal `webpush` en `AthleteAlert`/`AdminAlert`; hoy la web usa realtime (Reverb) + sondeo cada 60 s.

## 8. Probar un push en un teléfono real

1. Build de desarrollo o preview instalado (Expo Go no recibe push remoto). Inicia sesión.
2. Ajustes › Notificaciones › **Activar notificaciones** y acepta el permiso.
3. Comprueba el registro: `php artisan tinker` → `App\Models\PushDevice::where('user_id', ID)->where('active', true)->count()`.
4. Envía uno:
   ```php
   app(App\Support\Notifications\PushSender::class)->sendToUser(ID,
       App\Support\Notifications\PushMessage::make('Prueba', 'Hola desde Finisher Legacy')
           ->withDeepLink('/notifications')->withImage('https://…/imagen.jpg'));
   ```
   (con la cola corriendo; o `QUEUE_CONNECTION=sync` en local).
5. También puedes probar el token directo en https://expo.dev/notifications.
6. 15 min después: `php artisan push:check-receipts --min-age=0` muestra recibos y desactiva tokens muertos.

## 9. Checklist de problemas

* **No llega nada:** `PUSH_PROVIDER=expo`? ¿worker de la cola `realtime` corriendo? ¿`push_devices.active=1` para el usuario? ¿la preferencia push de esa categoría está encendida?
* **Error `InvalidCredentials` en logs (`push.expo.ticket_error` / `push.expo.receipt_error`):** faltan/expiraron FCM V1 (Android) o APNs (iOS) en EAS.
* **401 de Expo:** *Enhanced push security* activo sin `EXPO_ACCESS_TOKEN` o token revocado.
* **`DeviceNotRegistered`:** app desinstalada o token rotado; el dispositivo se desactiva solo y se vuelve a registrar al abrir la app.
* **`MessageRateExceeded` / 429 / 5xx:** se reintenta solo (30 s, 2 min, 10 min) únicamente para los dispositivos afectados.
* **Android sin sonido o en silencio:** el canal se creó con otra importancia en una instalación anterior; el usuario debe cambiarlo en ajustes del sistema o reinstalar (Android no permite subir la importancia de un canal existente).
* **Ícono blanco cuadrado en Android:** el build no incluye `notification-icon.png`; hacer un build nuevo.
* **iOS sin imagen:** esperado sin Notification Service Extension.
* **Badge incorrecto:** el badge del push = no leídas del centro de cliente al momento del envío; la app lo corrige al abrir.
* **Simulador:** no recibe push remoto (iOS simulador ni emulador sin Google Play Services).
