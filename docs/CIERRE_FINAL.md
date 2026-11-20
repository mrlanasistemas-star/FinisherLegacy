# Cierre final — 2026-11-20

Estado de Finisher Legacy al cierre de la renovación: el producto es el
**Finisher Legacy Tag NFC** con activación digital. Ya no hay placas metálicas
ni QR. La app es iOS/Android (Expo) y la web, el panel y la API viven en este
repo. El diseño es claro premium.

Documentos vigentes: [README](../README.md) · [FUNCIONALIDADES](FUNCIONALIDADES.md) ·
[ARQUITECTURA](ARQUITECTURA.md) · [DESPLIEGUE](DESPLIEGUE.md) ·
[PUBLICACION_TIENDAS](PUBLICACION_TIENDAS.md) · [PENDIENTES_EXTERNOS](PENDIENTES_EXTERNOS.md).

## 1. Funciones terminadas (2026-11-20)

| Módulo | Backend / web | App | Detalle |
|---|---|---|---|
| Modo entrenamiento | `training_workouts`, `/api/v1/training/*`, `/mi-entrenamiento`, cierre automático | En vivo con GPS, registro manual, historial, progreso y récords, sin conexión, compartir como Legacy Moment | `renovacion/entrenamientos.md` |
| Familia y seguimiento compartido | Vínculos familiares, invitación `/familia/invitacion/{token}`, `/live/{token}` con estados en curso, pausado y finalizado, entrenamientos al aire libre visibles para la familia | Familia, invitar, seguir en vivo | `renovacion/familia.md` |
| NIP de contenido (opcional) | Protección de recuerdos, momentos y fotos; 423 `CONTENT_LOCKED`; bloqueo temporal 429 `PIN_LOCKED` | Ajustes › Seguridad, desbloqueo de 10 min solo en memoria, nada protegido en caché | `renovacion/nip.md` |
| Portarretrato | Diseño guardado, vista previa del servidor, pedido desde el recuerdo, archivo de producción en el admin | Elegir foto, plantilla y textos, guardar y pedir | `renovacion/portarretrato.md` |
| Comercio | Envío a domicilio con costo del servidor, recoger, cancelar pedido sin pagar, reembolso total OpenPay desde el admin, pago manual protegido | Entrega en el checkout (pago web OpenPay de un solo uso) | `renovacion/pagos.md`, móvil `docs/COMMERCE_ARCHITECTURE.md` |
| Moderación | Advertir y suspender a la persona detrás de un reporte | — | `FUNCIONALIDADES.md` §3 |
| NFC | Un chip solo puede quedar ligado a un Tag (hash de UID único) | Activación por escritura | móvil `docs/NFC_ACTIVATION.md` |
| Cuenta | Restablecer contraseña abre la app; eliminar la cuenta borra avisos, preferencias, avatar y ubicación | Pantalla de restablecer en la app | — |
| Tarjeta de ánimo NFC y precios de los mensajes | Tag con `purpose = cheer` ligado a un enlace de ánimo, vínculo automático al surtir, cuotas y precios por canal (`CheerQuota`, `cheer_pricing_settings`), pago de mensajes adicionales con OpenPay por quien envía, limpieza `training:purge-unpaid-cheers`, admin Límites y tarifas › Mensajes de ánimo, `catalog:publish-cheer-card` | Comprar la tarjeta para un enlace, activarla (Activar › Tarjeta de ánimo), cambiar enlace o desactivarla, mensajes «Tarjeta NFC» y «Anónimo» | `renovacion/tarjeta-animo.md`, `FUNCIONALIDADES.md` §5 bis |

## 2. Cambios importantes del cierre

- **NIP en las pantallas del recuerdo (app):** Editar, Fotos y videos,
  Portarretrato, Enlaces y Fotos compradas ya no muestran un error genérico
  ante un 423 `CONTENT_LOCKED`. Ahora muestran el candado con «Desbloquear» y
  recargan sus datos al desbloquear (`ContentLockedBody`). El bloqueo temporal
  (429 `PIN_LOCKED`) dice cuánto falta («Vuelve a intentarlo en 2:05») en
  lugar del «demasiados intentos» genérico.
- **Configuración de tiendas (app):** se quitó el filtro del plugin de
  Stripe; ya no estaba en `app.json` ni en las dependencias, y nada importa
  `@stripe/*`. La configuración resuelta quedó revisada: entitlements de NFC
  (solo `TAG`), enlaces universales y App Links con `autoVerify` para `/n/`,
  `/t/`, `/reset-password/`, `/live/` y `/familia/invitacion/` (`/animo/` es
  solo web), Privacy Manifest y permisos bloqueados (micrófono, ubicación en
  segundo plano en Android, READ_MEDIA_*, AD_ID, SYSTEM_ALERT_WINDOW).
  También los textos de permisos es/en (`locales/*.json`), el cifrado
  exento, los íconos de 1024 px y el splash.
- **Rutas tipadas (app):** `family/` no tiene `_layout`, así que se usa
  `as Href`, como ya hacía `training/`; `tsc` queda limpio.
- **phpstan:** 25 errores en el código del día pasaron a 0, corrigiendo la
  causa. Accesos `?->` redundantes antes de `??`; `find()` con id sin tipo
  cambiado a `whereKey()->first()`; vínculos aceptados con subconsulta tipada;
  fechas de `TrainingWorkout` como `CarbonImmutable`, que es lo que entrega
  `Date::use`; respuestas de entrenamiento sin ternarios con `null`; `match`
  del NIP con `default` (404). Se agregó una sola excepción documentada en
  `phpstan.neon`: Sanctum tipa `currentAccessToken()` como
  `PersonalAccessToken`, pero en sesión web es `TransientToken`, así que el
  `instanceof` es necesario.
- **Inglés:** faltaban 22 textos de la web (historial de `/mi-entrenamiento`,
  página de NIP de contenido, envío sin costo en el checkout) y 7 mensajes
  del reembolso OpenPay. Se agregaron a `lang/web/en.json` y `lang/en.json`.
  La página de invitación familiar, `/live` y el portarretrato ya estaban
  completos.

## 3. Código eliminado (sin consumidores comprobados)

Cada elemento se verificó por imports, rutas, referencias dinámicas,
nombres de páginas Inertia, comandos, tareas programadas y pruebas.

**Backend** (`f3f3e05`, `6107885`, `ea89102`)
- Componentes Vue `public/PlateClipSteps.vue`, `public/SectionHeading.vue` y
  `three/TagAnchor.vue`, junto con su andamiaje en `tagStageContext.ts`.
- `app/Actions/Commerce/ActivateAthleteOwnedProduct.php`, que no tenía ninguna
  llamada.
- Vista `resources/views/plates/face-pdf.blade.php`, que no renderizaba nada.
- Exportaciones y tipos sin uso: etiquetas de producción, reimpresión e
  incidentes de placas en `statusLabels.ts`, `drawPulseRing`, `HOLE_Y`,
  `HOME_URL`, `Passkey`, `DashboardStats`, `DashboardProfileSummary`,
  `ParticipantMatch`, `PublicSport` y `HeroAthlete`.

**App** (`bf49ed2`)
- 13 funciones y hooks sin uso, entre ellos preregistro por token, cuota del
  recuerdo, editar momento, tres hooks de sesiones de soporte, registro de
  push duplicado, chequeo de NFC y utilidades. También 4 tipos.
- `eslint.config.js` ignora `.expo/`, `android/` e `ios/` generados.

**Se conservó a propósito:**
- Checkout web con Stripe y `stripe/stripe-php`: `FINISHER_API_PAYMENT_GATEWAY`
  todavía tiene `stripe` como valor por defecto, pero solo afecta al endpoint
  antiguo `payments/online`. Ver §9.
- Lector QR y pantallas de placas: leen códigos impresos de placas antiguas y
  muestran su historial.
- Modelos y tablas de placas y Stripe, y todas las migraciones aplicadas.
- Dependencias que parecen sin uso pero que expo-router requiere en tiempo
  de ejecución (`@expo/ui`, `expo-symbols`, `expo-glass-effect`).

**Documentos movidos a `docs/historico/`** (backend y app, con `git mv` y un
README de aviso): producción de placas, Legacy Plate V2/V3, comercio con
Stripe PaymentSheet, Device API, Event Ops, ADRs retirados, auditoría y
checklists iniciales, rediseño claro y navegación de octubre. En la app:
`MOBILE_IMPLEMENTATION_PLAN`, `MOBILE_BACKEND_GAPS`,
`MOBILE_BACKEND_REQUIREMENTS`, `API_INTEGRATION`, `VISUAL_OVERHAUL`,
`RESPONSIVE_QA` y `STORE_RELEASE_CHECKLIST`. La tabla de rutas antiguas y
nuevas está en `docs/historico/README.md`.

## 4. Pruebas y verificaciones

### Backend (`finisherLegacy`)

| Verificación | Resultado |
|---|---|
| `composer validate` | válido |
| `vendor/bin/pint --test` | pasa |
| `vendor/bin/phpstan analyse` (nivel 7, sin baseline) | **0 errores** (antes del cierre: 25, todos en archivos del día) |
| `php artisan route:list` | 708 rutas, sin errores |
| `php artisan migrate:status` | 196/196 ejecutadas, 0 pendientes |
| `npx vue-tsc --noEmit` | sin errores |
| `npx eslint resources/js` | sin errores |
| Pest dirigido (tracking, training, portrait, NIP, entrega y cancelación, comercio móvil, carrito, checkout, comunidad) | 236 pruebas, 4 784 aserciones, todas pasan |
| Pest completo (`php artisan test --parallel`) | **1 763 pruebas: 1 760 pasan, 3 omitidas, 0 fallan** (14 230 aserciones, ~7.6 min, 6 procesos) |
| `npm run build` | pasó tras el QA web (lo corrió el QA) |
| Tarjeta de ánimo (`CheerCardsAndPricingTest`) | 16 pruebas nuevas; suites Training, LegacyTags, Commerce y Quotas **356/356** |
| Pagos, tras cambiar la pasarela por defecto del API a OpenPay | **84/84** |

### App (`FinisherLegacyMobile`)

| Verificación | Resultado |
|---|---|
| `npx tsc --noEmit` | sin errores |
| `npx eslint .` | 0 problemas |
| `npx jest` | 43 suites, 450 pruebas, todas pasan |
| `npx expo config --type public` | válida |
| `npx expo-doctor` | 20/21; solo 5 parches de SDK (ver §6) |
| `npx expo export --platform android --platform ios` (carpeta temporal fuera del repo) | compila: Android 4 456 módulos (~10 MB Hermes), iOS 4 365 módulos (~9.9 MB) |

**QA visual:** el QA web usó Playwright con el servidor de Vite, en anchos
de celular, tableta y 1024 px. Commits `d8cad48` … `c9f89d8`. Los scripts
están en la carpeta `web-qa\` del scratchpad de esa sesión; se pueden
reutilizar, pero no están en el repo. El QA móvil se hizo en el emulador de
Android con Metro. Commits `ac5c5a6`, `93e0d1a`, `b4ce24b`, `55958e1` y
`38c5248`.

## 5. Problemas encontrados y resueltos

- Las pantallas del recuerdo mostraban un error genérico con contenido
  protegido con NIP, y el bloqueo temporal no decía cuánto faltaba (§2).
- Había 4 errores de `tsc` en las rutas de Familia (§2).
- Había 25 errores de phpstan en el código del día. Uno de ellos era un tipo
  incorrecto de las fechas de entrenamiento (`Carbon` en vez de
  `CarbonImmutable`) (§2).
- Faltaban textos en inglés en la web y en los mensajes del reembolso (§2).
- `/mi-entrenamiento` daba error 500 en MySQL 8.4 por el alias reservado
  `manual` (`d8cad48`).
- Un commit sin rutas explícitas de otro agente incluyó los `git mv` de la
  documentación (`0dcf562`). Se dejó así, ya publicado, sin reescribir la
  historia. Regla para los agentes: `git commit -- <rutas>`.

## 6. Estado por plataforma

**Android:** la configuración está lista para `eas build -p android --profile
production` (AAB, `versionCode` remoto con `autoIncrement`). App Links con
`autoVerify` en los 5 prefijos. Permisos: cámara, NFC (no obligatorio en el
hardware), ubicación en primer plano con servicio `location`, reproducción
de audio y facturación de Play. Pendientes:
- FCM (`GOOGLE_SERVICES_JSON` como variable tipo archivo de EAS).
- Huella SHA-256 de Play App Signing en el servidor.
- Primer AAB subido a mano.
- `android/` local está desfasada y se regenera con prebuild. EAS siempre la
  genera desde la configuración.

**iOS:** bundle `com.finisherlegacy.app`, `associatedDomains`, entitlement NFC
`TAG`, modos en segundo plano de ubicación y audio, `aps-environment` según
el perfil, Privacy Manifest y `ITSAppUsesNonExemptEncryption=false`.
Pendientes:
- Team ID y ASC App ID en `eas.json › submit.production`, hoy con
  `REEMPLAZAR_*`.
- Llave APNs.
- Capacidad In-App Purchase y productos en App Store Connect.

**Antes de la build de tienda:** con Metro detenido, ejecutar
`npx expo install --fix` (parches de `expo`, `expo-constants`, `expo-linking`,
`expo-router` y `expo-task-manager`), volver a correr las pruebas y hacer
commit.

**EAS:** `eas whoami` responde con la sesión de la cuenta dueña del proyecto.
No se lanzó ninguna build. Comandos exactos, que requieren aprobación:

```bash
eas build -p android --profile preview
eas build -p android --profile production
eas build -p ios --profile production
eas submit -p android --profile production
eas submit -p ios --profile production
```

## 7. Pagos y NFC

- **Pagos:** OpenPay es la única pasarela para bienes físicos. En la app es
  un checkout web de un solo uso (`/pago/app/{token}`), con reembolso total
  desde el admin y webhooks en `/api/webhooks/openpay`. Las ampliaciones
  digitales de fotos, video y voz en la app se venden solo por Apple/Google
  (expo-iap), con verificación en el servidor y notificaciones en
  `/api/webhooks/app-store` y `/api/webhooks/google-play`. Stripe no existe en
  la app; el backend lo conserva para conciliar datos antiguos. Faltan las
  llaves y cuentas reales ([PENDIENTES_EXTERNOS](PENDIENTES_EXTERNOS.md)).
- **NFC:** una URL por pieza (`/t/{código}`), un UID por chip y activación por
  escritura con código de un solo uso. Falta la matriz de pruebas en
  teléfonos reales (móvil `docs/NFC_ACTIVATION.md`).

## 8. Migraciones del 2026-11-20

Todas son aditivas y ya corren en local. En el VPS se aplican con
`php artisan migrate --force` ([DESPLIEGUE](DESPLIEGUE.md) §A.3).

- `2026_11_20_300000_add_shipping_minor_to_orders_table`
- `2026_11_20_300000_create_athlete_family_links_table`
- `2026_11_20_300000_create_portrait_designs_table`
- `2026_11_20_300000_create_training_workouts_table`
- `2026_11_20_300100_add_family_audience_to_tracking_shares`
- `2026_11_20_300100_link_tracking_sessions_to_training_workouts`
- `2026_11_20_400000_add_content_pin_protection`
- `2026_11_20_400000_make_legacy_tag_nfc_uid_hash_unique`: si hay UIDs
  duplicados, no crea el índice y deja «skipped» en el log.
- `2026_11_20_600000_create_payments_refund_permission`
- `2026_11_20_700000_create_cheer_cards_and_pricing`: columnas nuevas en
  `legacy_tags`, `legacy_tag_activation_intents` y `cheer_messages`; tablas
  `cheer_pricing_settings` y `cheer_sender_credits`; producto interno
  `mensaje-de-animo`.

Variables nuevas: `FINISHER_SHIPPING_FEE_MINOR`,
`FINISHER_SHIPPING_FREE_OVER_MINOR` y `TRAINING_WORKOUT_STALE_HOURS`.

## 9. Pendientes que sí son de código o decisión interna

- ~~`FINISHER_API_PAYMENT_GATEWAY` con `stripe` por defecto~~: resuelto en
  `5f13420`, ahora el valor por defecto es `openpay` (Stripe solo queda para
  conciliación histórica).
- `athleteMobileTabs` en `resources/js/config/navigation.ts` no se usa; se
  dejó porque el QA tenía el archivo abierto.
- `PROMPTS-IMAGES.md` (raíz del backend) tiene prompts de fotos de placas y
  puede moverse a `docs/historico/`.
- Tarjeta de ánimo NFC: el vínculo automático al surtir (`FulfillOrderItem` →
  `AssignTagsToOrderItem` → `BindCheerCard`) **no tiene prueba automática**;
  solo se revisó el código. La tarjeta y los mensajes pagados faltan de probar
  en el sandbox de OpenPay y con tarjetas NTAG213 reales.
- Guía para usuarios: [GUIA_DE_LA_APP.md](GUIA_DE_LA_APP.md), con capturas
  del emulador (entorno de demostración).

## 10. Commits

Rama `main` en ambos repos, publicada en `origin/main`. Hay otras ramas
antiguas (`dev/cambios-generales`, `feature/*`) que no se tocaron.

**Backend:** `07768ab` envío, cancelar y reintentos · `ddf31db` moderación ·
`e203932` UID NFC único · `eb02619` borrar cuenta completo · `128cda8`
restablecer abre la app · `81888fb` vínculos familiares · `a87f362`
invitación y `/live` · `6772452` modo entrenamiento · `04b0440` pago manual
seguro · `2098171` reembolso OpenPay · `f26b1d7` detalle de reembolso ·
`e74d5c4` docs pagos · `49dfaf4` portarretrato · `d8cad48` `/mi-entrenamiento`
MySQL · `bf0b1b1` inglés familia y app links · `425e66b` prueba de app links ·
`174abba` NIP · `6e134be` NIP en enlaces y portarretrato · `e6f78ed`
responsive admin · `d9c4e90` familia sigue entrenamiento · `37247cb`
responsive inicio y tienda · `bf3112f` idioma en el servidor · `0417beb` área
del atleta en celulares · `0dcf562` clave del traductor (+ movimientos de
docs) · `c9f89d8` barra superior · `b23d09a`, `411efa8` docs ·
`f3f3e05`, `6107885`, `ea89102` limpieza · `1b8276e` phpstan · `fadea10`
inglés · cierre (este documento) · `cc8329c` precios configurables para
mensajes de ánimo · `3061c88` tarjeta de ánimo NFC en la tienda · `792cb90`
pruebas de la tarjeta y los precios · `b22a73f` docs de la tarjeta ·
`5f13420` la pasarela por defecto del API de pagos es OpenPay · guía de la app
con capturas.

**App:** `42b6e75` mensajes IAP · `d63aad8` portarretrato · `eb6468b`
enlaces y pie de foto · `760cf04` push al cerrar sesión · `d8312e6` momentos
de entrenamiento · `28186a1` restablecer contraseña · `8659344` entrega en el
checkout · `ecef47c` docs comercio · `6b8c9f1` familia · `d611da0` NIP ·
`0c4cf9f` NIP fuera del caché · `60956e4` modo entrenamiento · `b47adfd`
desbloqueo con NIP en el recuerdo · `9a2b3c7` compilaciones · `18d4994` rutas
de Familia · `ac5c5a6`, `93e0d1a`, `b4ce24b`, `55958e1`, `38c5248` QA móvil ·
`bf49ed2` limpieza · `8dcc5d1` docs · `35cb1f6` docs comercio · `538fa64`,
`11e74c5`, `6347791`, `d61812c`, `9b320a9` QA móvil (etiquetas sin recortes,
idioma al momento, ejemplo de correo, botones sobre el teclado, barra de
estado en entrenamiento) · `c88f32a` tarjetas de ánimo en entrenamiento ·
`51f11dc` activar tarjeta de ánimo · `efd6d5f` mascota con imagen ligera.

## 11. Pendientes externos reales

Solo lo que no se resuelve con código: cuentas, llaves, decisiones de
negocio, datos legales y fiscales, pruebas en hardware y capturas reales.
Lista completa, con dónde se configura y cómo se verifica cada cosa:
**[PENDIENTES_EXTERNOS.md](PENDIENTES_EXTERNOS.md)**.
