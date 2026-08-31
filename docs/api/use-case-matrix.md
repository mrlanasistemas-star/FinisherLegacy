# Matriz de casos de uso — Web / API / Device

Qué transporte expone qué caso de uso, y qué Action/Query/Service comparten
— nunca dos implementaciones del mismo caso de uso. `-` significa
"deliberadamente no expuesto por ese transporte", no "pendiente".

| Caso de uso | Web | API | Device | Clase compartida |
|---|:---:|:---:|:---:|---|
| Buscar participante | ✓ | ✓ | - | `App\Queries\Operations\SearchEventParticipants` |
| Detalle de participante + elegibilidad | ✓ | ✓ | - | `App\Queries\Operations\GetParticipantOperationsDetail`, `App\Services\PlateEligibilityService` |
| Dashboard Event Ops | ✓ | ✓ | - | `App\Queries\Operations\GetEventOperationsDashboard` |
| Generar placa integrada | ✓ | ✓ | - | `App\Services\PlateGenerationService::generateIntegrated()` |
| Generar placa rápida (contingencia) | ✓ | - | - | `App\Services\PlateGenerationService::generateQuick()` |
| Claim de Legacy Code | ✓ (web pública) | ✓ | - | `App\Services\ClaimLegacyCodeService` |
| Reclamar `ProductionJob` | - | - | ✓ | `App\Services\Devices\ProductionJobClaimService` |
| Preparar / grabar frente / voltear / grabar reverso / verificar QR / entregar | ✓ (fallback manual) | - | ✓ | `App\Actions\Production\*` (docs/adr/0003 §8) |
| Fallar un `ProductionJob` | ✓ | - | ✓ | `App\Actions\Production\FailProductionJob` |
| Reimprimir una placa | ✓ (admin) | - | - | `App\Http\Controllers\Admin\PlateController::reprint()` |
| Sincronizar proveedor externo | ✓ | ✓ | - | `App\Jobs\SyncExternalEventJob` |
| Ver estado de sincronización | ✓ | ✓ | - | `App\Http\Resources\Api\V1\Integrations\SyncRunResource` |
| Vincular/crear evento desde proveedor | ✓ (admin) | - | - | `App\Actions\Integrations\LinkExternalEvent`, `CreateEventFromExternalData` |
| Resolver conflicto de identidad | ✓ (admin) | - | - | `App\Actions\Athletes\ResolveAthleteIdentityConflict` |
| Fusionar atletas | ✓ (admin) | - | - | `App\Actions\Athletes\MergeAthletes` |
| Import CSV/XLSX | ✓ | - | - | `App\Services\Integrations\EventIngestionService` (mismo pipeline que la API/proveedores, ver docs/adr/0005) |
| Pairing de estación | - | - | ✓ | `App\Http\Controllers\Api\V1\Devices\PairingController` |
| Heartbeat / bootstrap de estación | - | - | ✓ | `App\Http\Controllers\Api\V1\Devices\DeviceController` |
| Registro/login de usuario | ✓ (sesión) | ✓ (token) | - | `App\Actions\Fortify\CreateNewUser` |
| Medallas propias | ✓ | ✓ | - | `App\Http\Controllers\Api\V1\MedalController` / equivalente web |
| Catálogo de tienda | - (pendiente) | ✓ | - | `App\Http\Controllers\Api\V1\Store\ProductController` |
| Carrito / checkout | - (pendiente) | ✓ | - | `App\Actions\Commerce\{AddCartItem,CheckoutCart}` |
| Pedidos propios | - (pendiente) | ✓ | - | `App\Http\Controllers\Api\V1\Store\OrderController` |
| Pago online / manual | - (pendiente) | ✓ | - | `App\Actions\Commerce\{CreateOnlinePayment,RegisterManualPayment}` |
| Webhook de pago | - | ✓ (fuera de `/api/v1`) | - | `App\Actions\Commerce\ProcessPaymentWebhook` |
| Modelos de Legacy Plate | - (pendiente) | ✓ | - | `App\Http\Controllers\Api\V1\LegacyPlateModelController` |
| Generar Legacy Plate desde entitlement | - (pendiente) | - (pendiente) | - | `App\Actions\LegacyPlates\GenerateLegacyPlate` |
| Mi equipo (Digital Closet) / claim de gear | - (pendiente) | ✓ | - | `App\Http\Controllers\Api\V1\GearController` |
| Mis eventos (historial) | - (pendiente) | ✓ | - | `App\Queries\Athletes\GetAthleteHistory` (misma Query que el admin) |
| Media de evento (subir/borrar/reordenar) | - (pendiente) | ✓ | - | `App\Actions\Media\*` |
| Resolver fuente de datos de un evento | ✓ (admin, existente) | ✓ | - | `App\Actions\Integrations\ResolveEventDataSource` |
| Crear evento manual | - (pendiente) | ✓ | - | `App\Actions\CreateEvent` |
| Ver/establecer fuente de datos de un Organizer | - (pendiente) | ✓ | - | `App\Http\Controllers\Api\V1\Admin\OrganizerDataSourceController` |
| Probar ProviderConnection | ✓ (admin) | ✓ | - | `App\Actions\Integrations\TestProviderConnection` |
| Resolver incidencia (con resolution_type) | ✓ (admin, flujo viejo sin resolution_type) | ✓ | - | `App\Actions\ResolveIncident` |

## Por qué algunas cosas nunca son API

- **Import CSV/XLSX**: sigue siendo un flujo web (subida de archivo grande,
  mapeo de columnas interactivo) — el pipeline de ingestión que consume
  por debajo (`EventIngestionService`) es el mismo que usaría cualquier
  cliente API futuro, así que no hay lógica bloqueada, solo transporte.
- **Placa rápida / reimpresión / admin (conflictos, merge, vinculación de
  eventos)**: superficies de soporte operado por Super Admin/Event
  Manager desde el panel — no hay caso de uso real de un cliente externo
  (Desktop/Mobile) llamándolas todavía. Se exponen el día que exista ese
  caso de uso real, no antes (docs/api/v1.md — evitar CRUD especulativo).
