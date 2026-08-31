# Event data source — Organizer ↔ Provider ↔ Event

## Modelo

```
Organizer  1───1  OrganizerDataSource ──── ProviderConnection (nullable)
    │                     │
    │ hasMany             │ type: manual | file | api
    ▼                     ▼
  Event ── EventEdition   (default heredado por cada edición del evento)
                │
                │ data_source_type / data_source_provider_connection_id (override, nullable)
                ▼
       App\Actions\Integrations\ResolveEventDataSource
```

`OrganizerDataSource` (brief §16-§17) es el "cómo recibimos datos" por
defecto de un Organizer — un registro por Organizer en esta fase (brief
§17 no exige múltiples fuentes simultáneas, solo una activa con
prioridad/default). `EventEdition` puede **heredar** ese default o
**sobrescribirlo** con sus propias columnas (`data_source_type`,
`data_source_provider_connection_id`) — brief §18/§21/§65.

`App\Actions\Integrations\ResolveEventDataSource::handle($edition)` es el
único lugar que decide cuál aplica: override de la edición si existe,
si no el default del Organizer, si no `EventDataSourceNotConfiguredException`
(`EVENT_DATA_SOURCE_NOT_CONFIGURED`) — nunca un fallback silencioso a
"manual" adivinado.

## Evento manual, sin Organizer

`events.organizer_id` ya era `nullable` (sin cambios de esquema
necesarios) — un evento puede crearse sin Organizer en absoluto (brief
§19-§20/§127/§182). `ResolveEventDataSource` simplemente no encuentra
Organizer y lanza la misma excepción si tampoco hay override propio en la
edición: un evento verdaderamente manual siempre necesita su propio
`data_source_type = manual` explícito, o vive sin resolución de fuente de
datos hasta que alguien lo configure (aceptable — no todo evento manual
necesita sync).

## Providers

`App\Contracts\Integrations\EventProviderAdapter` (Slice 4, sin cambios) +
`App\Services\Integrations\EventProviderRegistry` — se agrega un adapter
nuevo:

### GenericRestEventProvider (`provider_key = generic_rest`)

Para APIs REST simples sin necesidad de código propio (brief §25-§30/
§57-§61). Toda su configuración vive en `ProviderConnection`:

```
base_url                          columna existente
credentials                       columna existente, string único encriptado
                                   (token bearer / api key / password de basic auth
                                   — nunca un array; el username no-secreto de
                                   basic auth vive en settings.basic_username)
settings.auth_type                none | bearer | api_key_header | basic
settings.api_key_header           nombre del header (default X-Api-Key)
settings.headers                  headers estáticos adicionales, no-secretos
settings.participants_endpoint    plantilla de ruta, "{external_event_id}" se sustituye
settings.participants_root        dot-path al arreglo dentro de la respuesta JSON
settings.participant_field_mapping   clave canónica => dot-path (data_get)
settings.results_endpoint / results_root / result_field_mapping   ídem para resultados
settings.event_endpoint / events_endpoint / event_root / event_field_mapping   ídem para eventos
settings.offset_param / settings.page_size_param   nombres de query params de paginación
```

Field mapping usa `data_get()` (dot notation) — deliberadamente **no** un
ETL visual enterprise (brief §28/§60). Un proveedor cuya forma no cabe en
este esquema sigue necesitando un adapter de código dedicado
(`EventProviderAdapter` propio) — `GenericRestEventProvider` no pretende
ser universal.

`supportsIncrementalSync()` devuelve `false` a propósito: la forma de
paginación de un REST genérico no es lo bastante confiable para prometer
sync incremental — cada sync es una re-paginación completa.

Nunca se registran/loggean credenciales; `testConnection()` solo persiste
éxito/fallo + timestamp (`ProviderConnectionTestResult`), igual que el
Mock provider ya existente.

## Deuda conocida

- Sin endpoint API `POST /api/v1/events` para creación manual todavía
  (`CreateEvent` Action no implementada en este pase — ver reporte final,
  sección "Known backend debt"). Event/EventEdition/EventRace siguen
  creándose vía los flujos Web/Admin existentes.
- Sin UI para configurar `OrganizerDataSource`/probar `GenericRestEventProvider`
  desde el admin todavía — backend completo, front pendiente.
