# ADR 0003 — Production state machine, immutable artifacts, front/flip/back/QR

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

Estado: Aceptado
Fecha: 2026-08-21
Contexto previo: ADR 0001 (baseline), ADR 0002 (Device API, Slice 1)

## Decisión

`ProductionJob` deja de ser un estado grueso (`queued`/`processing`/
`completed`/`failed`/`cancelled`) y pasa a representar el **proceso físico
de grabado** con granularidad real:

```
queued → assigned → preparing → engraving_front → awaiting_flip
       → engraving_back → verifying_qr → ready → delivered
```

con `failed`/`cancelled` como salidas según las reglas de abajo. `Plate`
sigue representando el **producto** (`queued`/`processing`/`ready`/
`delivered`/`reprint`/`cancelled`) — nunca se mezclan las dos máquinas de
estado. Una `Plate` puede tener varios `ProductionJob` a lo largo de su vida
(reimpresiones); solo uno relevante a la vez.

## Responsabilidades separadas (§46-47)

- **`App\Services\Production\ProductionJobStateMachine`**: la única fuente
  de verdad de qué transición de `ProductionJob.status` es legal. No sabe
  nada de `Plate`.
- **`App\Services\Production\PlateProductionCoordinator`**: sincroniza
  `Plate.status` cuando `ProductionJob.status` cambia — nunca al revés.
- **`App\Actions\Production\*`**: los casos de uso reales (11 clases, una
  por intención — `StartProductionPreparation`, `StartFrontEngraving`,
  `CompleteFrontEngraving`, `ConfirmPlateFlip`, `StartBackEngraving`,
  `CompleteBackEngraving`, `VerifyProductionQr`, `DeliverProductionPlate`,
  `FailProductionJob`, `CancelProductionJob`, y
  `App\Actions\Devices\ReleaseProductionJob`). Nunca un `updateStatus($string)`
  genérico.

Reemplaza a `App\Services\ProductionService` (Slice 0/1), que mezclaba las
tres responsabilidades — eliminado, no dejado como código muerto.

## Mapeo Plate ↔ Job (§47)

| ProductionJob.status | Plate.status |
|---|---|
| `queued` | (sin cambio — ya está `queued` desde que se generó la placa) |
| `assigned`, `preparing`, `engraving_front`, `awaiting_flip`, `engraving_back`, `verifying_qr` | `processing` |
| `ready` | `ready` (+ `produced_at` si no tenía) |
| `delivered` | `delivered` (+ `delivered_at` si no tenía) |
| `failed` | **sin cambio** — el intento falló, no el producto; ver §Failure |
| `cancelled` | `cancelled`, salvo que la placa tenga otro job `ready`/`delivered` (reimpresión) |

## 8. Web y Device comparten las mismas Actions

`App\Http\Controllers\ProductionController` (web, manual) y
`App\Http\Controllers\Api\V1\Devices\ProductionJobController` (Device API)
inyectan y llaman **las mismas** clases de `app/Actions/Production`. La
única diferencia entre transportes vive en `App\Actions\Production\
ProductionJobAction::assertOwnership()`: un `ProductionDevice` solo puede
actuar sobre un job que reclamó; un operador web en modo manual puede
actuar sobre cualquiera (es el fallback humano, no un claim). Un solo
`ProductionJobStateMachine`, nunca dos implementaciones de las reglas.

## Actor: User vs. ProductionDevice (§35)

`App\Contracts\ProductionActor` (implementada por `User` y
`ProductionDevice`) deja que cada Action reciba "quién está haciendo esto"
sin ramificar por `instanceof`. Para los cuatro eventos que la máquina de
estados necesita poder consultar (frente/reverso/volteo/QR) se agregaron
columnas polimórficas nuevas — `front_actor_type/id`,
`back_actor_type/id`, `flip_actor_type/id`, `qr_actor_type/id` — nunca se
reutilizó `front_engraved_by`/`back_engraved_by`/`qr_verified_by`
(`belongsTo(User)` desde antes de Slice 1): meter un `production_device_id`
ahí habría sido un FK mintiendo sobre su propio tipo. Esas tres columnas
viejas se conservan sin tocar, como evidencia histórica.

Entregado/fallido/cancelado/liberado **no** tienen columna de actor
dedicada — se resuelven por el causer polimórfico nativo de ActivityLog
(`causedBy($actor)`), que ya soporta cualquier modelo. Añadir 4 pares de
columnas más solo para esos cuatro eventos raros habría sido sobre-
ingeniería; la máquina de estados nunca necesita leerlos para decidir una
transición, solo el humano que audita.

## Lease y release (§26-28, §86)

- **Seguro de reclamar** (`ProductionJob::isSafeToRelease()`): solo
  `assigned`/`preparing` — nada físico ha pasado todavía.
- **Nunca se reclama automáticamente** desde `engraving_front` en
  adelante — hay evidencia física real en la placa.
- **Renovación de lease**: cada llamada exitosa de un device a *cualquier*
  endpoint de producción (no solo `claim`) renueva `lease_expires_at`
  (`App\Actions\Production\ProductionJobAction::transition()`). Esto
  resuelve el caso "un grabado de 2 minutos pierde el job por lease" sin
  necesitar un endpoint de renovación aparte: en la práctica, una vez que
  el job entra a `engraving_front` el lease deja de importar del todo
  (regla anterior), así que la renovación solo protege la ventana
  `assigned`/`preparing`, que es exactamente donde sí debe poder
  reclamarse si el device desaparece.
- **`POST .../release`**: ahora expuesto (§26). Solo válido desde
  `assigned`/`preparing` — la propia `ProductionJobStateMachine` no tiene
  destino `queued` desde ningún estado de grabado, así que falla con
  `INVALID_PRODUCTION_TRANSITION` por construcción si se intenta después.

## Artifact inmutable (§11-25, §61-64)

`App\Models\ProductionArtifact`: una fila por `ProductionJob` (frente +
reverso juntos — se generan en una sola operación atómica, nunca parcial),
nunca mutada después de `generated_at`. Campos: `production_job_id`,
`plate_id`, `plate_template_version_id`, `renderer_version`, `format`,
`front_storage_path`/`front_sha256`, `back_storage_path`/`back_sha256`,
`width_mm`/`height_mm`, `back_transform`, `metadata`, `generated_at`.

- **Cuándo se genera**: en el momento del `claim()` (device) o dentro de
  `StartProductionPreparation` (web manual, que no pasa por `claim`) — ver
  el diagrama de flujo §31: `claim → artifact congelado → prepare`.
  `App\Services\Production\ProductionArtifactService::ensureGenerated()`
  es idempotente: un job que ya tiene artifact simplemente lo devuelve,
  nunca vuelve a renderizar.
- **Mismo renderer que el fallback manual**: `ProductionArtifactService`
  llama exactamente a `App\Services\PlateExportService` — el mismo
  servicio que ya usa "Descargar para láser". Nunca hay un segundo cálculo
  de posiciones (§63 — un solo modo de producción, no dos diseños).
- **Texto como trazos**: el artifact automático se genera con
  `textAsPaths: true` (el fix de acentos/ñ de `docs/plate-production.md`
  §9 ya está resuelto y probado) — un device nunca debe depender de una
  fuente instalada. La vista previa web sigue mostrando `<text>` normal,
  sin cambios.
- **Storage**: disco `local` (privado, nunca URL pública — configurable en
  `config('finisher.production_artifact_disk')`), en
  `production/artifacts/{job_id}/{front,back}.svg`. Solo accesible vía el
  endpoint autenticado y verificado por ownership.
- **Hash**: SHA-256 calculado del contenido exacto que se persiste,
  guardado en la misma fila — nunca recalculado en cada descarga.
- **Reimpresión** (§49-50): un nuevo `ProductionJob` (mismo `Plate`, mismo
  `legacy_code_id`) siempre implica un nuevo `ProductionArtifact` — nunca
  se sobreescribe el anterior. Si no se pidió refrescar el snapshot, el
  nuevo artifact usa exactamente el mismo `plate_template_version_id` y el
  mismo snapshot congelado en `Plate` — mismo input, mismo output.

## Verificación de QR (§39-42, §98)

`App\Support\Production\LegacyQrVerifier::matches()` nunca acepta un
booleano — compara el valor decodificado (URL completa `.../l/{code}` o
código puro) contra `Plate.legacyCode.code`. Un QR incorrecto:

- **no** transiciona el job (se queda en `verifying_qr`).
- **no** marca `qr_verified_at`.
- responde `422 QR_VERIFICATION_FAILED` — nunca revela cuál era el código
  esperado.
- guarda `qr_decoded_value` de todos modos (para poder auditar qué se
  escaneó realmente, sin exponerlo en la respuesta de error).

## Fallo y recuperación (§51-53, §87-90)

Un `ProductionJob` fallido **nunca vuelve a `queued` solo**. Los
timestamps de evidencia física (`front_engraved_at`, `back_engraved_at`)
**nunca se limpian** al fallar — una transacción de base de datos no puede
deshacer metal ya grabado. Recuperarse significa una decisión humana
explícita: reimpresión (nuevo job, nuevo artifact) — el retry automático
queda fuera de Slice 2 (ver Deuda).

`attempts` se incrementa solo cuando el fallo ocurrió **después** de que
`front_started_at` ya existía — es decir, cuando de verdad hubo un intento
físico, no cuando falló durante la preparación.

## Regla de irreversibilidad física

Una vez que una cara terminó de grabarse (`front_engraved_at`/
`back_engraved_at` con valor), esa evidencia es permanente. Ningún flujo
normal de la UI la borra — ni un fallo posterior, ni una cancelación (que
de hecho ya es imposible después de `engraving_front`, por construcción de
`ProductionJobStateMachine`). Esta es la razón de fondo detrás de casi
todas las reglas de este documento: el software puede deshacer una fila de
base de datos; no puede deshacer un láser.

## Filtro por máquina (§59)

`production_jobs.machine_profile_id` (nullable, aditivo). Un job sin
perfil es genérico — cualquier estación lo ve. Un job con perfil solo lo
ve un device con el mismo `machine_profile_id`. Deliberadamente simple:
sin reglas de "compatibilidad" más allá de igualdad exacta.

## Lo que Slice 2 NO hace todavía

- Ningún driver real (Cloudray/BSL/JCZ/EZCAD/LightBurn) — el backend sigue
  sin hablarle al láser; termina en el archivo de producción congelado.
- Ninguna app de escritorio real — el contrato se prueba con
  `php artisan finisher:simulate-station`.
- `RetryProductionJob` explícito — hoy "reintentar" es, en la práctica,
  una reimpresión manual desde `/admin/plates/{id}`.
- WebSocket/Reverb para el kanban — sigue siendo polling.
- Compatibilidad de máquina más allá de igualdad exacta de
  `machine_profile_id`.
