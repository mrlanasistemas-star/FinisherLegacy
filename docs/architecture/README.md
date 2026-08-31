# Arquitectura — índice

Mapa rápido de los documentos de arquitectura. Para decisiones y su
razonamiento, ver `docs/adr/`; estos documentos son el "cómo se organiza
el código", no el "por qué se decidió así".

| Documento | Cubre |
|---|---|
| `docs/architecture/athlete-identity.md` | Athlete canónico, deduplicación, `AthleteIdentityMatcher` (Slice 3) |
| `docs/architecture/external-event-providers.md` | Provider adapters, DTOs canónicos, Mock provider (Slice 4) |
| `docs/architecture/ingestion-pipeline.md` | CSV/API → `EventIngestionService` → Actions de Slice 3/4 |
| `docs/plate-production.md` | Plate Studio, generación de placas, Event Ops (Slice 5) |
| `docs/legacy-code-lifecycle.md` | Legacy Code, claim, permanencia |
| `docs/device-api/v1.md` | Contrato HTTP que un Desktop/simulador consume |
| `docs/event-ops/README.md` | Dashboard operativo, búsqueda, elegibilidad (Slice 5) |
| `docs/api/v1.md` | Contrato general `/api/v1` — auth, medallas, Event Ops API, integraciones, errores (Slice 6) |
| `docs/api/use-case-matrix.md` | Qué caso de uso vive en qué transporte (Web/API/Device) |
| `docs/api/client-integration-guide.md` | Cómo un cliente externo futuro (Desktop, Mobile) se conecta |
| `docs/api/openapi.yaml` | Especificación OpenAPI de Fase 1, generada a mano a partir de las rutas reales |
| `docs/desktop/technology-decision.md` | Comparación .NET/Tauri/Electron para un futuro Desktop — documentación únicamente, sin código |
| `docs/architecture/legacy-plate-v2.md` | Legacy Plate pre-manufacturada + grabado dinámico, name fitting, entitlement de producción |
| `docs/architecture/commerce.md` | Catálogo, inventario, precio por ventana, carrito/checkout/orders, pagos online/manuales |
| `docs/architecture/athlete-assets.md` | AthleteOwnedProduct/AssetCode (Digital Closet), Athlete history, media de evento |
| `docs/architecture/event-data-source.md` | Organizer ↔ ProviderConnection ↔ Event, `GenericRestEventProvider` |

## Tres clientes, un backend (Slice 6)

```
                    FINISHER LEGACY BACKEND
                           Laravel

            ┌──────────────┼───────────────┐
            │              │               │
        WEB INERTIA      REST API       DEVICE API
            │              │               │
           Vue          CLIENTES       ESTACIONES
                        FUTUROS          FUTURAS
            │              │               │
            └──────────────┼───────────────┘
                            ▼
                  APPLICATION LAYER
                  (Actions/Services/Queries)
                            ▼
                      DOMAIN RULES
                            ▼
                          MYSQL
```

**Ningún cliente vive en este repositorio salvo la Web Vue/Inertia.** Un
futuro Desktop (`FinisherLegacy-Desktop`) o Mobile consumen `/api/v1`
desde repositorios separados — ver `docs/api/client-integration-guide.md`.
Este repo solo expone el contrato; nunca construye el cliente que lo
consume (docs/adr/0007-desktop-event-station.md documenta esa decisión
para el caso Desktop específicamente).

## Capas, de arriba hacia abajo

```
Web Controller (Inertia)     API Controller (/api/v1)     Device Controller (/api/v1, auth:sanctum device)
        └───────────────────────────┬───────────────────────────┘
                                     ▼
                      Actions (App\Actions\*)  — una por caso de uso
                                     ▼
                       Services/Queries (App\Services\*, App\Queries\*)
                                     ▼
                                  Models
```

Ningún controller reimplementa una regla que ya vive en un Action —
Web, API pública y Device API llaman exactamente las mismas Actions
cuando expresan el mismo caso de uso (ver docs/adr/0003 §8 para el
ejemplo más explícito: producción física, y `docs/api/use-case-matrix.md`
para el inventario completo).
