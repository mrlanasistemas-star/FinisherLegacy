<!--
  Pagos de Finisher Legacy: estado real de OpenPay (web y app), qué falta y
  la lista exacta de pasos para habilitarlo. Auditoría y cambios del
  2026-11-10. Si cambias el flujo de pagos, actualiza este archivo.
-->

# Pagos (OpenPay) — estado y cómo habilitarlo

**Respuesta corta a "¿ya están listos los pagos OpenPay en la app?":** el
código ya está completo en la web, en la API y en la app, y tiene pruebas.
**Todavía no se puede cobrar** porque falta lo que no es código: una cuenta
OpenPay con KYC aprobado, sus llaves, el webhook dado de alta en el panel de
OpenPay, una compra de prueba en sandbox y una **nueva build nativa** de la
app (se quitó Stripe). Los pasos exactos están abajo, en
[Para habilitar OpenPay](#para-habilitar-openpay).

---

## 1. Cómo funciona (una sola pasarela: OpenPay)

```
Web:  Carrito → /checkout (datos + entrega) → Order (precios del servidor, stock apartado)
      → /checkout/{order} (formulario Openpay.js) → cargo → [3-D Secure] → /checkout/{order}/confirmacion

App:  Carrito → POST /api/v1/checkout → Order
      → POST /api/v1/orders/{uuid}/checkout-session → {url, expires_at}   (URL de un solo uso, 5 min)
      → navegador dentro de la app (expo-web-browser openAuthSessionAsync)
          /pago/app/{token} → /pago/app/pedido/{order} (el MISMO formulario Openpay.js, sin menú del sitio)
          → cargo → [3-D Secure] → /pago/app/pedido/{order}/confirmacion
          → finisherlegacy://checkout/return?order={uuid}   (cierra el navegador; no trae resultado)
      → la app consulta GET /api/v1/orders/{uuid} hasta tener el estado real
```

Una sola pasarela para todo (web y app). Stripe queda **solo en el backend**,
para conciliar pagos antiguos (ver §5).

### Por qué un navegador dentro de la app (y no un SDK nativo)

OpenPay México publica SDKs móviles nativos (iOS/Android) para tokenizar
tarjetas, pero **no tiene SDK oficial para React Native/Expo**. Usarlos
requeriría escribir y mantener un módulo nativo propio para tokenización y
antifraude (device session), y aun así 3-D Secure termina en una página web
del banco. Abrir el checkout web de OpenPay que ya funciona dentro de un
navegador seguro de la app es la ruta más robusta y con menor alcance PCI:

- La tarjeta la escribe el usuario en el formulario de **Openpay.js** y la
  tokeniza OpenPay en ese navegador. Ni la app ni Laravel ven nunca el número
  de tarjeta: Laravel solo recibe `token_id` + `device_session_id`
  (antifraude, `openpay-data.js`).
- 3-D Secure ocurre en el mismo navegador, sin código extra.
- Es el mismo código y las mismas pruebas que la web.

### Seguridad de la sesión del navegador

- `POST /api/v1/orders/{uuid}/checkout-session` (Sanctum, solo el dueño del
  pedido) crea un token aleatorio de 64 caracteres. En la base solo se guarda
  su SHA-256 (`checkout_handoff_tokens`). Vence en 5 minutos
  (`FINISHER_APP_CHECKOUT_TOKEN_TTL`), es de **un solo uso** (se marca con un
  `UPDATE` condicional atómico) y pedir uno nuevo revoca el anterior sin usar.
- Al abrirlo, `/pago/app/{token}` regenera la sesión y guarda un "alcance"
  limitado a **ese pedido** durante 30 minutos
  (`FINISHER_APP_CHECKOUT_SESSION_MINUTES`, suficiente para 3-D Secure). **No
  es un login web**: `AuthorizeAppCheckout` pone al usuario solo para esa
  petición y solo en las rutas `/pago/app/pedido/{ese pedido}/…`. Otro pedido,
  `/checkout/{pedido}`, `/mis-pedidos` o cualquier otra página siguen cerrados.
- En iOS se abre con `preferEphemeralSession: true` (no comparte cookies con
  Safari).

### Nunca "pagado" desde el cliente

- Ni el redirect de 3-D Secure (`?id={charge_id}`) ni el deep link de
  regreso marcan nada como pagado. El estado lo decide el servidor
  consultando el cargo a la API de OpenPay (`SyncOnlinePayment` →
  `ProcessPaymentWebhook`, el mismo camino del webhook) en la página de
  confirmación, en `/estado` y en `GET /api/v1/orders/{uuid}` (mientras el
  último intento OpenPay esté pendiente, como máximo una vez cada 4 s por
  pedido).
- La app solo muestra "Pago confirmado" cuando `payment_state = paid`.

### Solo productos físicos en la app

Las políticas de App Store (Guideline 3.1.1) y Google Play (Payments) exigen
compra integrada (StoreKit / Play Billing) para bienes digitales. Por eso:

- La API de catálogo (`/api/v1/store/products`) **excluye** `digital_photo`
  (fotos de evento y ampliaciones de espacio), y la ficha responde 404.
- `POST /api/v1/cart/items` rechaza un producto digital (`PRODUCT_UNAVAILABLE`).
- `POST /checkout-session` rechaza un pedido con bienes digitales
  (`409 ORDER_NOT_APP_PAYABLE`), y el cargo dentro del navegador de la app
  también.
- `OrderResource.app_payable` es `false` para esos pedidos; la app oculta el
  botón "Pagar" sin enlazar a la web (sin "anti-steering").
- La app nunca ofreció comprar ampliaciones: `QuotaController` es solo
  lectura. La web sigue vendiendo bienes digitales con OpenPay.
- Regla única: `App\Support\Commerce\AppPurchasePolicy`.

---

## 2. Auditoría — qué ya funcionaba (web)

| Pieza | Estado | Dónde |
|---|---|---|
| Tokenización Openpay.js + `device_session_id` (openpay-data.js) | ✅ Funciona. Carga desde `js.openpay.mx`, `setSandboxMode` según la configuración. | `resources/js/components/commerce/OpenPayCardForm.vue` |
| Creación del cargo con el SDK oficial `openpay/sdk` | ✅ Monto y moneda siempre del `Order`, nunca del cliente; `order_id` único por intento; datos del cliente para antifraude. | `App\Services\Commerce\Payments\OpenPayPaymentGateway` |
| 3-D Secure | ✅ `OPENPAY_USE_3D_SECURE=true` lo fuerza siempre. Si no, un rechazo antifraude (3005) se reintenta una vez con 3DS (igual que el plugin oficial de OpenPay). | mismo |
| Regreso de 3DS | ✅ `redirect_url` → confirmación; el `?id=` se ignora y se re-lee el cargo. | `CheckoutController::confirmation` |
| Idempotencia | ✅ `payment_webhook_receipts` único por `(provider, event_id)`; reserva del `Payment` bajo lock antes de llamar a OpenPay; reanuda un cargo 3DS pendiente en lugar de cobrar dos veces. | `CreateOnlinePayment`, `ProcessPaymentWebhook` |
| Estados | ✅ `Order.status` / `payment_status` / `fulfillment_status` separados; `payment_state` derivado (pending/processing/paid/failed/cancelled/refunded); matriz de transiciones. | `OrderPaymentState`, `ProcessPaymentWebhook` |
| Reembolsos | ⚠️ **Solo reflejo**: un reembolso hecho en el panel de OpenPay llega por webhook (`charge.refunded`), se re-lee el cargo y pasa a `refunded`; si había piezas sin entregar, el pedido se cancela y libera stock. **No hay botón de reembolso en el admin** y un reembolso **parcial** no se ha verificado contra OpenPay real. | `ProcessPaymentWebhook::applyRefund` |
| Correo de confirmación de pago | ❌ **No existe.** Al pagarse no se envía correo; solo hay actualización en tiempo real/polling y la pantalla de confirmación. | — |
| Sandbox ↔ producción | ✅ Casilla "Modo producción" en el admin (o `OPENPAY_PRODUCTION_MODE`). Cambia la URL de la API del SDK y `setSandboxMode` del navegador. Las llaves de sandbox y producción son distintas. | `PaymentSettings`, `OpenPayClientFactory` |
| Expiración de pedidos sin pagar | ✅ `finisher:expire-pending-orders` cada 5 min (libera stock y cupón). Requiere el scheduler (`schedule:run`) en el servidor. | `routes/console.php` |

### Webhook — cómo se autentica

OpenPay **no firma** sus notificaciones. Lo único que ofrece es
**autenticación HTTP Basic opcional** (usuario/contraseña que capturas al
crear el webhook en su panel). Además exige un dominio (no IP), HTTPS/TLS 1.2
con certificado público válido y puerto 443, 8443 o 10443.

Antes de este cambio el código **no** verificaba Basic auth (dependía solo de
re-leer el cargo). Ahora:

1. Si hay usuario y contraseña configurados (admin o `.env`), toda
   notificación sin ellos recibe **401** antes de leer nada (incluido el
   código de verificación). La comparación es en tiempo constante y nunca se
   registra lo enviado.
2. El cuerpo **nunca** se usa para monto/estado: se consulta el cargo por id
   a la API de OpenPay con la llave privada.
3. Eventos que no son `charge.*` (payouts, transferencias, contracargos…) se
   responden 200 sin efectos, para que OpenPay no los reintente sin fin. Los
   contracargos se atienden desde el panel de OpenPay.
4. La llave de idempotencia ahora incluye el estado re-leído
   (`{cargo}:{evento}:{estado}`): si un primer aviso llegó cuando el cargo
   aún estaba pendiente, el reintento con el cargo ya `completed` sí se aplica.

### Admin — Configuración → Pagos (`payments.configure`)

Ya existía: pasarela activa, Merchant ID, llave pública, llave privada
(cifrada, solo se muestra `••••1234`), casilla de producción, casilla de 3DS,
URL del webhook con "Copiar", código de verificación del webhook (1 h) y
"Probar conexión" (lee los datos del comercio en OpenPay con las llaves y el
modo guardados; no genera cargos).

Agregado:

- Lista **"Para habilitar OpenPay"** con estado de cada paso (llaves, prueba
  de conexión, credenciales del webhook, webhook recibido, pasarela activa).
- Usuario y contraseña del webhook (contraseña cifrada, nunca se reenvía al
  navegador; vacía = conservar; casilla para quitarlas).
- "Última notificación de OpenPay" (fecha), para saber que el webhook llega.
- El texto ya no dice que la app cobra con Stripe.

---

## 3. Auditoría — app móvil (antes de este cambio)

- Cobraba con **Stripe PaymentSheet** (`@stripe/stripe-react-native`), vía
  `POST /orders/{uuid}/payments/online {provider: stripe}` +
  `POST /payments/sync`. Sin llaves de Stripe en el servidor respondía 501
  ("pago no disponible"): **nunca se pudo cobrar en la app**.
- Eso implicaba dos pasarelas en paralelo (OpenPay web + Stripe app).

**Ahora:** la app ya no usa Stripe. `@stripe/stripe-react-native` se
desinstaló y su plugin se filtra en `app.config.ts` (su entrada en `app.json`
debe quitarse — es archivo de store-release). Requiere **nueva build nativa**.

---

## 4. Cambios de este trabajo (archivos)

Backend / web (`finisherLegacy`):

- `database/migrations/2026_11_10_700000_create_checkout_handoff_tokens_and_openpay_webhook_auth.php`
- `app/Models/CheckoutHandoffToken.php`, `app/Models/PaymentSetting.php`
- `app/Actions/Commerce/CreateAppCheckoutSession.php`, `ConsumeCheckoutHandoffToken.php`
- `app/Http/Controllers/Store/AppCheckoutController.php`, `CheckoutController.php` (helpers compartidos)
- `app/Http/Middleware/AuthorizeAppCheckout.php`
- `app/Http/Controllers/Api/V1/Store/PaymentController.php` (`checkoutSession`), `OrderController.php` (sync al consultar), `ProductController.php`, `CartController.php` (sin bienes digitales)
- `app/Http/Resources/Api/V1/OrderResource.php` (`app_payable`)
- `app/Support/Commerce/AppPurchasePolicy.php`, `WebCheckoutPayment.php` (`openpayOnly()`)
- `app/Http/Controllers/Api/Webhooks/OpenPayWebhookController.php` (Basic auth), `app/Services/Commerce/Payments/OpenPayPaymentGateway.php`, `PaymentSettings.php`, `app/Http/Requests/Admin/UpdatePaymentSettingsRequest.php`
- `app/Enums/ApiErrorCode.php` (`ORDER_NOT_APP_PAYABLE`), `app/Exceptions/OrderNotAppPayableException.php`
- `config/finisher.php`, `routes/web/commerce.php`, `routes/api.php`
- `resources/js/pages/store/AppCheckout.vue`, `AppCheckoutConfirmation.vue`, `AppCheckoutInvalid.vue`, `Checkout.vue`, `CheckoutConfirmation.vue`, `components/commerce/OpenPayCardForm.vue`, `pages/admin/settings/Payments.vue`, `lib/commerce.ts`
- `tests/Feature/Commerce/AppCheckoutSessionTest.php`

App (`FinisherLegacyMobile`):

- `src/api/payments.ts` (`createCheckoutSession`), `src/payments/openpay-checkout.ts`, `src/payments/checkout-return.ts`, `src/payments/payment-flow.ts` (+ test), `src/hooks/use-payment-flow.ts`
- `src/app/checkout/index.tsx`, `src/app/orders/[uuid].tsx`, `src/app/+native-intent.tsx`, `src/features/links/map-incoming-path.ts` (+ test), `src/types/models.ts`
- eliminado `src/payments/stripe.ts`; `package.json`/`package-lock.json` sin Stripe; `app.config.ts` filtra el plugin de Stripe

### La página de pago dentro de la app

- Sin menú ni pie del sitio; encabezado compacto con "Cancelar" (vuelve a la
  app) y "Seguro"; respeta las áreas seguras (`env(safe-area-inset-*)`).
- Mismo formulario Openpay.js (teclado numérico, autocompletado `cc-*`,
  validación de tarjeta/fecha/CVV de Openpay.js, mensajes de rechazo en
  español).
- 3-D Secure: el banco abre en el mismo navegador y regresa a
  `/pago/app/pedido/{uuid}/confirmacion`.
- Confirmado: "Volver a la app" y regreso automático a los 1.8 s. Error:
  "Intentar de nuevo" (mismo pedido). Enlace usado/vencido: pantalla
  "Volver a la app".

### Estados en la app

| Servidor (`payment_state`) / navegador | Pantalla |
|---|---|
| `paid` | Pago confirmado |
| `processing` | Pago en proceso (sigue consultando hasta ~4 min) |
| `pending` + regresó del navegador | Confirmando… (≈19 s de consultas) → "Aún no vemos tu pago": Revisar de nuevo / Intentar de nuevo |
| `pending` + cerró el navegador | Pago sin terminar (reintentar, no cobra dos veces) |
| `failed` | El pago no se completó (reintentar) |
| `cancelled` / vencido | Ya no se puede pagar → carrito |
| 501 al pedir la URL | Pago en línea no disponible |
| `ORDER_NOT_APP_PAYABLE` | Este pedido no se paga en la app |

---

## 5. Stripe

- **App:** retirado (UI, paquete y plugin).
- **Backend:** se **conserva** `StripePaymentGateway`, `POST /api/webhooks/stripe`,
  `POST /orders/{uuid}/payments/online|sync` y las pruebas. Motivo: conciliar
  cualquier pago Stripe histórico, y no romper builds viejas de la app (que
  seguirán viendo 501 "no disponible" mientras no haya llaves de Stripe). No
  se borró ningún dato de pagos.
- La web solo muestra Stripe si eliges "Stripe" como pasarela activa y existen
  `STRIPE_KEY`, `STRIPE_SECRET` y `STRIPE_WEBHOOK_SECRET`. Con OpenPay activo
  no hace falta configurar Stripe.

---

## Para habilitar OpenPay

### A. Cuenta OpenPay (fuera del código)

1. Crea la cuenta de comercio en https://www.openpay.mx (OpenPay México, de
   BBVA). Al registrarte obtienes **una cuenta sandbox** con sus llaves de
   prueba (panel: https://sandbox-dashboard.openpay.mx).
2. Completa el **KYC / alta de comercio** que OpenPay solicita (documentos
   del negocio, cuenta bancaria para depósitos, giro). La cuenta de
   **producción es distinta** y OpenPay la habilita solo cuando aprueba la
   documentación; sus llaves (`https://dashboard.openpay.mx`) son otras.
   Los tiempos y requisitos exactos los define OpenPay — confírmalos con tu
   ejecutivo.
3. Pregunta a OpenPay si tu afiliación requiere **3-D Secure** obligatorio y
   qué tarjetas/marcas tienes habilitadas (Visa, Mastercard, AMEX, Carnet).

### B. Llaves → dónde van

En el panel de OpenPay: **Configuración → Llaves de API** (o equivalente).

| Dato | Dónde capturarlo (recomendado) | Alternativa `.env` |
|---|---|---|
| Merchant ID (`m…`) | Admin → Configuración → Pagos → Merchant ID | `OPENPAY_MERCHANT_ID` |
| Llave pública (`pk_…`) | Admin → Llave pública | `OPENPAY_PUBLIC_KEY` |
| Llave privada (`sk_…`) — **secreta** | Admin → Llave privada (se guarda cifrada con `APP_KEY`) | `OPENPAY_PRIVATE_KEY` |
| País | fijo `MX` | `OPENPAY_COUNTRY=MX` |
| Sandbox / producción | Admin → casilla "Modo producción" (apagada = sandbox) | `OPENPAY_PRODUCTION_MODE=false` |
| 3-D Secure siempre | Admin → "Usar 3D Secure en todos los cobros" | `OPENPAY_USE_3D_SECURE=true` |
| Pasarela de la web | Admin → Pasarela activa: OpenPay | `FINISHER_PAYMENT_GATEWAY=openpay` (ya es el valor por defecto) |
| Usuario del webhook | Admin → Webhook → Usuario | `OPENPAY_WEBHOOK_USER` |
| Contraseña del webhook (≥ 12 caracteres) | Admin → Webhook → Contraseña (cifrada) | `OPENPAY_WEBHOOK_PASSWORD` |

Lo guardado en el admin tiene prioridad sobre `.env`. **Nunca** pongas la
llave privada en el código, en la app ni en Git. La app no necesita ninguna
llave: todo pasa por el servidor.

Opcionales de la app (ya tienen valores por defecto correctos):
`FINISHER_APP_CHECKOUT_TOKEN_TTL=5`, `FINISHER_APP_CHECKOUT_SESSION_MINUTES=30`,
`FINISHER_APP_CHECKOUT_RETURN_URL=finisherlegacy://checkout/return`
(debe coincidir con el `scheme` de `app.json`).

### C. Servidor

```bash
php artisan migrate                # crea checkout_handoff_tokens y columnas del webhook
php artisan config:cache           # si usas caché de configuración (después de editar .env)
php artisan route:cache            # si usas caché de rutas
npm run build                      # compila las páginas nuevas (AppCheckout*.vue)
```

- `APP_URL` debe ser el dominio HTTPS real: la URL de la app, el
  `redirect_url` de 3-D Secure y el webhook se generan con él.
- El scheduler debe correr (`* * * * * php artisan schedule:run`) para que
  expiren los pedidos sin pagar.
- No hace falta abrir nada para Openpay.js: carga desde `js.openpay.mx`. Si
  tienes Content-Security-Policy, permite `js.openpay.mx`, la API de OpenPay
  y las páginas de 3-D Secure de los bancos.

### D. Webhook en el panel de OpenPay

1. Admin → Configuración → Pagos: define **usuario y contraseña del webhook**
   y guarda.
2. Panel de OpenPay → **Configuración → Webhooks → Agregar**:
   - URL: la que muestra el admin, `https://<tu-dominio>/api/webhooks/openpay`
     (dominio, HTTPS/TLS 1.2, certificado válido, puerto 443).
   - Autenticación: **básica**, con el mismo usuario y contraseña.
   - Eventos: al menos `charge.succeeded`, `charge.failed`,
     `charge.cancelled`, `charge.refunded` (los demás se aceptan y se ignoran).
3. Al guardar, OpenPay envía un **código de verificación**. Recarga
   Configuración → Pagos: aparece en "Código de verificación recibido" (dura
   1 h). Cópialo en el panel de OpenPay → **Verificar**.
4. La lista "Para habilitar OpenPay" marca "Webhook… verificado" cuando llega
   la primera notificación autenticada.

Haz esto **una vez en sandbox y otra en producción** (son cuentas distintas).
Aunque el webhook falle, cada pago se confirma igual al consultar el cargo
(confirmación, `/estado` y `GET /api/v1/orders/{uuid}`); el webhook cubre a
quien cierra todo antes de que el banco responda y los reembolsos.

### E. Prueba en sandbox

1. Admin: llaves de **sandbox**, "Modo producción" apagado → Guardar →
   **Probar conexión** (debe decir "Conexión correcta con OpenPay").
2. Tarjetas de prueba oficiales de OpenPay México (fuente:
   https://documents.openpay.mx/docs/testing.html — la página se carga con
   JavaScript; ábrela en el navegador y **confírmalas ahí**, OpenPay puede
   cambiarlas):

   | Tarjeta | Resultado esperado |
   |---|---|
   | `4111 1111 1111 1111` (Visa) | aprobada |
   | `5555 5555 5555 4444` (Mastercard) | aprobada |
   | `3456 7800 0000 007` (AMEX) | aprobada |
   | `4222 2222 2222 2220` | rechazada (3001) |
   | `4444 4444 4444 4448` | fondos insuficientes (3003) |
   | `4000 0000 0000 0044` | rechazada por antifraude (3005) |

   Vencimiento: cualquier mes futuro. CVV: cualquier valor de 3 dígitos (4 en
   AMEX). La documentación indica que alguna tarjeta de prueba solo se
   aprueba con 3-D Secure; si una "aprobada" se rechaza, activa "Usar 3D
   Secure en todos los cobros" y repite.
3. **Web:** agrega un producto físico, `/checkout`, paga con una tarjeta
   aprobada → confirmación "¡Listo! Tu pago está confirmado". Con 3DS,
   completa la página del banco de prueba. Repite con una rechazada → mensaje
   en español y el pedido sigue pagable.
4. **App** (con la build nueva, apuntando a ese backend): Tienda → producto
   → Carrito → Checkout → **Pagar** → se abre la ventana segura con el
   formulario de OpenPay → tarjeta aprobada → se cierra sola → "Pago
   confirmado". Prueba también: cerrar la ventana sin pagar ("Pago sin
   terminar" → Intentar de nuevo), tarjeta rechazada (mensaje y reintento),
   y 3-D Secure.
5. Verifica en el admin (Comercio → Pedidos/Pagos) que el pedido quedó
   pagado, y en el panel de OpenPay el cargo con el mismo monto. "Última
   notificación de OpenPay" debe mostrar fecha.
6. Reembolso de prueba desde el panel de OpenPay → el pedido pasa a
   "reembolsado" (requiere el webhook).

### F. Pasar a producción

1. Cuando OpenPay apruebe la cuenta: captura Merchant ID y llaves de
   **producción**, enciende "Modo producción", guarda, **Probar conexión**.
2. Da de alta el webhook en el panel de **producción** (paso D).
3. Haz una compra real de monto bajo y reembólsala desde el panel.

### G. App móvil (equipo móvil / store-release)

1. Quitar la entrada `["@stripe/stripe-react-native", {…}]` de `app.json`
   (hoy se filtra en `app.config.ts`).
2. **Nueva build nativa** (EAS / dev client): se desinstaló un módulo nativo.
   Las builds anteriores siguen intentando Stripe y verán "pago no
   disponible".
3. Nada que configurar en la app: no lleva llaves; el `scheme`
   `finisherlegacy` ya existe.

---

## Pendientes conocidos (no inventados)

- Correo de confirmación de pago: no existe.
- Reembolsos desde el admin: no existen (se hacen en el panel de OpenPay y se
  reflejan por webhook). Reembolso parcial sin verificar contra OpenPay real.
- Prueba E2E real en sandbox: pendiente de credenciales (todo lo demás está
  probado con un doble del SDK, sin red).
- Pagos en efectivo/SPEI de OpenPay (tiendas, transferencias): no
  implementados; solo tarjeta.

## Pruebas

- `tests/Feature/Commerce/AppCheckoutSessionTest.php` — token de un solo uso,
  vencimiento, dueño del pedido, alcance a un solo pedido, cuenta suspendida,
  bienes digitales, 3DS que no paga desde el redirect, API de estado,
  webhook con Basic auth, idempotencia, eventos no `charge.*`, catálogo sin
  digitales, admin (credenciales cifradas, checklist).
- App: `src/payments/payment-flow.test.ts`, `src/features/links/map-incoming-path.test.ts`.

Fuentes OpenPay: https://documents.openpay.mx/docs/testing.html,
https://documents.openpay.mx/docs/webhooks.html,
https://documents.openpay.mx/docs/api.html (webhook `user`/`password` de
autenticación básica, verificación requerida),
https://documents.openpay.mx/docs/openpay-js.html.
