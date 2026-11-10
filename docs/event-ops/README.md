# Event Ops

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

`/operator` — centro de operaciones del evento, no un panel CRUD. Ver
`docs/adr/0006-event-operations.md` para la decisión completa.

## Flujo

```
Seleccionar evento activo (por sesión)
        │
        ▼
Dashboard: proveedor · datos · producción · estaciones · preparación · métricas
        │
        ▼
Buscar dorsal o nombre  →  ficha del corredor  →  elegibilidad
        │
        ▼
[ PRODUCIR PLACA ]  →  Plate + Legacy Code + ProductionJob (en cola)
        │
        ▼
Estación reclama  →  frente → voltea → reverso → QR → lista → entregada
```

## Piezas

| Pieza | Responsabilidad |
|---|---|
| `App\Http\Controllers\OperatorController` | Controller delgado — Query + Actions, nunca lógica propia |
| `App\Queries\Operations\GetEventOperationsDashboard` | Un ViewModel para todo el dashboard |
| `App\Services\PlateEligibilityService` | Elegibilidad (Slice 4, reutilizado) |
| `App\Services\Production\EventProductionReadiness` | Checklist de preparación del evento |
| `App\Queries\Production\GetProductionMetrics` | Tiempos reales medidos |
| `App\Services\PlateGenerationService` | El único Use Case que crea una Plate — mismo que usa cualquier otra superficie |

## Polling

`GET /operator/status` — JSON pequeño, consumido por `fetch()` cada 5s
desde `resources/js/pages/operator/Index.vue`. Nunca WebSockets en este
piloto (docs/adr/0006 §11).

## Búsqueda

Dorsal exacto primero (índice único `event_edition_id`+`bib_number`),
luego nombre — nunca carga la lista completa de participantes al
navegador (`->limit(20)`, servidor).

## Producir

Botón único → `POST /operator/participants/{p}/plate` →
`PlateGenerationService::generateIntegrated()`. Doble click no duplica:
el mismo guard que existía desde Slice 2
(`abort_if(Plate::where('event_participant_id', ...)->exists(), 409)`)
sigue siendo la única fuente de idempotencia — ver
`tests/Feature/Operator/PlateGenerationTest.php` y
`tests/Feature/Operator/EventOpsTest.php`.

## Simulación completa

```
php artisan finisher:simulate-live-event      # Slice 4 — provider + sync
php artisan finisher:simulate-station          # Slice 2 — estación real vía Device API
php artisan finisher:simulate-event-day        # Slice 5 — todo junto, sin servidor HTTP
```
