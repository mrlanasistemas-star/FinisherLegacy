# Arquitectura

Visión vigente del sistema (2026-11-20). Detalle técnico ampliado, en inglés:
[`docs/ARCHITECTURE.md`](ARCHITECTURE.md) (flujo de petición, canales,
límites de tasa, observabilidad). Cada módulo tiene su documento en
`docs/renovacion/`.

## 1. Forma general

```
Navegador ── Inertia v3 (Vue 3 + TS) ──┐
                                        ├── Laravel 13 (Actions · Policies · Eloquent) ── MySQL 8
App Expo ─── REST /api/v1 (Sanctum) ───┘        │             │             │
                                                 │ colas       │ broadcast   │ medios
                                          Redis + Horizon   Reverb (WS)   Synology NAS (SMB 3 sobre Tailscale)
Servicios externos: OpenPay · App Store Server API · Google Play Developer API · Expo Push (→ APNs / FCM) · Anthropic/DeepL (traductor, opcional)
```

- **Un monolito, dos clientes.** La web no llama a su propia API: los
  controladores Inertia y los de `/api/v1` usan las mismas Actions
  (`app/Actions/<Dominio>`). Validación con Form Requests, autorización con
  Policies (sin IDOR), respuestas con API Resources, rutas tipadas en Vue con
  Wayfinder.
- **REST es la autoridad.** El tiempo real solo avisa "algo cambió"; los
  clientes vuelven a leer. La caché nunca guarda verdad de negocio.
- `/api/v1` es aditiva (compatibilidad con builds viejas de la app).

## 2. Backend (este repositorio)

| Capa | Dónde |
|---|---|
| Rutas | `routes/web.php` + `routes/web/*.php` (público, legacy, comercio, admin…), `routes/api.php` + `routes/api/*.php`, `routes/channels.php`, `routes/console.php` (scheduler) |
| Casos de uso | `app/Actions/*` (Commerce, LegacyTags, Training, Family, Iap, Media, Notifications, Social…) |
| Servicios | `app/Services/*` (Commerce/gateways, Iap, Media, Portrait, Privacy/ContentPin, I18n, Notifications) |
| Consultas | `app/Queries/*` |
| Modelos | `app/Models/*` (tablas históricas de placas y Stripe se conservan para datos antiguos) |
| Frontend web | `resources/js/pages/*`, `resources/js/components/*`, navegación única `resources/js/config/navigation.ts` |
| Correos | `app/Mail/*`, plantillas `resources/views/emails/` (`docs/renovacion/correos.md`) |
| Permisos | spatie/laravel-permission (`config/permissions.php`), roles editables en el admin |

Autenticación: **Fortify** (sesión web) y **Sanctum** (token de la app,
`auth:sanctum` + `user.token`). Las cuentas suspendidas pierden la API al
instante (`EnsureUserIsActive`).

## 3. App móvil (FinisherLegacyMobile)

Expo SDK 57 · React Native 0.86 · React 19 (React Compiler) · TypeScript ·
Expo Router (rutas tipadas) · TanStack Query (persistida en AsyncStorage) ·
Axios · Zustand · React Hook Form + Zod · SecureStore (token) ·
`react-native-nfc-manager` · `expo-location` + `expo-task-manager` ·
`expo-audio` · `expo-iap` · `expo-notifications` · `expo-web-browser`.

- Cliente HTTP único `src/api/client.ts` con `EXPO_PUBLIC_API_URL`;
  `EXPO_PUBLIC_WEB_URL` define el dominio de enlaces universales y de
  compartir.
- Configuración nativa: `app.json` (identidad estática) + `app.config.ts`
  (plugins, permisos, entitlements, privacy manifest, intent filters).
  Explicación clave por clave: `FinisherLegacyMobile/docs/store/README.md`.
- Lo protegido con NIP nunca se guarda en la caché del teléfono.

## 4. API

`/api/v1`, JSON, Sanctum. Errores `{"error":{"code","message","details"},"request_id"}`;
`Idempotency-Key` en escrituras sensibles (solo recuerda respuestas
exitosas). Contrato: `docs/api/v1.md` y `docs/api/openapi.yaml`. Webhooks en
`/api/webhooks/*`, fuera de la sesión y autenticados por la firma o
credenciales de cada proveedor.

## 5. Almacenamiento (NAS Synology)

| Disco (`config/filesystems.php`) | Raíz | Uso |
|---|---|---|
| `synology_media` | `FINISHER_MEDIA_ROOT` (p. ej. `/mnt/fl-media`, montaje SMB 3 por Tailscale) | Medios procesados (recuerdos, eventos, momentos, entrenamientos). Privado. |
| `media_tmp` | `FINISHER_MEDIA_TMP_ROOT` | Subidas en curso, siempre en el disco local del VPS. |
| `athlete_media`, `event_photo_originals`, `support_audio`, `product_media` | `*_DISK_ROOT` | Discos heredados configurables. |

Todo es privado: se entrega por rutas firmadas (`/media/f/{uuid}/{variante}`)
con TTL (`FINISHER_MEDIA_PRIVATE_URL_TTL`). Variantes de imagen, video
H.264 + póster y audio AAC/m4a se generan en la cola `media` con ffmpeg
(`FFMPEG_BINARY`/`FFPROBE_BINARY`); la duración se mide en el servidor.
`php artisan storage:health` (cada 10 min) verifica montaje, escritura y
espacio. Detalle: `docs/SYNOLOGY.md`, `docs/renovacion/synology-smb.md`.

## 6. Tiempo real (Reverb)

`BROADCAST_CONNECTION=reverb`. Si no hay credenciales, no se transmite nada y
la web hace *polling* (la campana consulta `/notifications/summary`).

| Canal | Tipo | Eventos |
|---|---|---|
| `App.Models.User.{id}` | privado | `notification.created`, `order.updated`, `payment.updated` |
| `admin.commerce` | privado (`orders.view`) | `order.updated`, `payment.updated`, `inventory.updated` |
| `live.{clave}` | público con clave secreta | puntos de seguimiento, `tracking.ended` |
| `community.feed` | público | publicaciones nuevas |

Autorización: `POST /broadcasting/auth` (web) y `POST /api/v1/broadcasting/auth` (app).

## 7. Colas (Horizon)

| Cola | Trabajo |
|---|---|
| `realtime` (`QUEUE_REALTIME`) | broadcasts y push |
| `default` | notificaciones, correos, importaciones |
| `media` (`QUEUE_MEDIA`, conexión `redis-long`) | imágenes, ffmpeg, transferencia al NAS |

Producción: Redis + Horizon (`config/horizon.php`: `supervisor-1` para
realtime+default y `supervisor-media`). `/horizon` solo para `super_admin`.
Local: `QUEUE_CONNECTION=database` y un `queue:listen`. La confirmación de
pagos por webhook **no** se encola (corre en la transacción del webhook; solo
sus efectos secundarios van a la cola).

Scheduler (`routes/console.php`): expirar pedidos (5 min), salud del NAS
(10 min), campañas push (1 min), recibos de Expo (15 min), cierre de
entrenamientos abandonados (1 h), limpieza de tokens, stock bajo, avisos de
planes por vencer y alerta de pasarela sin configurar (diarios).

## 8. Pagos

| Qué se vende | Dónde | Cómo |
|---|---|---|
| Bienes físicos (tags, accesorios, portarretrato) | Web | Openpay.js + 3-D Secure en `/checkout/{pedido}` |
| Bienes físicos | App | `POST /api/v1/orders/{uuid}/checkout-session` → URL de un solo uso `/pago/app/{token}` abierta con `expo-web-browser` → regreso a `finisherlegacy://checkout/return` → la app consulta el pedido |
| Ampliaciones digitales (fotos, videos, voz) | App | `expo-iap` (StoreKit 2 / Play Billing) → `POST /api/v1/iap/purchases` → verificación con App Store Server API / Google Play Developer API |
| Fotos de eventos | Web | OpenPay; reparto con el fotógrafo congelado al pagar |

- **Nunca "pagado" desde el cliente:** solo el webhook de OpenPay
  (`/api/webhooks/openpay`, HTTP Basic + relectura del cargo) o la consulta
  del cargo marcan un pedido como pagado (`MarkOrderPaid`, idempotente con
  `payment_webhook_receipts`).
- Reembolso total desde el admin (`RefundOnlinePayment`, permiso
  `payments.refund`); reembolsos de las tiendas llegan por
  `/api/webhooks/app-store` y `/api/webhooks/google-play` y bajan el límite
  sin borrar contenido.
- Envío: `App\Support\Commerce\ShippingRates` (`FINISHER_SHIPPING_FEE_MINOR`,
  `FINISHER_SHIPPING_FREE_OVER_MINOR`), congelado en `orders.shipping_minor`.
- **Stripe:** retirado de la app. El backend conserva `StripePaymentGateway`
  y `/api/webhooks/stripe` solo para conciliar pagos antiguos.

Detalle: `docs/renovacion/pagos.md`, `docs/renovacion/compras-dentro-de-app.md`,
`docs/renovacion/cuotas-y-precios.md`, `FinisherLegacyMobile/docs/COMMERCE_ARCHITECTURE.md`.

## 9. Push y notificaciones

`SendAthleteNotification` guarda en `notifications` (centro web + app),
transmite `notification.created` y llama a `PushSender` →
`SendPushBatchJob` (≤ 100 por petición) → **Expo Push** → APNs / FCM.
`PUSH_PROVIDER=expo` activa `ExpoPushNotificationGateway`; cualquier otro
valor usa el gateway nulo (no envía, no falla). El servidor no guarda llaves
de APNs/FCM: se suben a EAS. `push:check-receipts` desactiva tokens muertos.
Preferencias por categoría; alertas a administradores (`NotifyAdmins`);
campañas desde el admin. Detalle: `docs/renovacion/notificaciones.md`,
`docs/renovacion/campanas-push.md`.

## 10. NFC

- Cada pieza (tag o portarretrato) es una unidad de inventario
  (`legacy_tags`) con `public_code` y un **código de activación** (HMAC con
  `APP_KEY`, CSV en claro una sola vez al crear el lote).
- Activación: `tags/activation/start` entrega la única URL grabable
  `{LEGACY_TAG_PUBLIC_URL}/t/{código}` → el teléfono la escribe como NDEF URI
  → la vuelve a leer → `…/confirm` valida la URL y liga el hash del UID del
  chip (único por chip, migración `2026_11_20_400000_make_legacy_tag_nfc_uid_hash_unique`).
- Al tocar el tag se abre `/t/{código}`; con la app instalada se abre la app
  (Universal Links / App Links: `/.well-known/apple-app-site-association`
  necesita `APPLE_TEAM_ID`; `/.well-known/assetlinks.json` necesita
  `ANDROID_SHA256_CERT_FINGERPRINTS`). Rutas reclamadas por la app:
  `/n/*`, `/t/*`, `/reset-password/*`, `/live/*`, `/familia/invitacion/*`
  (`config/nfc.php` › `app.paths`, igual que los intent filters de `app.config.ts`).
- iOS usa `NFCTagReaderSession` (entitlement solo `TAG`); Android
  `android.permission.NFC` con NFC no obligatorio.
- `/n/{token}` y `/l/{código}` siguen resolviendo chips y placas históricos.

Detalle: `docs/renovacion/manual-activacion-nfc.md`,
`docs/renovacion/especificacion-nfc-proveedor.md`,
`docs/renovacion/matriz-pruebas-nfc.md`, `docs/NFC.md`,
`FinisherLegacyMobile/docs/NFC_ACTIVATION.md`.

## 11. Idiomas (i18n)

El texto en español es la llave (estilo gettext, `t('…')`); en otro idioma
un texto sin traducir cae al español. Idiomas en `config/i18n.php`
(`APP_SUPPORTED_LOCALES`): español e inglés. Catálogo en base de datos +
archivos generados (`lang/*.json`, `lang/web`, `lang/mobile`); la app recibe
su catálogo por `GET /api/v1/i18n/*` y además trae `src/i18n/locales/en.json`.
El servidor responde en el idioma del cliente (`SetLocale`, también en la
API). Prefijo `/en/...` para SEO. Traductor automático opcional
(`I18N_TRANSLATOR` = `null` por defecto, o Anthropic / DeepL). Detalle:
`docs/renovacion/idiomas.md`.

## 12. Privacidad y seguridad

- NIP opcional (`App\Services\Privacy\ContentPin`): HMAC + hash, bloqueo
  progresivo, `423 CONTENT_LOCKED` para el dueño sin desbloqueo, 404 para los
  demás (`docs/renovacion/nip.md`).
- Ubicación: solo con consentimiento explícito; compartir con familia o por
  enlace siempre termina con la sesión (`docs/renovacion/familia.md`).
- Tokens públicos ≥ 128 bits aleatorios; IPs y UIDs solo como HMAC.
- `request_id` en cada petición, log y job; canal de log `payments`.
