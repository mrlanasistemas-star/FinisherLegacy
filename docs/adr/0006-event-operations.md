# ADR 0006 — Event Ops

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

Estado: Aceptado
Fecha: 2026-08-23
Contexto previo: ADR 0002 (Device API, Slice 1), ADR 0003 (máquina de
estados de producción, Slice 2), ADR 0004 (identidad canónica, Slice 3),
ADR 0005 (ingestión unificada, Slice 4)

## Decisión

**Event Ops es `/operator` extendido, no una ruta nueva.** El módulo
"Event OS" del Slice 2 (`App\Http\Controllers\OperatorController`) ya
implementaba exactamente lo que este slice pide: selección de evento
activo por sesión, búsqueda de corredor, generación de placa integrada,
placa rápida de contingencia. Crear `/event-ops` en paralelo habría sido
una segunda implementación del mismo concepto — violación directa de "no
duplicar lógica". Este slice **añade** al mismo controller/página: un
dashboard de estadísticas, integración con `PlateEligibilityService`
(Slice 4), y polling de estado — nunca reemplaza el flujo de búsqueda→
producir que ya funcionaba.

```
GET  /operator            → dashboard + búsqueda (index, ya existía, ahora con stats)
GET  /operator/search     → búsqueda por dorsal/nombre (ya existía)
GET  /operator/status     → NUEVO — JSON polling (production + sync stats)
GET  /operator/participants/{p}  → ficha + elegibilidad (ya existía, ahora con PlateEligibilityService)
POST /operator/participants/{p}/plate → producir (ya existía, mismo GenerateIntegratedPlate)
```

## 1. Active event

Sigue viviendo en `session('operator_event_edition_id')` — por
usuario/sesión, nunca una variable global que cambiaría el evento activo
para todos los operadores simultáneamente (§4 del prompt original). Sin
cambios respecto a Slice 2.

## 2. Dashboard — Query, no 80 arrays en el controller

`App\Queries\Operations\GetEventOperationsDashboard::handle(EventEdition)`
regresa un array con cuatro secciones — cada una computada por su propia
pieza pequeña, nunca un único método de 200 líneas:

- **`provider`**: del `ExternalEventMapping` más reciente de la edición
  (Slice 4) — estado de conexión, último sync, si los datos están
  "stale" (más viejos que `config('finisher.event_ops_sync_stale_seconds')`).
- **`production`**: conteos por columna, reutilizando las mismas
  agrupaciones que `ProductionController::COLUMNS` (nunca una segunda
  taxonomía de estados).
- **`stations`**: `ProductionDevice` de la edición activa, con
  `isOnline()` (ya existía en el modelo desde Slice 1).
- **`readiness`**: `App\Services\Production\EventProductionReadiness`.
- **`metrics`**: `App\Queries\Production\GetProductionMetrics` — solo se
  calcula si hay jobs entregados (una edición sin producción aún no
  necesita promedios en pantalla).

## 3. Búsqueda — prioridad y performance

Sin cambios de comportamiento respecto a Slice 2
(`OperatorController::search()`): dorsal exacto primero (por índice
único `event_edition_id`+`bib_number`), luego nombre — nunca fuzzy
search costoso por default, nunca 50,000 participantes cargados al
cliente (siempre `->limit(20)`, servidor).

## 4. Plate eligibility — reutilizado, no reimplementado

`OperatorController::showParticipant()` ahora llama
`App\Services\PlateEligibilityService::check()` (Slice 4) en vez de su
propio `Plate::where(...)->exists()` suelto. Las razones (`NO_RESULT`,
`NO_TEMPLATE`, `IDENTITY_CONFLICT`, `PLATE_ALREADY_EXISTS`) se muestran
tal cual en la ficha del corredor. Un conflicto de identidad
(`AthleteIdentityConflict` pendiente) bloquea *esa* participación, nunca
el evento completo — el resto de corredores producen normal.

## 5. Producir — mismo Use Case, mismo guard de idempotencia

`generateIntegratedPlate()` sigue siendo la única puerta —
`PlateGenerationService::generateIntegrated()`, sin lógica nueva. El
guard contra doble-click ya existía (`abort_if(... exists() ..., 409)`)
y sigue siendo la fuente de verdad de idempotencia — no se agregó un
segundo mecanismo (debounce de UI, locks) porque el existente ya cubre el
caso real: dos requests casi simultáneos, uno gana la carrera de
`Plate::create()`... en realidad el segundo request ve la placa ya creada
por el primero, gracias al chequeo `exists()` — ver §14.

## 6. Producción — sin tocar Slice 2

Ningún endpoint de Event Ops crea o transiciona un `ProductionJob`
directamente — el flujo sigue siendo: `PlateGenerationService` encola el
job (`ProductionJob::create(status: Queued)`), y de ahí en adelante es
exclusivamente responsabilidad del Device API / `ProductionController`
(ADR 0003), nunca de Event Ops. La estación decide cuándo reclamar,
nunca el backend la empuja.

## 7. Reintentos / reimpresión — ya existía

`App\Models\PlateReprint` + `Admin\PlateController::reprint()` (Slice 2)
ya implementan exactamente "job fallido → reimpresión preserva Legacy
Code, evidencia nueva, original conservado" — no se rediseñó. Lo único
que se agregó en Slice 5: `App\Actions\Production\FailProductionJob`
ahora también crea un `EventIncident` (`type = print_failure`) cuando un
job falla, para que aparezca en la cola de incidencias sin que un
operador tenga que reportarlo manualmente (§60-61 del prompt).

## 8. Incidents

Sin sistema nuevo — sigue siendo `App\Models\EventIncident` /
`Admin\IncidentController` (ya existían). Lo nuevo es solo el disparo
automático desde `FailProductionJob` descrito arriba.

## 9. Production metrics

`App\Queries\Production\GetProductionMetrics::handle(EventEdition)`
calcula promedios directo por SQL sobre las columnas de timestamp que
`ProductionJob` ya tenía desde Slice 2
(`queued_at`→`claimed_at`, `front_started_at`→`front_engraved_at`,
`flip_confirmed_at`→`back_started_at`, `back_started_at`→`back_engraved_at`,
`qr_verified_at`→`ready_at`, `queued_at`→`delivered_at`) — nunca una
tabla de agregados precalculados prematura (§76: "no guardar aggregates
prematuramente si SQL puede calcular"). Solo aparece en pantalla cuando
hay al menos un job entregado.

## 10. Event production readiness

`App\Services\Production\EventProductionReadiness::check(EventEdition)`:
evento+edición (siempre true si la edición existe), fuente de datos
(hay `EventParticipant`s), carrera (hay `EventRace`), molde
(`defaultPlateTemplateVersion()` no nulo), estación (hay
`ProductionDevice` para la edición), perfil de máquina (la estación tiene
`machine_profile_id`), calibración QR (`EventProductionCheck.qr_tested_at`
no nulo). **Solo el molde bloquea** (§81) — sin estación, el modo manual
(Event Ops sin estación asignada, exportación bajo "Producción manual")
sigue funcionando.

## 11. Polling, no WebSockets

`GET /operator/status` — JSON pequeño (los mismos cuatro bloques del
dashboard), consumido por `fetch()` desde Vue cada 3-5s, nunca un reload
de página Inertia completo (§35-37, mismo patrón que
`docs/adr/0005 §Sync run` usa para `/admin/integrations/sync-runs/{run}/status`).
No se agregó una segunda ruta bajo `/api/v1` para esto — Desktop nunca la
consume (§94, Desktop solo habla `/api/v1` de dispositivos), así que
duplicarla ahí habría sido una segunda Query sin consumidor real.

## 12. Lo que Slice 5 NO hizo en Event Ops

- Wizard de 6 pasos (§82) — el flujo de configuración ya existente
  (`/admin/events/{edition}/production-setup`, `/admin/plate-studio`,
  `/admin/production-devices`) cubre lo mismo sin un wizard nuevo; no se
  justificó construir una segunda superficie solo por presentación.
- Separar `ProductionJob`/`ProductionAttempt` (§64) — el modelo actual ya
  registra intentos (`attempts`, timestamps de cada fase) sin ambigüedad;
  no había necesidad real de la migración.
- Atajos de teclado más allá del autofocus que ya traía el buscador de
  Slice 2.
