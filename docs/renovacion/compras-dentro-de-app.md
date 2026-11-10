<!--
  Compras dentro de la app (StoreKit 2 / Google Play Billing) de ampliaciones
  de fotos de un recuerdo Legacy. Implementado el 2026-11-11. Si cambias el
  flujo, actualiza este archivo y docs/store/app-store.md / google-play.md de
  la app móvil.
-->

# Compras dentro de la app — ampliaciones de fotos

**Resumen:** dentro de la app móvil, el espacio extra para fotos de un
recuerdo (un bien digital) se vende **solo** con la facturación de la tienda:
StoreKit 2 en iOS y Google Play Billing en Android, como exigen la App Store
Review Guideline 3.1.1 y la política de Pagos de Google Play. La web sigue
vendiéndolo con OpenPay (`docs/renovacion/cuotas-y-precios.md`). La app
**nunca** enlaza a la compra web (sin «anti-steering»).

El código está completo y probado. **Para cobrar de verdad falta lo externo**
(cuentas, contratos, productos en las tiendas, llaves): ver
[Para habilitarlo](#para-habilitarlo). Sin credenciales, todos los endpoints
responden `503 IAP_NOT_CONFIGURED` y la app oculta «Ampliar espacio».

---

## 1. Decisiones

| Tema | Decisión | Por qué |
|---|---|---|
| Librería | **`expo-iap` 6.4.x** (hyochan / OpenIAP) | Mantenida, módulo Expo con *config plugin* (agrega el permiso `BILLING` y la capacidad), compatible con Expo SDK 57 y la Nueva Arquitectura, StoreKit 2 + Play Billing 8. Dependencias pares: `expo`, `react`, `react-native` (`*`) y dos paquetes de Amazon **opcionales** que no se instalan (verificado en `node_modules/expo-iap/package.json`). `react-native-iap` v14 requiere Nitro Modules, una dependencia nativa más. |
| Tipo de producto | **Consumible** (iOS «Consumable», Play «producto único» que se consume) | Cada ampliación se puede comprar varias veces y para distintos recuerdos. Un no consumible por recuerdo exigiría un producto por historia (imposible de dar de alta en la tienda). El derecho vive en **nuestro** servidor (`media_entitlements`), creado por transacción verificada. |
| «Restaurar compras» | Reenvía al servidor las transacciones **sin terminar** que la tienda aún tiene, y vuelve a leer la cuota | Los consumibles no se «restauran» en StoreKit; lo ya otorgado está ligado a la cuenta de Finisher Legacy en el servidor y no se pierde al cambiar de teléfono. Apple exige el botón si hay cualquier compra integrada; lo tenemos en la hoja de compra. |
| Precio | Lo fija la tienda (App Store Connect / Play Console). La app muestra el `displayPrice` localizado del SDK, **nunca** un número nuestro. | Moneda e impuestos por país los maneja la tienda. |
| Monto de la Order | iOS: `price`/`currency` del JWS firmado por Apple (`amount_source=store`). Android: Play no devuelve el precio en `purchases.products.get`; se guarda el `priceAmountMicros`/moneda que mostró el SDK (`amount_source=client_reported`). Solo informativo: el dinero lo cobró la tienda. | |
| Cuenta | `appAccountToken` (iOS) / `obfuscatedAccountId` (Android) = UUID estable derivado de `HMAC(APP_KEY, user_id)`. Si la transacción lo trae y no coincide con quien la envía → `409 IAP_PURCHASE_OTHER_ACCOUNT`. | Evita acreditar a otra cuenta una transacción ajena. |

## 2. Flujo

```
App (recuerdo)                    Backend                             Tienda
GET /api/v1/stories/{id}/quota  → límite / usadas
GET /api/v1/iap/offer?story=&platform=
                                → product_id del pack + account_token
fetchProducts([product_id])  ──────────────────────────────────────→ precio localizado
[usuario toca "Comprar por $…"]
(guarda en el teléfono: producto → recuerdo)
requestPurchase(sku, accountToken) ────────────────────────────────→ hoja de pago de la tienda
purchaseUpdatedListener ←─────────────────────────────────────────── transacción
POST /api/v1/iap/purchases      → verifica con Apple/Google ───────→ App Store Server API /
                                                                     Play Developer API
                                  StorePurchase (único) + Order pagada
                                  + OrderItem + Payment → MarkOrderPaid
                                  → GrantPhotoExpansionEntitlements
                                  Android: acknowledge ────────────→
                                ← 201 {purchase, quota}  (200 si ya estaba)
finishTransaction(consumible)  ─────────────────────────────────────→ (solo después del 201/200)
refrescar cuota
```

Regla de oro: **la app termina (consume) la transacción solo después de que
el servidor otorgó el espacio**, o cuando el servidor dice que la tienda la
anuló (`IAP_PURCHASE_NOT_VALID`). Cualquier falla de red, de la tienda o de
configuración deja la transacción sin terminar: se reintenta sola al abrir la
app (`useIapRecovery`) y con «Restaurar compras». El servidor es idempotente,
así que nunca se otorga dos veces.

### Estados que maneja la app

| Situación | Qué ve el usuario |
|---|---|
| Sin módulo nativo (Expo Go / dev client viejo), tienda sin configurar, o sin producto para ese recuerdo | Solo el contador de fotos; no aparece «Ampliar espacio». |
| Canceló la hoja de la tienda | Vuelve al botón de compra, sin mensaje. |
| Pendiente (Ask to Buy, pago en efectivo en Android) | «Pago pendiente…»; se acredita solo cuando la tienda lo confirma (listener o siguiente arranque). |
| «Ya lo tienes» (Android: compra anterior sin consumir) | Se acredita la anterior y se pide reintentar. |
| Sin red / servidor caído al confirmar | «Tu compra está a salvo…»; reintento automático. |
| Producto no corresponde / otra cuenta | Mensaje claro; la transacción **no** se termina (el dinero no se pierde; soporte lo resuelve). |

## 3. API

Todas con `auth:sanctum` + `user.token`. Errores `{"error":{"code","message","details"}}`; `details.retryable` indica si la app debe reintentar.

### `GET /api/v1/iap/offer?story={publicId}&platform=ios|android`

Solo el dueño (otro usuario: 404).

```json
{"data": {"story": "ab12cd34", "platform": "ios", "available": true, "reason": null,
  "product_id": "com.finisherlegacy.app.fotos10",
  "pack": {"id": 1, "name": "…", "description": null, "photos": 10},
  "account_token": "1f0c…-4…", "quota": {"limit": 5, "used": 5, "remaining": 0, "can_expand": true, "expansion": null}}}
```

`reason`: `not_configured` (sin credenciales) o `no_product` (ningún pack que
cubra el recuerdo tiene ID de producto para esa plataforma). El pack ofrecido
es el más específico **activo** que cubre el recuerdo y tiene ID para esa
tienda; no necesita precio web.

### `POST /api/v1/iap/purchases`

```json
{"platform": "ios", "product_id": "com.finisherlegacy.app.fotos10", "story_public_id": "ab12cd34",
 "transaction_id": "2000000123456789", "jws": "…opcional…"}
{"platform": "android", "product_id": "fotos_10", "story_public_id": "ab12cd34",
 "purchase_token": "…", "price_amount_micros": 99000000, "currency": "MXN"}
```

| Respuesta | Significado |
|---|---|
| `201` | Verificada y otorgada. `data.purchase`, `data.quota`. |
| `200` `replayed: true` | Esa transacción ya estaba registrada (misma cuenta). No se otorga nada nuevo. |
| `404` | El recuerdo no existe o no es tuyo. |
| `409 IAP_PURCHASE_PENDING` | Android `purchaseState=2`. Reintentar más tarde. |
| `409 IAP_PURCHASE_OTHER_ACCOUNT` | La transacción es de otra cuenta. |
| `422 IAP_PRODUCT_MISMATCH` | El producto no es una ampliación, no coincide con lo que reporta la tienda, o su alcance no cubre ese recuerdo. |
| `422 IAP_VERIFICATION_FAILED` | La tienda no la conoce, firma JWS inválida, bundle/paquete distinto, o sandbox con `APPLE_IAP_ALLOW_SANDBOX=false`. |
| `422 IAP_PURCHASE_NOT_VALID` | Reembolsada / revocada / cancelada. La app la termina sin otorgar. |
| `503 IAP_STORE_UNAVAILABLE` | Apple/Google no respondieron (o rechazaron nuestras credenciales — revisar logs). Reintentable. |
| `503 IAP_NOT_CONFIGURED` | Faltan credenciales de esa plataforma. |

**Verificación iOS:** `GET https://api.storekit.itunes.apple.com/inApps/v1/transactions/{id}`
con JWT ES256 (`APPLE_IAP_KEY_ID`, `APPLE_IAP_ISSUER_ID`, `APPLE_IAP_PRIVATE_KEY`,
`bid=APPLE_BUNDLE_ID`); si responde `4040010` se reintenta en
`api.storekit-sandbox…` (TestFlight, sandbox, revisión de Apple). El
`signedTransactionInfo` se verifica además contra **Apple Root CA - G3**
(`resources/certs/apple/AppleRootCA-G3.cer`, SHA-256
`63:34:3A:BF:…:3E:91:79`, descargado de apple.com/certificateauthority):
cadena x5c de 3 certificados, OIDs de Apple en hoja (`1.2.840.113635.100.6.11.1`)
e intermedio (`1.2.840.113635.100.6.2.1`) y firma ES256.

**Verificación Android:** OAuth de cuenta de servicio (JWT RS256 →
`oauth2.googleapis.com/token`, alcance `androidpublisher`), luego
`GET …/applications/{GOOGLE_PLAY_PACKAGE_NAME}/purchases/products/{productId}/tokens/{token}`.
`purchaseState` 0 = otorgar, 2 = pendiente, 1 = cancelada. Después del grant,
`POST …:acknowledge` (si falla se registra en el log; el consumo de la app
también confirma). La clave de idempotencia es `sha256(purchaseToken)`.

### Reembolsos (notificaciones de las tiendas)

| Endpoint | Autenticación | Efecto |
|---|---|---|
| `POST /api/webhooks/app-store` (App Store Server Notifications **V2**) | `signedPayload` y su `signedTransactionInfo` verificados contra Apple Root CA - G3 (mismo verificador). Firma inválida → 400. `bundleId` distinto → 200 ignorada. | `REFUND`, `REVOKE` → revoca. `REFUND_REVERSED` → restaura. Todo lo demás (`TEST`, `CONSUMPTION_REQUEST`…) → 200. |
| `POST /api/webhooks/google-play` (RTDN por Pub/Sub **push**) | Preferido: token OIDC de la suscripción push (`Authorization: Bearer`), verificado con las llaves públicas de Google, `aud = GOOGLE_PLAY_RTDN_AUDIENCE` y correo `GOOGLE_PLAY_RTDN_SERVICE_ACCOUNT`. Alternativa: `?token=GOOGLE_PLAY_RTDN_TOKEN`. Inválido → 401. | `voidedPurchaseNotification` (reembolso, contracargo) y `oneTimeProductNotification` tipo 2 (cancelada) → revoca. Todo lo demás → 200. |

Revocar = `store_purchases.status=revoked`, Order `payment_status=refunded`,
Payment `refunded` y `RevokePhotoExpansionEntitlements` (pone `revoked_at`).
**Nunca se borra una foto**: el recuerdo las sigue mostrando todas y solo no
admite nuevas mientras esté por encima del límite.

## 4. Datos

- `photo_expansion_packs.apple_product_id`, `google_product_id` (únicos, nulos = no se vende en la app). Se editan en **Admin › Límites y tarifas** (`/admin/limites-y-tarifas`), en el formulario de cada ampliación, con la nota de que el precio de la app lo fija la tienda.
- `store_purchases`: una fila por transacción verificada (`platform`, `transaction_key` único por plataforma, `transaction_id`, `purchase_token` cifrado, `product_id`, `user_id`, `legacy_story_id`, `photo_expansion_pack_id`, `order_id`, `status`, `environment`, `quantity`, `amount_minor`, `currency`, `amount_source`, `purchased_at`, `acknowledged_at`, `revoked_at`, `revocation_reason`).
- La Order: `sales_channel = app_store | play_store` (nuevos valores de `SalesChannel`, con etiqueta en el admin y el tablero), `payment_status = paid`, `status = confirmed`, `fulfillment_status = fulfilled`. OrderItem `metadata.kind = photo_expansion` (lo mismo que la web; `GrantPhotoExpansionEntitlements` no distingue el origen) + `store`, `store_product_id`, `store_transaction_id`. Payment `provider = app_store | google_play`, `method = in_app_purchase` (no aparece entre los métodos de pago manual).
- `AppPurchasePolicy` sigue bloqueando OpenPay para bienes digitales en la app; la compra integrada no pasa por el checkout.

Migración: `database/migrations/2026_11_11_200000_add_in_app_purchases.php` (aditiva).

## 5. Archivos

Backend: `config/iap.php`, `resources/certs/apple/AppleRootCA-G3.cer`,
`app/Services/Iap/{AppleJwsVerifier,AppStoreVerifier,GooglePlayVerifier,InAppPurchaseCatalog,VerifiedStoreTransaction}.php`,
`app/Actions/Iap/{RecordStorePurchase,RevokeStorePurchase}.php`,
`app/Models/StorePurchase.php`, `app/Exceptions/Iap/IapException.php`,
`app/Http/Controllers/Api/V1/Iap/IapPurchaseController.php`,
`app/Http/Controllers/Api/Webhooks/{AppStoreNotificationController,GooglePlayNotificationController}.php`,
`app/Http/Requests/Api/Iap/StoreIapPurchaseRequest.php`, `routes/api/iap.php`,
admin `ExpansionPackController` / `QuotaController` / `pages/admin/quotas/Index.vue`.
Pruebas: `tests/Feature/Iap/InAppPurchaseTest.php` (Http::fake; cadena de
certificados de prueba generada en cada corrida).

App (`FinisherLegacyMobile`): `src/features/iap/*` (driver con carga
perezosa, servicio, máquina de estados + pruebas Jest, hoja de compra, tarjeta
de cuota), `src/api/iap.ts`, `src/schemas/iap.ts`, pantallas
`src/app/stories/index.tsx` y `src/app/stories/[publicId].tsx`, entrada en la
pestaña Legado, `useIapRecovery()` en `src/app/_layout.tsx`, plugin
`expo-iap` en `app.config.ts`. **Requiere una nueva build nativa.**

---

## Para habilitarlo

### A. Contratos y cuentas (fuera del código)

1. **Apple:** App Store Connect › Business › **Paid Apps Agreement** firmado
   por el Account Holder, con **datos bancarios** y **formularios fiscales**
   (W-8BEN-E para una empresa mexicana; los formularios de impuestos locales
   que pida Apple). Sin el contrato en estado *Active* los productos nunca se
   cargan (`fetchProducts` devuelve vacío y la app oculta el botón).
2. **Google:** Play Console › **Perfil de pagos** (Configuración › Perfil de
   pagos) con cuenta bancaria e información fiscal. Merchant account activa.
3. Decidir el precio de cada ampliación en cada tienda (normalmente el punto
   de precio equivalente a la tarifa web vigente). Apple y Google retienen su
   comisión (15 % en el Small Business Program / programa de 15 % de Google,
   30 % si no aplica) — confírmalo en cada consola.

### B. Productos

Crear un producto **por ampliación** (el mismo ID en el admin):

| Tienda | Dónde | Tipo | ID sugerido (ejemplo) |
|---|---|---|---|
| App Store Connect | Mi app › Monetización › **Compras dentro de la app** › + | **Consumible** | `com.finisherlegacy.app.fotos10` |
| Play Console | Monetizar › Productos › **Productos únicos** › Crear | Producto único (la app lo consume) | `fotos_10` (minúsculas, números, `_` y `.`) |

En cada uno: nombre y descripción en español (p. ej. «10 fotos más para tu
recuerdo»), precio, disponibilidad por país; en Apple además la captura de
revisión de la hoja de compra y enviarlo **junto con la versión de la app**
(el primer producto debe ir en un envío de la app). Después, en
**Admin › Límites y tarifas** abre la ampliación y pega los dos IDs.

### C. Credenciales del servidor (`.env`)

| Variable | De dónde sale |
|---|---|
| `APPLE_IAP_KEY_ID`, `APPLE_IAP_ISSUER_ID`, `APPLE_IAP_PRIVATE_KEY` | App Store Connect › Usuarios y acceso › Integraciones › **Compras dentro de la app** › generar llave (.p8, se descarga una sola vez). `APPLE_IAP_PRIVATE_KEY` = contenido del .p8 (con `\n`) o ruta absoluta al archivo. El Issuer ID está en esa misma página. |
| `APPLE_BUNDLE_ID` | `com.finisherlegacy.app` (por defecto). |
| `APPLE_APP_APPLE_ID` (opcional) | Apple ID numérico de la app; si se define, las notificaciones de producción de otra app se ignoran. |
| `APPLE_IAP_ALLOW_SANDBOX` | `true` (por defecto): la revisión de Apple y TestFlight compran en sandbox. |
| `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` | Ruta absoluta al JSON de una cuenta de servicio de Google Cloud (fuera del repo, permisos 600). En Play Console › Usuarios y permisos › invitar el correo de la cuenta de servicio con **Ver información financiera** y **Gestionar pedidos y suscripciones** para esta app. Habilitar la *Google Play Android Developer API* en ese proyecto de Cloud. |
| `GOOGLE_PLAY_PACKAGE_NAME` | `com.finisherlegacy.app` (por defecto). |
| `GOOGLE_PLAY_RTDN_AUDIENCE`, `GOOGLE_PLAY_RTDN_SERVICE_ACCOUNT` | Ver D (OIDC, recomendado). |
| `GOOGLE_PLAY_RTDN_TOKEN` | Alternativa sin OIDC: secreto largo aleatorio (`openssl rand -hex 32`). |

Después: `php artisan config:cache`.

### D. Notificaciones de reembolso

- **Apple:** App Store Connect › Mi app › Información de la app › **App Store
  Server Notifications** → *Versión 2* → URL de producción y de sandbox:
  `https://finisherlegacy.com/api/webhooks/app-store`. Botón «Enviar
  notificación de prueba» → debe responder 200.
- **Google:** Google Cloud › Pub/Sub › crear tema (p. ej. `play-rtdn`), dar
  rol **Pub/Sub Publisher** a `google-play-developer-notifications@system.gserviceaccount.com`;
  crear una suscripción **push** a `https://finisherlegacy.com/api/webhooks/google-play`
  con **autenticación habilitada** (cuenta de servicio de push; audiencia =
  esa misma URL → `GOOGLE_PLAY_RTDN_AUDIENCE`, correo →
  `GOOGLE_PLAY_RTDN_SERVICE_ACCOUNT`). Sin OIDC: URL
  `…/api/webhooks/google-play?token={GOOGLE_PLAY_RTDN_TOKEN}`. En Play Console
  › Monetizar › Configuración de monetización › **Notificaciones en tiempo
  real** → pegar el nombre completo del tema → «Enviar notificación de prueba».

### E. Pruebas

- **iOS:** App Store Connect › Usuarios y acceso › **Sandbox** › testers
  (correos que no sean Apple ID reales). En el iPhone: Ajustes › App Store ›
  Cuenta de sandbox. Probar con una build de TestFlight o de desarrollo
  (no Expo Go). En sandbox también se prueban reembolsos desde
  *Ajustes › Desarrollador* o `Transaction Manager` de Xcode.
- **Android:** Play Console › Configuración › **Pruebas de licencias** →
  agregar las cuentas de Gmail; la app debe estar en una pista de prueba
  (interna) e instalada desde Play. Las tarjetas de prueba permiten «Aprobar»,
  «Rechazar» y «Pago pendiente» (prueba `IAP_PURCHASE_PENDING`). Los pedidos
  de prueba se reembolsan en Play Console › Gestión de pedidos (prueba RTDN).
- Casos a recorrer: compra, cancelar, pendiente → aprobada, sin red al
  confirmar (activar modo avión justo después de pagar → al reabrir se
  acredita), «Restaurar compras», reembolso → baja el límite sin borrar fotos.

### G. Videos, mensajes de voz, planes por periodo y banderas (2026-11-12)

- **Tipo de contenido**: cada ampliación tiene `kind` (`photos` / `videos` / `audio`). `GET /api/v1/iap/offer` acepta `kind` (por defecto `photos`) y la compra verificada otorga el tipo de su ampliación (`extra_images` / `extra_videos` / `extra_audio`).
- **Tipo de producto en las tiendas según el cobro de la ampliación**:

  | `billing_period` | App Store Connect | Play Console |
  |---|---|---|
  | `one_time` (pago único) | **Consumable** | Producto único (consumible) |
  | `semiannual` / `annual` (plan prepagado) | **Non-Renewing Subscription** | Producto único (consumible) |

  La verificación es la misma en ambos casos (una transacción de una sola vez). **Las fechas las pone el servidor**: al confirmar la compra el derecho recibe `starts_at` / `expires_at` (6 o 12 meses); renovar es volver a comprar (si al plan le quedan ≤ 30 días, se extiende desde su vencimiento). En iOS una suscripción sin renovación automática no caduca sola en la tienda: la vigencia es responsabilidad nuestra, y por eso el mismo producto se puede volver a comprar.
- **Banderas de precio**: como la tienda es dueña del precio, cada bandera puede tener **su propio producto** (`apple_product_id` / `google_product_id` en la bandera, panel "Avanzado" del precio en Límites y tarifas). `offer` devuelve el `product_id` de la bandera que le toca al recuerdo (o el de la ampliación si la bandera no tiene uno) y `pack.step`. Un mismo producto puede repetirse en el siguiente precio de la misma ampliación, nunca en otra.
- Si llega una transacción verificada de **otra bandera** de la misma ampliación (el recuerdo cambió entre la oferta y el pago, o un cliente modificado), el espacio se otorga —la tienda ya cobró— y el pedido queda marcado `step_mismatch` con una entrada en el registro de actividad para revisarlo.
- **Suscripciones auto-renovables** (opción futura, no implementada): productos *Auto-Renewable Subscription* en App Store / suscripciones en Play, grupo de suscripción por tipo, verificación de renovaciones con App Store Server Notifications V2 (`DID_RENEW`, `EXPIRED`, `DID_FAIL_TO_RENEW`, `GRACE_PERIOD_EXPIRED`, `REFUND`) y RTDN de suscripciones en Google, una tabla de suscripciones que mantenga vigente el derecho mientras la suscripción esté activa, y el enlace a "Administrar suscripción" de cada tienda.

### F. Build nativa

`expo-iap` es código nativo: hace falta `eas build` nuevo para iOS y Android
(el dev client actual no lo trae; ahí la app oculta la compra sin fallar).
