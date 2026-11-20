# Dependencias externas (secretos, cuentas, hardware)

Lo que el código no puede resolver solo. Ningún valor se guarda en el repo:
todo va en el `.env` del servidor, en EAS o en el gestor de secretos.

## Pagos

| Qué | Dónde se usa | Estado |
|---|---|---|
| OpenPay `OPENPAY_MERCHANT_ID`, `OPENPAY_PRIVATE_KEY`, `OPENPAY_PUBLIC_KEY` (sandbox y producción), `OPENPAY_PRODUCTION_MODE` | Checkout web (`FINISHER_PAYMENT_GATEWAY=openpay`), también editable en `/admin/configuracion/pagos` | Falta |
| Webhook OpenPay → `{APP_URL}/api/webhooks/openpay` | Confirmación de pagos | Falta |
| Stripe `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | App móvil (PaymentSheet, `FINISHER_API_PAYMENT_GATEWAY=stripe`) y web si se elige | Falta |
| Webhook Stripe → `{APP_URL}/api/webhooks/stripe` | Idem | Falta |
| Decisión: una sola pasarela para web y app o dos | Contabilidad / conciliación | Pendiente de negocio |
| Apple In-App Purchase / Google Play Billing (productos, acuerdos de pago, datos bancarios/fiscales en ambas consolas) | Solo si se venden bienes digitales dentro de la app | Pendiente de decisión |

## Apple

| Qué | Para qué |
|---|---|
| Cuenta Apple Developer Program (organización) | Builds, TestFlight, App Store |
| **Team ID** → `APPLE_TEAM_ID` | AASA (`/.well-known/apple-app-site-association`) |
| Capacidad **NFC Tag Reading** en el App ID `com.finisherlegacy.app` | Entitlement `com.apple.developer.nfc.readersession.formats` |
| Associated Domains, Sign in with Apple, Push (llave APNs subida a EAS) | Universal Links, login, notificaciones |
| `IOS_APP_STORE_ID` | Banner / enlaces a la tienda |
| iPhones de prueba registrados (XS+ y, si hay, 7/8) | Dev build y matriz NFC |

## Google

| Qué | Para qué |
|---|---|
| Cuenta Google Play Console | Publicación |
| **SHA-256 del certificado de Play App Signing** (+ el de la build interna) → `ANDROID_SHA256_CERT_FINGERPRINTS` | `/.well-known/assetlinks.json` (App Links verificados) |
| Credenciales FCM subidas a EAS | Push |
| `GOOGLE_OAUTH_CLIENT_IDS` (opcional) | Login con Google |
| 2 Android de marcas distintas con NFC | Matriz NFC |

## Expo / EAS

- Cuenta y proyecto EAS existentes (projectId en `app.json`); `EXPO_ACCESS_TOKEN` solo si se activa la seguridad reforzada de push.
- Variables `EXPO_PUBLIC_API_URL`, `EXPO_PUBLIC_WEB_URL` por perfil.

## Infraestructura

| Qué | Para qué |
|---|---|
| VPS Linux (Nginx, PHP-FPM 8.4, MySQL 8, Redis, Supervisor) con acceso SSH | Producción |
| Dominio `finisherlegacy.com` + DNS + TLS (y `ws.` para Reverb) | HTTPS obligatorio para Web NFC y App/Universal Links |
| `LEGACY_TAG_PUBLIC_URL` definitivo | **Se graba en cada chip**: no puede cambiar después de producir |
| Correo transaccional (`MAIL_MAILER` + credenciales, `MAIL_FROM_ADDRESS`) | Registro, recuperación, pedidos |
| Synology: usuario de servicio SMB `svc-fl-media`, carpeta `fl-media`, Tailscale en NAS y VPS, ACL | Medios (`synology-smb.md`) |
| Destino de Hyper Backup (C2 / otro NAS / disco) | Respaldos |
| Monitor externo (uptime + alertas de log) | `/up`, `storage:health` |
| `ffmpeg`/`ffprobe` en el servidor (`FFMPEG_BINARY`) | Video/audio |

## Hardware y proveedores

| Qué | Para qué |
|---|---|
| **Muestras de tags NFC** (20 por diseño/material) del proveedor (USO Card u otro), NTAG213 genuino, NDEF formateado, sin bloquear, con URL por pieza | Protocolo de `especificacion-nfc-proveedor.md` |
| Archivo CSV de códigos generado por la plataforma y entregado al proveedor | Codificación por pieza |
| Medallas reales para prueba de desintonía | T5 |
| Muestra del portarretrato acrílico (Zhihong Acrylic) con su NFC | Lectura con imanes, medida útil 20 × 25 |
| Lector USB NFC (ACR122U / ACR1252U) | QA de lote al 100 % |
| Arte final por colección y archivo maestro del logo | Producción del tag (p. 7–8) |

## Decisiones de negocio pendientes que bloquean código

- Precio y almacenamiento incluido por compra; qué es premium (p. 23).
- Reglas de venta por aliado: ¿el tag de aliado se activa sin registro de venta?
- QR de respaldo: la estrategia (p. 6, p. 11) lo pide; el producto actual decidió "solo NFC".
- Política de privacidad/términos actualizados (voz, fotos de terceros, menores).
