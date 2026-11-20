# Funcionalidades — inventario vigente

Inventario de lo que **existe en el código** al 2026-11-20 (rutas en
`routes/`, app en `FinisherLegacyMobile/src/app`). Cada módulo enlaza su
documento de detalle. Lo que depende de cuentas o llaves externas se indica y
se detalla en [PENDIENTES_EXTERNOS.md](PENDIENTES_EXTERNOS.md).

Producto: **Finisher Legacy Tag NFC** (sin QR, sin placas metálicas) más el
**portarretrato inteligente** (marco con NFC). Identidad visual solo clara:
Blanco Hueso, Negro Carbono y Oro.

---

## 1. Sitio público (web)

| Función | URL | Notas |
|---|---|---|
| Inicio, cómo funciona, nosotros, contacto, ayuda | `/`, `/como-funciona`, `/nosotros`, `/contacto`, `/ayuda` | Contenido editable en Admin › Contenido del sitio. |
| Páginas de producto | `/recuerdos`, `/entrenamientos`, `/portarretrato`, `/app` | Explican el recuerdo, el modo entrenamiento, el marco y la descarga de la app. |
| Tienda | `/tienda`, `/tienda/catalogo`, `/tienda/categoria/{slug}`, `/tienda/{producto}` | Productos físicos (tags, accesorios, portarretrato). Modelo 3D del tag. |
| Colecciones | `/colecciones`, `/colecciones/{slug}` | Diseños por colección del tag. |
| Carrito y pago | `/carrito`, `/checkout`, `/checkout/{pedido}` (Openpay.js + 3-D Secure), `/checkout/{pedido}/confirmacion` | Recoger o envío a domicilio con costo configurable. `docs/renovacion/pagos.md`. |
| Abrir un tag | `/t/{código}` | Tag nuevo → invita a activarlo; activado → recuerdo del dueño (o lo que el dueño permita). Abre la app si está instalada (enlace universal). |
| Activación | `/activar`, `/activar/nfc` (Web NFC en Chrome Android), `/activar/{código}` | Código de activación de la tarjeta del empaque. `docs/renovacion/manual-activacion-nfc.md`. |
| Recuerdo público / compartido | `/legacy/{historia}`, `/legacy/para/{token}` | Respeta visibilidad y NIP. |
| Perfil público del atleta | `/@{usuario}`, `/@{usuario}/{lista}` | Seguidores / seguidos. |
| Comunidad | `/comunidad`, `/comunidad/personas`, `/comunidad/conexiones`, `/comunidad/publicaciones/{momento}` | Legacy Moments, reacciones, comentarios, seguir, reportar. |
| Eventos y resultados | `/eventos`, `/events/{slug}`, prerregistro | Datos importados o de proveedores. |
| Fotos de eventos | `/fotos`, `/fotos/descargar/{venta}` | Compra de fotos de fotógrafos (web). |
| Fotógrafos | `/fotografos`, `/fotografos/registro`, portal `/fotografo` (fotos, ventas, simulador de ganancias) | `docs/renovacion/comisiones-fotografos.md`. |
| «Envíame ánimo» | `/animo/{token}` | Familia y amigos graban un mensaje de voz sin cuenta. `docs/renovacion/entrenamientos.md`. |
| Seguimiento en vivo | `/live/{token}` | Mapa en tiempo real de una carrera o entrenamiento compartido. |
| Invitación familiar | `/familia/invitacion/{token}` | Aceptar ser familia de un atleta. `docs/renovacion/familia.md`. |
| Acceso | `/entrar`, `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` | Fortify (correo + contraseña). |
| Legales y cuenta | `/privacy` (`/privacidad`), `/terms` (`/terminos`), `/eliminar-cuenta` | Requeridos por las tiendas. |
| Idiomas | prefijo `/en/...`, selector de idioma | Español (base) e inglés. `docs/renovacion/idiomas.md`. |
| Compatibilidad | `/n/{token}`, `/l/{código}` | Chips NFC y Legacy Codes históricos siguen resolviendo. |

## 2. Área del atleta (web, con sesión)

| Función | URL |
|---|---|
| Mi Legacy: recuerdos de mis tags, editor (datos de carrera, fotos, videos, voz, certificado, historia), fotos compradas, descarga del archivo, impresión, diseñador del portarretrato | `/mi-legacy`, `/mi-legacy/{historia}/editar`, `…/fotos-compradas`, `…/descargar`, `…/imprimir`, `…/portarretrato` |
| Mi legado: historial, medallas, mis eventos, mi equipo | `/dashboard`, `/dashboard/medals`, `/dashboard/my-events`, `/dashboard/my-gear` |
| Mis pedidos | `/mis-pedidos`, `/mis-pedidos/{pedido}` |
| Mi entrenamiento: enlaces «Envíame ánimo», mensajes recibidos (escuchar, favorito, bloquear, reportar) | `/mi-entrenamiento` |
| Perfil, contraseña y seguridad, apariencia, preferencias de notificaciones, NIP, eliminar cuenta | `/dashboard/profile`, `/settings/*` |
| Notificaciones | campana + `/notifications` |
| Portal de aliados (venta rápida en punto de venta) | `/aliado`, `/aliado/venta-rapida` |

## 3. Panel de administración (`/admin`)

Navegación única en `resources/js/config/navigation.ts`; cada sección exige su
permiso (spatie/laravel-permission, Admin › Roles y permisos).

| Sección | Qué hace |
|---|---|
| Dashboard | Ventas, canales, inventario, activaciones, alertas (`/admin/alertas`). |
| Clientes y atletas | Usuarios, atletas, eventos y resultados, importaciones, duplicados de identidad. |
| Catálogo | Colecciones y diseños del tag, productos (variantes, imágenes, modelo 3D), categorías. |
| Inventario | Existencias, ubicaciones, lotes (al recibir un lote se generan las unidades NFC y el CSV de códigos de activación), transferencias, proveedores, unidades NFC (`/admin/tags`), activaciones, chips NFC de placas históricas, productos conectados. |
| Ventas | Pedidos (envío: paquetería y guía, marcar entregado, cancelar), **pagos y reembolsos** (reembolso total OpenPay, permiso `payments.refund`), venta rápida, cupones y ofertas, **Límites y tarifas** (fotos/videos/audio incluidos, ampliaciones, precios por periodo, IDs de producto de Apple y Google). |
| Contenido | Fotos de eventos por revisar, fotógrafos y comisiones, **comunidad y moderación** (reportes, advertir y suspender usuarios), entrenamientos (reportes de mensajes de ánimo, purgar audio), contenido del sitio, **campañas push**. |
| Aliados | Aliados comerciales y sus ventas. |
| Reportes | Ventas. |
| Soporte | Incidencias (reemplazo, devolución, defecto, propiedad), mensajes de contacto (incluye solicitudes de borrado de cuenta), placas históricas (solo consulta). |
| Configuración | Ajustes de empresa, **Pagos** (llaves OpenPay, webhook, probar conexión), organizadores, fuentes de datos, conexiones, **Idiomas** (traducciones de interfaz y contenido del catálogo). |
| Auditoría y seguridad | Bitácora (activitylog), roles y permisos. |
| Horizon | `/horizon`, solo `super_admin`. |

## 4. API `/api/v1` (app móvil)

Contrato: `docs/api/v1.md`. Errores con sobre `{error:{code,message,details},request_id}`.

| Dominio | Endpoints principales |
|---|---|
| Auth y cuenta | `auth/*` (registro, login, logout, olvidé/restablecer contraseña; `auth/social/{provider}` sigue en el backend pero la app ya no tiene esa interfaz), `me`, `me/profile`, `me/locale`, `DELETE me/account` |
| Tags | `tags/{código}`, `tags/{código}/activate`, `tags/activation/start` → `{intent}/confirm` / `fail` (escritura NFC verificada), `nfc/{token}` |
| Recuerdos | `me/stories`, `me/stories/{id}` (editar), `…/media` (subir, importar fotos compradas, ordenar, borrar), `…/shares`, `…/portrait`, `…/portrait-designs`, `stories/{id}`, `stories/{id}/quota`, `me/purchased-photos` |
| NIP | `me/pin` (crear, cambiar, borrar), `me/pin/unlock`, `lock`, `items`, `recovery-code`, `reset`; lo protegido responde `423 CONTENT_LOCKED` sin desbloqueo. `docs/renovacion/nip.md` |
| Comercio | `store/*`, `cart/*`, `checkout`, `orders`, `orders/{uuid}/checkout-session` (pago web de un solo uso), `orders/{uuid}/cancel` (`orders/{uuid}/payments/online` y `/sync` quedan solo para builds antiguas con Stripe) |
| Compras dentro de la app | `iap/offer`, `iap/purchases` (verificación con Apple/Google). `docs/renovacion/compras-dentro-de-app.md` |
| Entrenamientos | `training/workouts` (iniciar, latido en vivo, terminar, descartar, compartir como momento, estadísticas, activo), `training/sports`, `training/links`, `training/messages` |
| Familia | `family`, `family/links`, `family/invites/{token}`, `family/following` |
| Seguimiento | `me/events/{p}/tracking/sessions`, `tracking/sessions/{uuid}/points`, `…/stop`, `…/shares`, `…/sharing`, `live/{token}` |
| Comunidad | `feed`, `explore`, `moments`, `comments`, reacciones, `athletes/{usuario}` (seguir, bloquear), `me/blocks`, `reports`, `search` |
| Atleta | `medals`, `me/events`, `me/gear`, `gear`, `me/history`, `me/support-sessions` (mensajes de apoyo en carrera) |
| Notificaciones | `me/notifications`, preferencias, `me/push-devices`, `notifications/{id}/opened` |
| Plataforma | `health`, `i18n/*`, `broadcasting/auth` |

Webhooks (fuera de `/v1`, sin sesión): `POST /api/webhooks/openpay`,
`/api/webhooks/app-store`, `/api/webhooks/google-play` y
`/api/webhooks/stripe` (solo conciliación histórica).

## 5. App móvil (FinisherLegacyMobile)

Expo SDK 57 + Expo Router. Pestañas: **Inicio · Recuerdos · [Activar] ·
Comunidad · Colecciones**; perfil y ajustes desde el avatar.

| Función | Pantallas (`src/app`) |
|---|---|
| Bienvenida, registro, login, recuperar y restablecer contraseña (enlace del correo abre la app) | `(auth)/*` |
| Activar un tag por NFC (escribir y verificar la URL en el chip) | `activate/*`, `legacy/scan`, `nfc/*` |
| Recuerdos: ver, editar, fotos/videos/voz, compartir, portarretrato, fotos compradas, ampliar espacio con compra dentro de la app | `stories/*`, `(tabs)/legacy` |
| NIP opcional (crear, desbloquear, recuperar) | `settings/content-pin`, `settings/pin-unlock`, `settings/pin-recovery` |
| Modo entrenamiento: entrenar en vivo con GPS, registrar actividad pasada, historial, récords, sin conexión, mensajes de ánimo con audio en segundo plano, compartir | `training/*` |
| Familia y seguimiento compartido | `family/*`, `live/[token]` |
| Seguimiento en vivo de una carrera | `tracking/[participantId]` |
| Comunidad: feed, momentos, perfiles, conexiones, búsqueda | `(tabs)/explore`, `feed`, `moments/*`, `athlete/*`, `search` |
| Tienda, carrito, checkout (pago OpenPay en navegador dentro de la app), pedidos | `(tabs)/store`, `store/*`, `cart`, `checkout`, `orders/*` |
| Medallas, mis eventos, equipo | `medals/*`, `my-events/*`, `gear/*`, `events/*` |
| Notificaciones y push | `notifications`, `settings/notifications` |
| Soporte en carrera | `support/*` |
| Ajustes: cuenta (eliminar cuenta), seguridad, privacidad, idioma, acerca de | `settings/*` |

Detalle de la app: `FinisherLegacyMobile/docs/ARCHITECTURE.md`,
`COMMERCE_ARCHITECTURE.md`, `NFC_ACTIVATION.md`, `LIVE_TRACKING.md`,
`TRAINING_AUDIO.md`, `SOCIAL_ARCHITECTURE.md`.

## 5 bis. Tarjeta de ánimo NFC y precios de los mensajes de ánimo

Extiende «Envíame ánimo»: una tarjeta física con chip NFC (NTAG213, tamaño
tarjeta de crédito) que abre en el navegador la página para dejarle un mensaje
de voz al atleta, **sin cuenta y sin app**, y precios configurables para los
mensajes. Detalle: `docs/renovacion/tarjeta-animo.md`. Pruebas:
`tests/Feature/Training/CheerCardsAndPricingTest.php`.

| Pieza | Qué hace |
|---|---|
| Modelo | La tarjeta es un `LegacyTag` con `purpose = cheer` y `cheer_link_id` (+ `cheer_bound_at`). El chip guarda `/t/{code}` como cualquier Tag: reusa escritor NFC, intentos de activación, antiduplicado por UID, lotes, inventario y kardex. El `purpose` lo fija el producto al crear el lote (`CheerCards::purposeFor`); una tarjeta nunca crea un recuerdo (`ActivateTag` la rechaza) y solo se vincula con `BindCheerCard`. |
| Compra | Producto `tarjeta-animo-nfc`. En la app, «Comprar tarjeta para este enlace» en cada enlace (`POST /cart/items` con `cheer_link_uuid`); el enlace se congela en `order_items.metadata.cheer_link`. Desde la ficha del producto sale sin enlace y el atleta la vincula después. |
| Surtido | Al surtir (`FulfillOrderItem` → `AssignTagsToOrderItem`) cada pieza se vincula sola al enlace elegido (`via = fulfillment`) y queda `activated`. Si el enlace ya no existe, sale sin vincular y no bloquea el envío. |
| Activación en la app | Activar → «Tarjeta de ánimo» → elegir o crear enlace → código → escribir el chip (`POST /tags/activation/start` con `purpose: "cheer"` y `cheer_link_uuid`). |
| Uso | `/t/{code}` vinculada → redirige a `/animo/{token}` y marca la sesión «viene de la tarjeta X» (2 h, no falsificable por URL). Sin vincular o desactivada → página «Esta Tarjeta de ánimo no está activa». Cada toque permite dejar otro mensaje, según los límites. |
| Cuotas y precios (`CheerQuota`) | `cheer_pricing_settings`, un registro por canal (`link` y `nfc_card`): `free_messages` (por enlace o por tarjeta), `after_free` (`blocked` · `free` · `paid`), `hard_limit`, `price_minor` (sin precio, `paid` se comporta como `blocked`), `price_steps`, `packs` (créditos en `cheer_sender_credits` por cookie `cheer_sender`) y `allow_anonymous` («Enviar como anónimo»). Sin registro: 200 gratis por enlace, luego bloqueado, nombre obligatorio. La decisión se repite bajo bloqueo de la fila del enlace. |
| Pago de mensajes adicionales | Paga **quien envía**, en la web, con OpenPay: el mensaje queda `pending_payment` (invisible) con un pedido de invitado (producto interno `mensaje-de-animo`, monto congelado) → `/animo/{token}/pago/{order}` (Openpay.js, 3-D Secure) → solo el webhook o `SyncOnlinePayment` (`MarkOrderPaid` → `DeliverPaidCheerMessages`) lo vuelve visible y avisa al atleta. Idempotente. Sin llaves de OpenPay se avisa antes de grabar. |
| Limpieza | `php artisan training:purge-unpaid-cheers` (cada 10 min): pasada la ventana de pago entrega lo pagado, expira el pedido pendiente y borra audio y texto del resto. |
| API (`/api/v1`, Sanctum) | `GET training/cards` (mis tarjetas + `meta.offer`), `GET training/card-offer`, `PUT training/cards/{uuid}` `{link_uuid}` (vincular/cambiar), `DELETE training/cards/{uuid}/link` (desactivar); `training/links` trae `state` y `stats`; `training/messages` trae `anonymous` y `source` (`link` / `nfc_card`). |
| Admin | **Ventas → Límites y tarifas → Mensajes de ánimo** (`/admin/limites-y-tarifas/mensajes-de-animo`, permiso `quotas.manage`): ajustes por canal, conteos y aviso si OpenPay no está configurado (el personal nunca lee los mensajes). En pedidos, cada línea de tarjeta muestra el enlace y las piezas; en Tags, insignia «Tarjeta de ánimo» y a qué enlace abre. |
| Publicar | `php artisan catalog:publish-cheer-card` (borrador) o `--price=<MXN>` (publicado). Idempotente. Luego fotos en Admin → Productos y un lote en Inventario → Lotes. |
| App | `training/share` (enlaces, tarjetas, «Cambiar enlace», «Desactivar», comprar), `activate` (opción «Tarjeta de ánimo»), `cart` (muestra «Abre: {enlace}»), `training/messages` (mensajes «Tarjeta NFC» y «Anónimo»). |

Migración: `2026_11_20_700000_create_cheer_cards_and_pricing.php` (aditiva y
reversible).

## 6. Reglas transversales

- **Pagos:** OpenPay es la única pasarela para bienes físicos (web y app); el
  pedido solo se marca pagado con la confirmación del servidor (webhook o
  consulta del cargo). Bienes digitales dentro de la app → solo compras de
  Apple/Google. Stripe no existe en la app; el backend lo conserva para
  conciliar pagos antiguos.
- **Privacidad:** contenido privado por defecto; NIP opcional fuerza privado;
  la ubicación se comparte solo por decisión explícita y termina con la sesión.
- **Moderación:** reportar y bloquear en comunidad y en mensajes de ánimo;
  filtro de lenguaje (`config/moderation.php`); admin puede advertir y
  suspender (las cuentas suspendidas pierden acceso a la API de inmediato).
- **NFC:** una URL por pieza (`{LEGACY_TAG_PUBLIC_URL}/t/{código}`), hash del
  UID único por chip, código de activación con límite de intentos.
- **Idiomas:** el texto en español es la clave; inglés completo en web, app,
  correos y catálogo.

## 7. Retirado (no buscar)

Producción de placas metálicas (Plate Studio, estaciones, Device API, Event
Ops), escaneo QR, Stripe PaymentSheet en la app, inicio de sesión con
Google/Apple en la app, interfaz oscura. Detalle: `docs/renovacion/retiro-modulos.md`
y `docs/historico/`.
