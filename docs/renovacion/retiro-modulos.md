# Retiro de módulos obsoletos (producción de placas metálicas)

Fecha: 2026-11-10 · Alcance: panel de administración (web).
Segunda pasada: 2026-11-11 · API, código de dominio, permisos, tablas, frontend
sin uso y app móvil — ver **§7**. Donde §1–§2 dicen "se conserva" o "sin
cambios" para la API, los permisos o el código huérfano, manda §7.

El negocio ya no graba placas metálicas en los eventos. El modelo vigente es:
producto NFC terminado por el proveedor → inventario → venta → entrega →
activación digital (`LegacyTag`, `/t/{code}`). Este documento lista qué se
retiró del panel, qué se conservó y cómo revertirlo.

**Regla aplicada (primera pasada, 2026-11-10):** se retiró solo la interfaz (rutas web, controladores,
páginas Vue, entradas de menú y widgets). **No se borró ni truncó ninguna
tabla, modelo, migración ni dato.** No se añadió ninguna migración.

## 1. Lo que se retiró

### Rutas web (y sus nombres)

| URL | Nombres de ruta | Permiso que la protegía |
| --- | --- | --- |
| `/operator/*` (Event OS: buscar participante, vista previa, generar placa integrada / rápida) | `operator.*` | `operator.access` |
| `/production/*` (kanban y transiciones de trabajos: preparar, frente, volteo, reverso, verificar QR, entregar, fallar, cancelar) | `production.*` | `production.access` / `production.manage` |
| `/admin/plates/{plate}/export/{face}/{format}`, `/export-qr/{format?}`, `/export-package` (archivos para el láser y QR) | `admin.plates.export`, `admin.plates.export-qr`, `admin.plates.export-package` | `plates.view` |
| `/admin/plates`, `/admin/plates/{plate}`, `POST plates/export-batch`, `POST plates/{plate}/reprint` | `admin.plates.index/show/export-batch/reprint` | `plates.view` / `plates.manage` |
| `/admin/events/{id}/production-setup` (+ `assign-template`, `qr-test`) | `admin.editions.production-setup.*` | `editions.manage` |
| `/admin/legacy-plate-models/*` (diseños de placa y especificación física) | `admin.legacy-plate-models.*` | `legacyplates.manage` |
| `/admin/legacy-plates/production` (+ `produce`) | `admin.legacy-plates.production.*` | `legacyplates.produce` |
| `/admin/legacy-plates/presales` (+ `link`) | `admin.legacy-plates.presales.*` | `legacyplates.manage` |
| `/admin/plate-studio/*` (moldes, versiones, publicación, exportación de prueba, tarjeta de calibración) | `admin.plate-studio.*` | `platetemplates.view` / `platetemplates.manage` |
| `/admin/machine-profiles/*` | `admin.machine-profiles.*` | `platetemplates.*` |
| `/admin/production-devices/*` (estaciones: aprobar emparejamiento, revocar) | `admin.production-devices.*` | `productiondevices.*` |
| `/admin/incidents` (+ `resolve`) — incidencias de evento, solo las creaba la producción | `admin.incidents.*` | `incidents.*` |
| `/admin/legacy-nfc/preparacion/*` (Preparación y entrega: tablero, exportar, datos, iniciar/personalizada/lista/entregada, asignar NFC) | `admin.legacy-nfc.preparation.*` | `production.access` / `production.manage` |

Las URLs retiradas responden **404**. `/admin/legacy-nfc` se conserva como
entrada para marcadores viejos y ahora lleva a *Chips NFC de placas*
(`nfc.view`, si no 403).

**QR:** con las exportaciones de placa desapareció la última opción de generar,
imprimir o exportar QR en el admin. Productos, tags y lotes no tienen ninguna
opción de QR (el lote solo exporta los códigos para grabar el chip NFC). El
campo `products.qr_capable` sigue existiendo, pero en la interfaz solo se
muestra como "Producto conectado (NFC)".

### Controladores eliminados

- `app/Http/Controllers/OperatorController.php`
- `app/Http/Controllers/ProductionController.php`
- `app/Http/Controllers/Admin/PlateController.php`
- `app/Http/Controllers/Admin/PlateStudioController.php`
- `app/Http/Controllers/Admin/MachineProfileController.php`
- `app/Http/Controllers/Admin/ProductionDeviceController.php`
- `app/Http/Controllers/Admin/ProductionSetupController.php`
- `app/Http/Controllers/Admin/LegacyPlateModelController.php`
- `app/Http/Controllers/Admin/LegacyPlatePresaleController.php`
- `app/Http/Controllers/Admin/LegacyPlateProductionController.php`
- `app/Http/Controllers/Admin/IncidentController.php`
- `app/Http/Controllers/Admin/LegacyNfc/PreparationController.php`
- `app/Http/Requests/Nfc/AssignPlateNfcRequest.php` (solo lo usaba Preparación)

Modificados: `Admin/LegacyNfc/WorkspaceController.php` (ya no redirige a
Preparación ni a Placas), `Admin/EditionController.php` (ya no envía
`legacyPlateModels`), `Admin/DashboardController.php` (ver §3).

### Páginas y componentes Vue eliminados

- `resources/js/pages/operator/` (Index, Participant, QuickPlateResult)
- `resources/js/pages/admin/plate-studio/`, `machine-profiles/`,
  `production-devices/`, `legacy-plate-models/`, `legacy-plates/`, `plates/`,
  `incidents/`
- `resources/js/pages/admin/legacy-nfc/Preparation.vue`
- `resources/js/pages/admin/events/ProductionSetup.vue`
- Componentes que solo usaban esas páginas: `components/operator/`,
  `components/preparation/`, `components/plate-studio/`,
  `components/plates/DownloadPlateDialog.vue`,
  `components/plates/PlatePreviewCard.vue`,
  `components/admin/HistoricalPipelineNotice.vue`
- En el Resumen del evento (`admin/editions/Show.vue`) se quitó el bloque
  "Legacy Plate de este evento" (botones a Preventas / Preparación y entrega,
  diseños activos). Se conservan los precios por ventana.

Los archivos generados de Wayfinder (`resources/js/actions`,
`resources/js/routes`) se regeneraron con `php artisan wayfinder:generate`.

### Menú (navigation.ts)

Se eliminó la sección "Operación (histórico)" (Legacy Plates, Preparación y
entrega, Preventas, Diseños, Plate Studio, Perfiles de máquina, Estaciones,
Incidencias de evento, Event OS) y la entrada "Preparación y entrega" del
personal sin panel. Importaciones pasa a *Clientes y atletas* y Legacy Codes a
*Soporte → Placas históricas*.

### Widgets del Dashboard

Se quitaron: pipeline de producción, "placas en producción", cola de
preparación, incidencias de evento, y los datos que el panel ya no mostraba
(serie de ventas por día, ofertas activas, stock bajo duplicado, resumen de
fotos, conteos generales). Ver §3.

### Tests eliminados (probaban únicamente las rutas web retiradas)

Archivos completos:

- `tests/Feature/Admin/LegacyPlateOpsWebTest.php`
- `tests/Feature/Admin/PlateCalibrationCardTest.php`
- `tests/Feature/Admin/PlateExportTest.php`
- `tests/Feature/Admin/PlateReprintAndBatchTest.php`
- `tests/Feature/Admin/PlateStudioTest.php`
- `tests/Feature/Admin/ProductionDeviceAdminTest.php`
- `tests/Feature/Admin/ProductionSetupTest.php`
- `tests/Feature/Nfc/PreparationTest.php`
- `tests/Feature/Production/ProductionKanbanTest.php`
- `tests/Feature/Production/IncidentOnFailureTest.php`
- `tests/Feature/Operator/PlateGenerationTest.php`
- `tests/Feature/Operator/EventOpsEligibilityTest.php`
- `tests/Feature/Operator/EventOpsSearchTest.php`
- `tests/Feature/E2E/PlateLifecycleTest.php`

Casos sueltos eliminados (el resto del archivo sigue):

- `LegacyPlates/LegacyPlateV3Test.php`: editor de layout y especificación física.
- `Admin/PlateTextAsPathsTest.php`: descarga text-as-paths vía ruta admin.
- `Admin/PlateTwoFaceProductionTest.php`: 7 casos que descargaban/reimprimían
  por ruta admin o publicaban en Plate Studio (los de servicio se conservan).
- `Operator/EventOpsDashboardTest.php`: endpoint web `operator.status`.
- `Commerce/OffersAndMarketplaceTest.php`: tope de 3 layouts vía ruta admin.
- `Admin/AdminAccessTest.php`: resolución web de incidencias de evento.

Actualizados a la nueva navegación: `NavigationSmokeTest.php` (las URLs
retiradas ahora deben dar 404), `Admin/AdminNavigationTest.php`,
`Admin/AdminAccessTest.php`, `Admin/Commercial/ActivationsAndNavigationTest.php`.

## 2. Lo que se conserva y por qué

### Público (los clientes siguen tocando sus placas)

- `/l/{code}` (+ `qr.svg`, `continue`, `claim`) y `/n/{token}` (+ `continue`,
  `claim`): links de placas ya vendidas.
- `/dashboard/my-plates`, Mi equipo, Mi legado: historial del atleta.

### Admin que sigue

- `/admin/legacy-codes` (solo lectura) → *Soporte → Placas históricas*.
- `/admin/legacy-nfc/etiquetas` y `/productos` → *Inventario* (chips de
  placas vendidas y productos conectados).
- `/imports` y todo el workspace de eventos (eventos = logros del atleta).

### API (retirada en la segunda pasada, ver §7)

Siguen registradas en `routes/api.php` y conservan sus tests:

- Device API de estaciones (`/api/v1/devices/*`, `/api/v1/device*`,
  `/api/v1/production/jobs/*`) y su servicio de dominio
  (`app/Actions/Production/*`, `app/Actions/Devices/*`).
- Event Ops API (`/api/v1/event-ops/*`, `operator.access`), que genera placas.
- `/api/v1/admin/incidents/{incident}/resolve` y
  `/api/v1/legacy-plate-models` (público).

Sin el panel ya no se pueden aprobar estaciones nuevas, así que la Device API
no recibe uso nuevo. **Pendiente de decisión:** retirar estas rutas API cuando
se confirme que ninguna app móvil o estación las llama.

### Permisos (revocados en la segunda pasada, ver §7)

En la primera pasada no se revocó ningún permiso ni rol (`production.*`, `operator.access`,
`plates.*`, `platetemplates.*`, `productiondevices.*`, `legacyplates.*`,
`incidents.*`, rol `production_operator`), porque algunos todavía protegen la
API anterior. En la web ya no abren nada. Cuando se retire la API se pueden
revocar con una migración reversible `2026_11_10_3xxxxx_*`, guardando la
asignación previa para el `down()`.

### Tablas (estado de la primera pasada; la segunda borra las vacías, ver §7)

Conteos de la base local el 2026-11-10:

| Tabla | Filas | Motivo para conservarla |
| --- | --- | --- |
| `plates` | 8 | Placas vendidas: `/l/{code}`, historial del atleta |
| `legacy_codes` | 8 | Links públicos `/l/{code}` |
| `nfc_tags` | 0 | Links públicos `/n/{token}` |
| `production_jobs` | 8 | Historial de producción de esas placas (auditoría) |
| `production_artifacts` | 0 | Archivos inmutables de producción (auditoría) |
| `plate_reprints` | 0 | Historial |
| `plate_templates` / `plate_template_versions` | 3 / 3 | Las placas guardan la versión con la que se grabaron (la vista pública vuelve a renderizar con ella) |
| `event_plate_templates` | 2 | Relación histórica evento ↔ molde |
| `legacy_plate_models` / `legacy_plate_model_fields` | 3 / 27 | Layout congelado en placas vendidas; API pública |
| `legacy_plate_entitlements` | 0 | Dato comercial (preventas pagadas): no se toca |
| `machine_profiles` | 1 | Referenciado por moldes |
| `production_devices`, `device_pairing_requests`, `device_idempotency_keys` | 0 / 0 / 0 | Los usa la Device API |
| `event_production_checks` | 0 | Historial de preparación de eventos |
| `event_incidents` | 1 | Historial; API de resolución |

**Candidatas a borrar más adelante (no se hizo):** `machine_profiles`,
`production_devices`, `device_pairing_requests`, `device_idempotency_keys`,
`event_production_checks`, `production_artifacts`, cuando se retire la Device
API. Antes hay que respaldarlas y revisar las llaves foráneas desde `plates` /
`production_jobs`.

### Código de dominio que quedó huérfano o casi (borrado en la segunda pasada, ver §7)

- `app/Queries/Preparation/GetPreparationBoard.php`,
  `app/Actions/Preparation/AdvancePlatePreparation.php`,
  `app/Services/CalibrationCardService.php`: ya no los usa nada.
- `app/Actions/Devices/ApproveDevicePairing.php`,
  `RevokeProductionDevice.php`: solo los usan los tests de la Device API.
- `resources/js/types/plate-studio.ts`: ya no lo importa nada.
- Seeders `PlateTemplateSeeder`, `MachineProfileSeeder`,
  `LegacyPlateModelSeeder`: siguen funcionando (llenan tablas que se conservan).

## 3. Dashboard nuevo (`/admin`)

Todas las cifras salen de consultas reales (`Admin\DashboardController` +
`App\Queries\Admin\CommerceDashboardMetrics`). Si no hay datos se muestra un
estado vacío, nunca un número inventado:

- Ventas del periodo: total, pedidos, ticket promedio, piezas.
- **Finisher Legacy Tags** (nuevo): unidades en stock / vendidas (esperando
  activación) / activadas / reservadas, de `legacy_tags.status`.
- **Pagos del periodo** (nuevo, requiere `payments.view`): pagos recibidos y
  su monto, reembolsos, pendientes y fallidos, de `payments`.
- Pedidos recientes, "Por atender" (pedidos, casos de soporte abiertos, fotos
  por revisar, reportes de comunidad), eventos de la semana.
- Canales, venta rápida, activación por cohorte, activaciones recientes,
  soporte, inventario, producto (aperturas NFC / código / enlace) y colecciones.

Pulido: las tarjetas cambian suavemente al pasar el cursor; al cambiar el
periodo, las cifras se vuelven skeletons mientras recargan; en el sidebar los
colores de hover/activo tienen transición junto con el colapso. Todo respeta
`prefers-reduced-motion`.

## 4. Navegación del admin

`resources/js/config/navigation.ts`: secciones del sidebar → pestañas:

1. **Dashboard**
2. **Clientes y atletas**: Clientes (usuarios) · Atletas · Eventos y resultados · Importaciones · Duplicados
3. **Catálogo**: Colecciones y diseños · Productos · Categorías
4. **Inventario**: Existencias · Ubicaciones · Lotes · Transferir · Proveedores (físico) · Unidades NFC · Chips NFC de placas · Productos conectados · Activaciones
5. **Ventas**: Pedidos · Pagos y reembolsos · Venta rápida · Cupones y ofertas · Límites y tarifas
6. **Contenido**: Fotos de eventos · Fotógrafos · Comunidad y moderación · Contenido del sitio
7. **Aliados**
8. **Reportes**: Ventas
9. **Soporte**: Incidencias · Mensajes · Placas históricas
10. **Configuración**: Ajustes · Pagos (`payments.configure`) · Organizadores · Fuentes de datos · Conexiones
11. **Auditoría y seguridad**: Auditoría · Roles y permisos

Cada pestaña lleva su permiso y una sección solo aparece si la persona puede
abrir al menos una pestaña. *Límites y tarifas* (`admin.quotas.index`,
`quotas.manage`) solo se muestra si existe el archivo de Wayfinder de esa ruta
(`import.meta.glob` sobre `resources/js/routes/admin/quotas/index.ts`). Así no
puede quedar como link roto. *Recuerdos* y *Portarretratos* no tienen página de
admin todavía, por eso no aparecen.

## 5. Cómo revertirlo

Todo es código y está en git. No hay cambios de esquema ni de datos.

1. `git checkout <commit-anterior> -- <rutas>` para cada archivo eliminado o
   modificado listado en §1 (controladores, páginas, componentes, tests,
   `routes/web.php`, `routes/web/nfc.php`, `resources/js/config/navigation.ts`,
   `resources/js/config/areaNav.ts`, `admin/Dashboard.vue`, `DashboardController.php`,
   `EditionController.php`, `admin/editions/Show.vue`, `LegacyNfc/WorkspaceController.php`).
2. `php artisan wayfinder:generate` y `php artisan route:list` para comprobar.
3. Como no se tocaron permisos ni roles, quienes tenían acceso lo recuperan sin
   hacer nada más.

## 6. Añadidos en esta misma pasada (admin de inventario NFC)

- **Lote → "Descargar códigos de activación"** (`tags.manage`):
  `GET /admin/inventario/lotes/{lot}/codigos-activacion.csv`
  (`admin.catalog.lots.activation-codes.download`) entrega tal cual el CSV
  privado más reciente que siga existiendo (`storage/app/private/exports/activation-codes/`,
  ruta tomada del log `tags.lot.activation_codes_generated`). Cada descarga
  queda en el log como `tags.lot.activation_codes_downloaded`. Si hay piezas
  sin código, la página ofrece "Generar códigos faltantes"
  (`POST …/codigos-activacion`, `GenerateActivationCodes`). Solo llena las
  piezas que no tienen código y escribe un CSV propio con sufijo aleatorio,
  así nunca sobrescribe el archivo de creación del lote. Los códigos se
  guardan con hash, por lo que ese archivo es la única copia legible.
- **Tag → "Reportar perdido" / "Reportar robado" / "Recuperar"** (`tags.manage`):
  `POST /admin/tags/{tag}/extravio` (`stolen`, `reason`) y
  `POST /admin/tags/{tag}/recuperar`, que llaman a `ManageTag::reportMissing()`
  y `ManageTag::recover()`. Los estados `lost` (Extraviado) y `stolen` (Robado)
  tienen badge propio (`resources/js/lib/tagStatus.ts`) y aviso en el detalle.

## 7. Segunda pasada (2026-11-11): API, dominio, permisos, tablas y app

Pedido: "haz la limpieza de código viejo, quita lo que no sirva". Antes de
borrar cada pieza se buscó con grep cualquier referencia en rutas,
controladores, jobs, listeners, scheduler, policies, providers, seeders,
migraciones, tests y en la app móvil. Solo se borró lo que ninguna función
viva usa. Todo está en git y se puede revertir (ver el final de §7.8).

### 7.1 API retirada (ahora responde 404)

| Endpoint | Por qué se pudo quitar |
| --- | --- |
| Device API de estaciones: `POST /api/v1/devices/pair`, `/pair/confirm`, `GET /api/v1/device`, `/device/bootstrap`, `POST /device/heartbeat`, `/api/v1/production/jobs/*` (next, show, claim, release, artifact, prepare, front/back start/complete, flip, qr/verify, deliver, fail, cancel) | Sin el panel ya no se aprobaban estaciones (0 filas en `production_devices`). La app móvil nunca la llamó. |
| Event Ops API `/api/v1/event-ops/*` (dashboard, búsqueda, participante, `POST …/plate`) | Generaba placas grabadas. La app móvil nunca la llamó. |
| `POST /api/v1/admin/incidents/{incident}/resolve` | Las incidencias de evento solo las creaba la producción (al fallar un trabajo). La app no la llama. |
| `GET /api/v1/legacy-plate-models` | La app tenía un cliente y un hook, pero ninguna pantalla los usaba. También se borraron de la app. |

También se quitaron de `bootstrap/app.php` los alias `device.token`,
`device.idempotent`, `ability` y `abilities`, y el renderer de errores propio
de la Device API. Los demás `/api/*` siguen con `ApiExceptionRenderer`. De
`AppServiceProvider` se quitó el limitador `device-pairing-confirm`. De
`config/finisher.php` se quitaron las claves `device_*`, `pairing_*`,
`idempotency_ttl_seconds`, `event_ops_sync_stale_seconds` y
`production_artifact_disk`. Se borró `config/plate-studio.php`. De
`ApiErrorCode` se quitaron `PLATE_ALREADY_EXISTS`, `PLATE_TEMPLATE_MISSING`,
`LEGACY_PLATE_NOT_PAID` y `LEGACY_PLATE_ALREADY_EXISTS`. En
`docs/api/v1.md` esas secciones quedaron marcadas como RETIRADO. Los docs de
`device-api/`, `event-ops/`, `desktop/`, `plate-production.md` y los ADR
0002, 0003, 0006 y 0007 tienen ahora el aviso de OBSOLETO.

### 7.2 Código de dominio borrado (backend)

Nada vivo lo referenciaba, solo las rutas de §7.1, los tests de esas rutas o
el propio código retirado:

- **Controladores y recursos API:** `Api/V1/Devices/*` (Device, Pairing,
  ProductionJob), `Api/V1/EventOpsController`, `Api/V1/Admin/IncidentController`,
  `Api/V1/LegacyPlateModelController`, `Resources/Api/V1/Devices/*`,
  `Resources/Api/V1/EventOps/*`, `LegacyPlateModelResource`,
  `Requests/Api/ResolveIncidentRequest`.
- **Acciones:** `Actions/Devices/*` (7, incluidas `ApproveDevicePairing` y
  `RevokeProductionDevice`), `Actions/Production/*` (11),
  `Actions/Preparation/AdvancePlatePreparation`, `Actions/ResolveIncident`,
  `Actions/LegacyPlates/GenerateLegacyPlate`.
- **Consultas:** `Queries/Preparation/GetPreparationBoard`,
  `Queries/Production/*`, `Queries/Operations/GetEventOperationsDashboard`,
  `GetParticipantOperationsDetail` y `SearchEventParticipants`. Se conservan
  `GetEventParticipantMetrics` y `GetEventParticipantsList`, que usa el admin
  de participantes.
- **Servicios de producción, generación, render y exportación:**
  `Services/Production/*` (coordinador, máquina de estados, artefactos,
  readiness, `Drivers/MockLaserDriver`), `Services/Devices/ProductionJobClaimService`,
  `CalibrationCardService`, `PlateGenerationService`, `PlateSnapshotBuilder`,
  `PlateEligibilityService`, `PlateExportService`, `PdfExportService`,
  `GdPlateRenderer`, `PlateTemplateRenderService`, `FontOutlineService`,
  `LegacyPlates/LegacyPlateNameFitService` y `AthleteNameFormatter`.
- **Soporte:** `Support/Devices/*`, `Support/Production/*`,
  `PlateEligibilityResult`, `PlateMeasurementService`, `PlateRenderData`,
  `PlateVectorIcons`, `PlateDynamicFields`, `PlateFilename`,
  `PlateRendererVersion`, `TextFitService`, `LegacyPlates/NameFitResult`.
- **Contratos:** `Contracts/Production/LaserDriver` y `Contracts/ProductionActor`.
  `User` ya no lo implementa (se quitó `productionActorLabel()`).
- **Middleware:** `EnsureProductionDeviceToken` y `EnsureIdempotencyKey`, que
  era el de la Device API. `EnsureApiIdempotencyKey` se conserva porque lo usa
  `POST /api/v1/events`.
- **Excepciones:** `Exceptions/Devices/*`, `Exceptions/Production/*`,
  `PlateAlreadyExistsException`, `PlateTemplateMissingException`,
  `LegacyPlateAlreadyExistsException`, `LegacyPlateNotPaidException`.
- **Enums:** `DeviceAbility`, `DeviceErrorCode`, `DevicePairingStatus`,
  `ProductionDeviceStatus`, `ProductionJobStatus`, `ProductionFailureCode`,
  `PreparationStage`, `IncidentStatus`, `IncidentType`,
  `IncidentResolutionType`, `PlateSportType`.
- **Modelos y factories:** `ProductionJob`, `ProductionArtifact`,
  `ProductionDevice`, `DevicePairingRequest`, `DeviceIdempotencyKey`,
  `MachineProfile`, `EventProductionCheck`, `EventIncident` y
  `EventPlateTemplate`, con sus factories. También las relaciones que los
  usaban: `EventEdition::productionJobs/incidents/plateTemplateAssignments/productionCheck/defaultPlateTemplateVersion`,
  `EventParticipant::incidents`, `Plate::productionJobs/latestProductionJob/incidents`
  y `PlateTemplateVersion::eventAssignments/isEditable`. Además se quitaron
  métodos sin uso: `PlateTemplate::latestPublishedVersion/nextVersionNumber/isUsedByAnyPlate`
  y `LegacyPlateModel::toSnapshotArray`.
- **Comandos:** `finisher:simulate-event-day` y `finisher:simulate-station`,
  que eran demos de la producción. `finisher:simulate-live-event` se conserva
  y ya no imprime la "elegibilidad para placa".
- **Seeders:** `MachineProfileSeeder` y `PlateTemplateSeeder` (moldes de Plate
  Studio). `DemoDataSeeder` ya no usa `PlateGenerationService`. Ahora escribe
  directamente unas pocas placas **históricas** ya entregadas, cada una con su
  Legacy Code, para que `/l/{code}` y el historial tengan datos. Además ya no
  crea moldes por evento, trabajos de producción, incidencias ni el usuario
  `production@finisherlegacy.com`.

### 7.3 Lo que se conserva (vivo) y por qué

- **`/l/{code}` y `/n/{token}`**, con `Plate`, `LegacyCode`, `NfcTag`,
  `NfcAssignment`, `QrCodeService`, `LegacyCodeQrService` y `Qr/GdImageBackEnd`
  (`/l/{code}/qr.svg`): son los links de placas ya vendidas.
- **`PlateTemplate` y `PlateTemplateVersion`** (solo modelos, sin el
  renderer): `plates.plate_template_id` y `plate_template_version_id` tienen
  llave foránea hacia ellos, y la Auditoría los nombra.
- **`LegacyPlateModel`, `LegacyPlateModelField`, `LegacyPlateEntitlement`,
  `CreateLegacyPlateEntitlement`, `LinkLegacyPlateEntitlementToParticipant`,
  `LegacyPlateLayouts` y `LegacyPlateModelSeeder`:** el carrito y el checkout
  (`AddCartItem`, `CheckoutCart`), la ingesta de participantes, el visor
  `Plate::layoutViewer()` y tres migraciones los siguen usando. Las
  preventas pagadas son datos comerciales.
- **`PlateReprint` y `PlateStatus`, `PlateGenerationMode`, `PlateLayoutType`,
  `PlateBackTransform`, `PlateTemplateVersionStatus`:** son casts y relaciones
  de modelos que se conservan.
- **`StaffRole::ProductionOperator`:** es un cast de
  `event_staff_assignments.role` y puede haber filas históricas con ese valor.
- **Placas históricas** del admin (`/admin/legacy-codes`), pedidos, pagos,
  auditoría, eventos, resultados y participantes, y todo el código de Stripe
  (se conserva para conciliación). Nada de eso se tocó.
- **`dompdf`** (lo usa `LegacyTags/StoryPrintRenderer`) y `resources/fonts/*`
  (los usan la impresión de historias, el portarretrato y la marca de agua de
  fotos).
- **`AuditController`:** sigue nombrando `ProductionJob`, `ProductionDevice` y
  `EventIncident` como etiquetas de texto, para leer el historial de
  actividad viejo.

### 7.4 Permisos y rol — migración `2026_11_11_500000_retire_plate_production_permissions`

Se quitaron del catálogo (`config/permissions.php`) y de
`RolePermissionSeeder`. La migración los quita de todos los roles y usuarios
y luego borra la fila del permiso:

`production.view`, `production.manage`, `production.access`,
`productiondevices.view`, `productiondevices.manage`, `operator.access`,
`platetemplates.view`, `platetemplates.manage`, `legacyplates.manage`,
`legacyplates.produce`, `incidents.view`, `incidents.manage`, `plates.view`,
`plates.manage`, `operators.view`, `operators.manage`.

Ningún código los revisa (se verificó con grep en `app/`, `routes/`,
`resources/js` y `config`). La sección *Soporte → Incidencias* usa
`support.*`, no `incidents.*`. *Placas históricas* usa `legacycodes.view`.

El rol **`production_operator`** se borra solo si ningún usuario lo tiene.
Si alguien todavía lo tiene, se conserva sin estos permisos y se registra un
warning en el log. También salió de `SystemRoles` y del seeder.
`event_operator` y `event_manager` se conservan con sus permisos vigentes.

**Reversible:** antes de quitar nada, la migración copia cada asignación
(rol → permiso, usuario → permiso y la definición del rol con su etiqueta y
sus permisos restantes) en la tabla nueva `retired_permission_assignments`.
`down()` recrea los 16 permisos, devuelve cada asignación, recrea el rol y
borra esa tabla.

### 7.5 Tablas — migración `2026_11_11_500100_drop_empty_plate_production_tables`

Candidatas (sus modelos se borraron en §7.2): `machine_profiles`,
`production_devices`, `device_pairing_requests`, `device_idempotency_keys`,
`production_jobs`, `production_artifacts`, `event_production_checks`,
`event_plate_templates`, `event_incidents`.

Reglas que se revisan al correr `up()`:

1. Si la tabla tiene filas **no se borra**, y queda un warning en el log.
2. Una tabla vacía se borra solo si **todas** las tablas que todavía le
   apuntan con llave foránea también se borran. Esto se calcula con
   `Schema::getForeignKeys()`.

`down()` vuelve a crear, con su esquema final, cada candidata que falte,
empezando por las tablas padre. Nunca toca una tabla que ya existe.

**Nunca se borran** (las referencia una tabla que se conserva):
`plate_templates`, `plate_template_versions` (FK desde `plates`) y
`legacy_plate_models`, `legacy_plate_model_fields` (FK desde `plates` y
`legacy_plate_entitlements`; además las usa el carrito). Tampoco se borra
`plate_reprints`, que tiene modelo vivo.

**Resultado esperado en la base local** (conteos del 2026-11-10):

| Tabla | Filas | Resultado |
| --- | --- | --- |
| `production_jobs` | 8 | **Se queda** (tiene filas) |
| `machine_profiles` | 1 | **Se queda** (tiene filas y `production_jobs` le apunta) |
| `production_devices` | 0 | **Se queda** (`production_jobs` le apunta) |
| `event_plate_templates` | 2 | **Se queda** (tiene filas) |
| `event_incidents` | 1 | **Se queda** (tiene filas) |
| `production_artifacts` | 0 | Se borra |
| `event_production_checks` | 0 | Se borra |
| `device_pairing_requests` | 0 | Se borra |
| `device_idempotency_keys` | 0 | Se borra |

Esto es intencional: las filas históricas no se tocan. Las tablas que se
quedan ya no tienen modelo ni código que las lea. Para borrarlas más
adelante: respaldarlas, vaciarlas a propósito y volver a correr una
migración equivalente.

Las dos migraciones se probaron en una base SQLite aparte, no en la local,
en este orden:

1. `up`.
2. `down`: permisos, asignaciones, rol y tablas se restauraron.
3. Otra vez `up`, con una fila en `production_jobs`: se conservaron
   `production_jobs`, `production_devices` y `machine_profiles`.

En una base nueva (tests) todas las candidatas están vacías y se borran.

### 7.6 Frontend web sin uso (`resources/js`)

Borrado porque nada lo importaba. Se verificó con un escaneo de imports
(alias `@/` y relativos) y con `vue-tsc`:

- `types/plate-studio.ts`, `config/areaNav.ts`.
- `components/public/LegalPlaceholderNotice.vue`, `public/LegacyTimeline.vue`
  (la que se usa es `components/legado/LegacyTimeline.vue`),
  `public/MagneticButton.vue` y `public/media/` (`FinalMedia`,
  `HomeHeroMedia`, `StoryMediaSequence`).
- `components/shared/LegacyPlatePreview.vue`, `shared/LinkPlateDialog.vue`,
  `shared/AppContainer.vue`.
- `components/admin/EventEditionSelector.vue`, `admin/SecondaryNav.vue`.
- Restos del starter kit: `AlertError.vue`, `AppLogo.vue`, `AppLogoIcon.vue`,
  `Heading.vue`, `PlaceholderPattern.vue`, `TextLink.vue`, `ui/alert/`,
  `ui/card/`, `ui/navigation-menu/`.
- Composables: `useAssetProbe`, `useCountUp`, `useInView`, `useParallax`,
  `useSidebarMode`.

No se borraron los videos e imágenes de `public/media/home/` que usaban los
componentes `public/media/*`. Son contenido de marca y no código, así que
quedan para decidir aparte. Los que no usa nada:
`hero/finisher-hero-desktop.{mp4,webm}`, `story/training-dawn.{mp4,webm,jpeg}`,
`story/training-dawn-poster.webp` y los `.jpeg` originales de `story/` y
`final/`.

### 7.7 App móvil (`FinisherLegacyMobile`)

- Se borraron `src/api/legacyPlateModels.ts`, `src/hooks/use-legacy-plate-models.ts`
  y el tipo `LegacyPlateModel` de `src/types/models.ts`: el endpoint ya no
  existe y nada los importaba. También `components/ui/separator.tsx`,
  `components/motion/index.ts` y `assets/images/brand/fl-logo-principal.png`,
  que no tenían ninguna referencia.
- Se conservan gear, medallas, mis eventos, tracking, legacy y nfc. Todas sus
  rutas se pueden alcanzar desde la navegación, las pestañas o los deep links.
  También se conservan `LegacyPlateModelDetail`, el campo `legacyPlate` y
  `PlateCard`, porque las pantallas de mis eventos y `/legacy/[code]`
  muestran placas históricas.
- No se quitó ninguna dependencia npm. Las que no se importan
  (`@expo/ui`, `expo-symbols`, …) son dependencias nativas de expo-router o de
  tooling.
- Docs: `docs/ARCHITECTURE.md` se puso al día y ahora tiene la sección
  "Módulos retirados". `COMMERCE_ARCHITECTURE.md` (Legacy Plates) tiene el
  aviso de OBSOLETO. Los demás planes viejos ya tenían aviso de obsoleto o
  histórico.

### 7.8 Tests

Borrados porque solo probaban código retirado: `Api/V1/Devices/*` (5),
`Api/V1/EventOpsApiTest`, `Api/V1/EventOpsPerformanceSmokeTest`,
`Admin/IncidentResolutionApiTest`, `ResolveIncidentTest`,
`Console/SimulateEventDayTest`, `Devices/ProductionDeviceStatusTest`,
`Production/*` (EventProductionReadiness, GetProductionMetrics,
ProductionArtifactImmutability), `Unit/Production/ProductionJobStateMachineTest`,
`Integrations/PlateEligibilityServiceTest`,
`LegacyPlates/AthleteNameFormatterTest`, `LegacyPlateNameFitServiceTest`,
`Admin/PlateBackTransformTest`, `PlateTextAsPathsTest`,
`PlateTwoFaceProductionTest`, `Operator/EventOpsDashboardTest`,
`Operator/IntegratedSnapshotTest` y `PlateTemplateSeederTest`.

Actualizados:

- `AdminNavigationTest`: los permisos retirados ya no se entregan y el rol
  `production_operator` ya no se siembra.
- `RoleManagementTest`, `NfcAdminTest` y `Nfc/helpers.php`: ya no usan
  permisos, roles ni trabajos de producción retirados.
- `ApiErrorContractTest`: los 403 y 404 se prueban contra
  `/api/v1/admin/organizers/{id}/data-source`.
- `StoreApiTest`: `/api/v1/legacy-plate-models` debe dar 404.
- `ExternalIdentityAndOverrideTest`: la placa histórica se crea con factory.
- `LegacyPlateEntitlementFlowTest` y `LegacyPlateV3Test`: se quitaron los
  casos que generaban placas.

**Cómo revertir la segunda pasada:** `php artisan migrate:rollback` de las
dos migraciones (permisos y tablas se restauran como se describe arriba) y
`git checkout <commit-anterior> -- <rutas>` de los archivos listados.
