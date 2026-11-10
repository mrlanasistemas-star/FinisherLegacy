# Retiro de módulos obsoletos (producción de placas metálicas)

Fecha: 2026-11-10 · Alcance: panel de administración (web).

El negocio ya no graba placas metálicas en los eventos. El modelo vigente es:
producto NFC terminado por el proveedor → inventario → venta → entrega →
activación digital (`LegacyTag`, `/t/{code}`). Este documento lista qué se
retiró del panel, qué se conservó y cómo revertirlo.

**Regla aplicada:** se retiró solo la interfaz (rutas web, controladores,
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

### API (fuera de este alcance, sin cambios)

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

### Permisos

No se revocó ningún permiso ni rol (`production.*`, `operator.access`,
`plates.*`, `platetemplates.*`, `productiondevices.*`, `legacyplates.*`,
`incidents.*`, rol `production_operator`), porque algunos todavía protegen la
API anterior. En la web ya no abren nada. Cuando se retire la API se pueden
revocar con una migración reversible `2026_11_10_3xxxxx_*`, guardando la
asignación previa para el `down()`.

### Tablas (todas conservadas, sin cambios)

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

### Código de dominio que quedó huérfano o casi (no se borró)

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
