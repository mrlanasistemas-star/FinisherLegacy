# Pendientes externos

**Solo lo que no se resuelve con código**: cuentas, contratos, llaves,
decisiones de negocio y pruebas con hardware real. Todo el código que los usa
ya existe y, mientras falten, responde "no configurado" sin simular nada.

Cada variable de este documento existe en `config/*.php` (verificado con
`grep`). Los valores van en el `.env` del servidor (después:
`php artisan config:cache`), en EAS o en el admin; **nunca en Git**.

Complementos: lista operativa de las tiendas en
`FinisherLegacyMobile/docs/store/pendientes-externos.md`; pasos detallados en
`docs/renovacion/pagos.md` y `docs/renovacion/compras-dentro-de-app.md`.

Orden sugerido: 1 → 2 → 3 → 4 → 5 (las cuentas de Apple y Google tardan
semanas en aprobarse; conviene iniciarlas primero).

---

## 1. OpenPay (pagos de bienes físicos, web y app)

### 1.1 Cuenta y KYC

- **Qué falta:** cuenta de comercio OpenPay México con la documentación
  aprobada (KYC), cuenta bancaria de depósito y afiliación de tarjetas.
- **Plataforma / URL:** https://www.openpay.mx (sandbox:
  https://sandbox-dashboard.openpay.mx · producción:
  https://dashboard.openpay.mx). Sandbox y producción son **cuentas distintas**.
- **Qué seleccionar:** alta de comercio; confirmar con el ejecutivo si la
  afiliación exige 3-D Secure y qué marcas están habilitadas.
- **Verificar:** el panel de producción muestra la cuenta activa.

### 1.2 Llaves

- **Pantalla:** panel OpenPay › Configuración › Llaves de API (sandbox y luego producción).
- **Datos que genera:** Merchant ID (`m…`), llave pública (`pk_…`), llave privada (`sk_…`, secreta).
- **Dónde configurarlo:** **Admin › Configuración › Pagos** (`/admin/configuracion/pagos`;
  la llave privada se guarda cifrada y tiene prioridad sobre `.env`) o `.env`:
  `OPENPAY_MERCHANT_ID`, `OPENPAY_PUBLIC_KEY`, `OPENPAY_PRIVATE_KEY`,
  `OPENPAY_COUNTRY=MX`, `OPENPAY_PRODUCTION_MODE` (`false` = sandbox),
  `OPENPAY_USE_3D_SECURE`. Pasarela de la web: `FINISHER_PAYMENT_GATEWAY=openpay` (valor por defecto).
- **Verificar:** botón **Probar conexión** en Admin › Configuración › Pagos →
  "Conexión correcta con OpenPay". La alerta diaria "pasarela sin configurar"
  deja de llegar.

### 1.3 Webhook

- **Dónde configurarlo primero:** Admin › Configuración › Pagos → usuario y
  contraseña del webhook (≥ 12 caracteres) → `.env` alternativo:
  `OPENPAY_WEBHOOK_USER`, `OPENPAY_WEBHOOK_PASSWORD`.
- **Pantalla OpenPay:** Configuración › Webhooks › Agregar.
- **Qué seleccionar:** URL `https://finisherlegacy.com/api/webhooks/openpay`
  (HTTPS, puerto 443), autenticación **básica** con el mismo usuario y
  contraseña, eventos `charge.succeeded`, `charge.failed`, `charge.cancelled`,
  `charge.refunded`.
- **Datos que genera:** un código de verificación que OpenPay envía al
  webhook; aparece en Admin › Configuración › Pagos ("Código de verificación
  recibido", dura 1 h) → pegarlo en OpenPay › Verificar.
- **Verificar:** la lista "Para habilitar OpenPay" marca el webhook como
  verificado y "Última notificación de OpenPay" tiene fecha.
- Repetirlo **en sandbox y en producción**.

### 1.4 Pruebas y paso a producción

- Compra en sandbox (web y app) con las tarjetas de
  https://documents.openpay.mx/docs/testing.html, rechazo, 3-D Secure y
  reembolso desde el panel (`docs/renovacion/pagos.md` §E).
- Producción: llaves de producción + "Modo producción" encendido
  (`OPENPAY_PRODUCTION_MODE=true`) + webhook de producción + compra real de
  monto bajo y reembolso desde **Admin › Ventas › Pagos y reembolsos**.

## 2. Decisión de negocio: costo de envío

- **Qué falta:** la tarifa real de envío a domicilio (hoy `0` = sin costo) y
  si hay envío gratis a partir de un monto.
- **Dónde configurarlo:** `.env` — `FINISHER_SHIPPING_FEE_MINOR` (centavos,
  p. ej. `15000` = $150.00 MXN) y `FINISHER_SHIPPING_FREE_OVER_MINOR`
  (centavos del subtotal con descuento; vacío = nunca gratis). Recoger en
  tienda siempre es gratis.
- **Verificar:** `/checkout` con envío muestra el costo, el total del pedido
  lo incluye (`orders.shipping_minor`) y OpenPay cobra ese total.

Otras decisiones de negocio abiertas: precio del portarretrato
(`php artisan catalog:publish-portrait-frame` lo deja en borrador) y precios
de cada ampliación por tienda (Admin › Ventas › Límites y tarifas).

## 3. Apple

| # | Qué falta | URL / pantalla | Qué seleccionar | Dato que genera | Dónde va | Cómo verificar |
|---|---|---|---|---|---|---|
| 3.1 | **Apple Developer Program** (organización; requiere número D-U-N-S) | https://developer.apple.com/programs/enroll/ | Organization | Cuenta de equipo | — | https://developer.apple.com/account muestra la membresía activa |
| 3.2 | **Team ID** | developer.apple.com/account › Membership details | — | Team ID (10 caracteres) | `.env` `APPLE_TEAM_ID` (genera `/.well-known/apple-app-site-association`); `eas.json` › `submit.production.ios.appleTeamId` (hoy `REEMPLAZAR_APPLE_TEAM_ID`) | `curl https://finisherlegacy.com/.well-known/apple-app-site-association` devuelve JSON con `TEAMID.com.finisherlegacy.app` (antes: 404) |
| 3.3 | Capacidades del App ID `com.finisherlegacy.app` | Certificates, Identifiers & Profiles › Identifiers | **NFC Tag Reading**, **Associated Domains**, **Push Notifications**, **In-App Purchase** (EAS puede activarlas al compilar) | — | — | `eas build -p ios` sin errores de entitlement |
| 3.4 | **App en App Store Connect** | https://appstoreconnect.apple.com › Apps › + | Bundle `com.finisherlegacy.app`, idioma principal Español (México) | Apple ID numérico de la app | `eas.json` › `ascAppId` (hoy `REEMPLAZAR_ASC_APP_ID`); `.env` `IOS_APP_STORE_ID` (banner) y opcional `APPLE_APP_APPLE_ID` (filtra notificaciones); `LEGACY_APP_IOS_URL` (enlace de descarga en la página del tag) | `eas submit -p ios` encuentra la app |
| 3.5 | **Paid Apps Agreement** + datos bancarios y fiscales (W-8BEN-E para empresa mexicana) | App Store Connect › Business (Acuerdos, impuestos y banca) | Firmado por el Account Holder | — | — | Estado *Active*; sin él la app no carga productos y oculta "Ampliar espacio" |
| 3.6 | **Compras dentro de la app** (consumibles; planes por periodo = Non-Renewing Subscription) | App Store Connect › tu app › Monetización › Compras dentro de la app › + | Tipo **Consumible**, un producto por ampliación (ej. `com.finisherlegacy.app.fotos10`), precio, captura de revisión | Product ID | **Admin › Ventas › Límites y tarifas** (`/admin/limites-y-tarifas`) → ampliación → ID de Apple (y por bandera de precio en "Avanzado") | `GET /api/v1/iap/offer?story=…&platform=ios` devuelve el `product_id`; el primer producto se envía junto con una versión de la app |
| 3.7 | **Llave de App Store Server API** | App Store Connect › Usuarios y acceso › Integraciones › Compras dentro de la app › generar llave | — | Key ID, Issuer ID, archivo `.p8` (se descarga una sola vez) | `.env` `APPLE_IAP_KEY_ID`, `APPLE_IAP_ISSUER_ID`, `APPLE_IAP_PRIVATE_KEY` (contenido PEM o ruta absoluta), `APPLE_BUNDLE_ID` (por defecto `com.finisherlegacy.app`), `APPLE_IAP_ALLOW_SANDBOX=true` | Compra sandbox en TestFlight acredita el espacio (sin credenciales: `503 IAP_NOT_CONFIGURED`) |
| 3.8 | **App Store Server Notifications V2** (reembolsos) | App Store Connect › tu app › Información de la app › App Store Server Notifications | Versión 2; URL de producción y de sandbox `https://finisherlegacy.com/api/webhooks/app-store` | — | — | "Enviar notificación de prueba" → 200 |
| 3.9 | **Llave APNs** (push iOS) | https://developer.apple.com/account/resources/authkeys/list (o `eas credentials -p ios` › Push Notifications) | Apple Push Notifications service | `.p8` + Key ID | **EAS** (no el servidor). `.env` `PUSH_PROVIDER=expo`; `EXPO_ACCESS_TOKEN` solo si se activa "Enhanced push security" en EAS | Un push de prueba llega a un iPhone con build de TestFlight |
| 3.10 | Sandbox testers | App Store Connect › Usuarios y acceso › Sandbox | Correos que no sean Apple ID reales | — | iPhone › Ajustes › App Store › Cuenta de sandbox | Compra, cancelar, restaurar y reembolso de prueba |

## 4. Google

| # | Qué falta | URL / pantalla | Qué seleccionar | Dato que genera | Dónde va | Cómo verificar |
|---|---|---|---|---|---|---|
| 4.1 | **Cuenta de Google Play Console** (organización recomendada: requiere D-U-N-S y evita los 12 testers × 14 días) | https://play.google.com/console | Organización | — | — | Consola activa |
| 4.2 | **Perfil de pagos** (cuenta bancaria + datos fiscales) | Play Console › Configuración › Perfil de pagos (https://payments.google.com) | Merchant | — | — | "Monetizar › Productos" disponible tras subir un AAB con el permiso BILLING |
| 4.3 | **App** `com.finisherlegacy.app` y primer AAB **subido a mano** | Play Console › Crear app; Pruebas › Prueba interna | — | — | `.env` `GOOGLE_PLAY_PACKAGE_NAME` (por defecto `com.finisherlegacy.app`); `LEGACY_APP_ANDROID_URL` (enlace de descarga) | La versión interna se instala desde Play |
| 4.4 | **SHA-256 de Play App Signing** | Play Console › Pruebas y lanzamiento › Configuración › Integridad de la app › Firma de apps | Certificado de la clave de firma de apps (y el de la clave de subida/EAS si se prueban builds fuera de Play) | Huella SHA-256 | `.env` `ANDROID_SHA256_CERT_FINGERPRINTS` (separadas por coma) | `curl https://finisherlegacy.com/.well-known/assetlinks.json` devuelve el JSON (antes: 404); Play Console › Vínculos profundos marca el dominio como verificado; tocar un tag abre la app |
| 4.5 | **Cuenta de servicio** para verificar compras | https://console.cloud.google.com/iam-admin/serviceaccounts (habilitar https://console.cloud.google.com/apis/library/androidpublisher.googleapis.com) y Play Console › Usuarios y permisos › Invitar | Permisos para la app: **Ver información financiera** y **Gestionar pedidos y suscripciones** | JSON de la llave | Archivo fuera del repo (permisos 600) → `.env` `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` (ruta absoluta) | Compra con cuenta de pruebas de licencias acredita el espacio |
| 4.6 | **Productos integrados** | Play Console › Monetizar › Productos › Productos únicos | Producto único (la app lo consume), ej. `fotos_10` | Product ID | **Admin › Ventas › Límites y tarifas** → ID de Google | `GET /api/v1/iap/offer?…&platform=android` devuelve el `product_id` |
| 4.7 | **RTDN** (reembolsos) por Pub/Sub | https://console.cloud.google.com/cloudpubsub y Play Console › Monetizar › Configuración de monetización › Notificaciones en tiempo real | Tema (ej. `play-rtdn`) con rol Pub/Sub Publisher para `google-play-developer-notifications@system.gserviceaccount.com`; suscripción **push** a `https://finisherlegacy.com/api/webhooks/google-play` con autenticación habilitada | Audiencia (= esa URL) y correo de la cuenta de servicio del push | `.env` `GOOGLE_PLAY_RTDN_AUDIENCE`, `GOOGLE_PLAY_RTDN_SERVICE_ACCOUNT` (alternativa sin OIDC: `GOOGLE_PLAY_RTDN_TOKEN` y `?token=` en la URL) | "Enviar notificación de prueba" en Play Console → 200 |
| 4.8 | **FCM** (push Android) | https://console.firebase.google.com → proyecto → app Android `com.finisherlegacy.app`; guía https://docs.expo.dev/push-notifications/fcm-credentials/ | Descargar `google-services.json`; crear llave de cuenta de servicio FCM V1 | `google-services.json` + JSON de FCM V1 | `eas env:create --environment production --name GOOGLE_SERVICES_JSON --type file --value ./secrets/google-services.json` (lo usa `app.config.ts`) y `eas credentials -p android` › FCM V1. Servidor: `PUSH_PROVIDER=expo` | Push de prueba llega a un Android con build de Play |
| 4.9 | Cuenta de servicio para `eas submit` | Play Console › Usuarios y permisos | Permiso de publicación | JSON | `FinisherLegacyMobile/secrets/google-play-service-account.json` (ignorado por Git) | `eas submit -p android` sube a la pista interna |
| 4.10 | Pruebas de licencias | Play Console › Configuración › Pruebas de licencias | Cuentas Gmail de prueba | — | — | Compra aprobada, rechazada y pendiente |

## 5. Traductor automático (opcional)

- **Qué falta:** solo si se quiere traducción automática de textos nuevos.
  Sin traductor, `i18n:sync --no-translate` + el panel de Idiomas bastan
  (inglés ya está completo en el repo).
- **Plataforma / URL:** Anthropic https://console.anthropic.com (API keys) o
  DeepL https://www.deepl.com/pro-api.
- **Dónde configurarlo:** `.env` `I18N_TRANSLATOR=anthropic` +
  `ANTHROPIC_API_KEY` (modelo `I18N_ANTHROPIC_MODEL`, por defecto
  `claude-opus-5-5`), o `I18N_TRANSLATOR=deepl` + `DEEPL_API_KEY`.
- **Verificar:** Admin › Idiomas deja de mostrar el aviso de "sin traductor
  configurado"; `php artisan i18n:sync` (sin `--no-translate`) traduce lo
  pendiente y `php artisan i18n:status` sube el porcentaje.

## 6. Legal, fiscal, contacto y bancario

| Qué falta | Dónde se usa | Dónde se configura | Cómo verificar |
|---|---|---|---|
| Revisión legal del Aviso de privacidad y los Términos; **razón social y domicilio** del responsable (LFPDPPP) | `/privacy`, `/terms`, fichas de ambas tiendas | Texto en `resources/js/pages/Privacy.vue` y la página de términos (cambio de código una vez definido el dato) | Las URLs abren sin sesión con el texto final |
| Datos fiscales y bancarios en OpenPay, Apple y Google | Cobros y depósitos | Cada consola (§1, §3.5, §4.2) | Estado activo en cada una |
| Correo de soporte `hola@finisherlegacy.com` funcionando y proveedor de correo transaccional | Contacto, recuperación de contraseña, correos de pedidos y NIP | `.env` `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (o `RESEND_API_KEY` / `POSTMARK_API_KEY`); datos de contacto en Admin › Contenido del sitio | Un "olvidé mi contraseña" real llega al buzón (`php artisan mail:preview` solo genera vistas previas locales) |
| Contacto para App Review y cuenta de revisión | Revisión de las tiendas | `php artisan app:review-account revision@finisherlegacy.com` en producción | Las credenciales entran en la app |
| Moderación diaria (24 h) | Términos y notas de revisión | Admin › Contenido › Comunidad y moderación; Soporte › Mensajes | Reportes atendidos |

## 7. Infraestructura

| Qué falta | Dónde se configura | Cómo verificar |
|---|---|---|
| `LEGACY_TAG_PUBLIC_URL` definitivo (**se graba en cada chip**: no puede cambiar después) | `.env` (por defecto `https://finisherlegacy.com`) | La URL que entrega `tags/activation/start` es la definitiva |
| Dominio con TLS para `finisherlegacy.com` y `ws.` (Reverb) | Nginx / DNS; `.env` `REVERB_*`, `VITE_REVERB_*` (se compilan en `npm run build`) | WebSocket conectado en el navegador |
| Synology: usuario `svc-fl-media`, carpeta `fl-media`, Tailscale en NAS y VPS, Hyper Backup | `docs/renovacion/synology-smb.md`; `.env` `FINISHER_MEDIA_ROOT`, `FINISHER_MEDIA_TMP_ROOT` | `php artisan storage:health` en verde |
| ffmpeg/ffprobe en el VPS | `apt install ffmpeg`; `.env` `FFMPEG_BINARY`, `FFPROBE_BINARY` si el PATH del worker es reducido | `storage:health` › Binario = OK |
| Monitor externo de `/up` y alertas de log | Servicio de monitoreo | Alerta de prueba |

## 8. Pruebas con hardware real

Nada de esto se ha verificado en dispositivos físicos; el código está probado
con pruebas automáticas y emulador.

| Prueba | Guía | Necesita |
|---|---|---|
| **Matriz NFC** (escribir y releer NTAG213/215/216, cancelar, chip ya escrito, chip de otra pieza, sin NFC, iPhone XS+ y 2 Android de marcas distintas, tags reales del proveedor y medallas para desintonía) | `FinisherLegacyMobile/docs/NFC_ACTIVATION.md`, `docs/renovacion/matriz-pruebas-nfc.md`, `docs/renovacion/especificacion-nfc-proveedor.md` | Build nativa nueva, muestras del proveedor, lector USB (ACR122U/ACR1252U) para QA de lote |
| **GPS y segundo plano** (entrenar al aire libre y seguimiento en vivo con pantalla bloqueada, ahorro de batería, permiso "Siempre" en iOS, servicio en primer plano en Android, sin conexión) | `FinisherLegacyMobile/docs/LIVE_TRACKING.md`, `FinisherLegacyMobile/docs/DEVICE_QA.md` | Teléfonos reales en exteriores |
| **Audio en segundo plano** (mensajes de ánimo con la pantalla bloqueada) | `FinisherLegacyMobile/docs/TRAINING_AUDIO.md` | Teléfonos reales |
| **Push** (entrega, apertura con deep link, recibos) | `docs/renovacion/notificaciones.md` §8 | §3.9 y §4.8 resueltos |
| **Enlaces universales / App Links** (`/t/*`, `/live/*`, `/familia/invitacion/*`, `/reset-password/*`) | `docs/ARQUITECTURA.md` §10 | §3.2 y §4.4 resueltos |
| **Compras dentro de la app** (compra, pendiente, sin red, restaurar, reembolso) | `docs/renovacion/compras-dentro-de-app.md` §E | §3.5–3.8 y §4.2–4.7 |
| **Video de demostración** para las tiendas (NFC + seguimiento + audio en segundo plano) | `FinisherLegacyMobile/docs/store/review-notes-es-en.md` | Teléfono real |

## 9. Material para las tiendas

- Capturas reales en teléfono (no simuladas): `FinisherLegacyMobile/docs/store/screenshots.md`.
- Gráfico de funciones 1024×500 e ícono 512×512 (Google Play).
- Imagen real del portarretrato (hoy es el render conceptual).
