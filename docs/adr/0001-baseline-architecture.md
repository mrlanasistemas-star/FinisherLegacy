# ADR 0001 — Radiografía de la arquitectura actual

Estado: Aceptado (documenta lo que YA existe, no propone cambios)
Fecha: 2026-08-20

## Propósito

Antes de tocar nada para el Device API / LaserDriver (ver ADR 0002), este
documento deja constancia de qué invariantes de negocio ya están
implementadas, dónde viven, y qué prueba lo confirma — para que la
rearquitectura mayor migre verticalmente sobre una base conocida en vez de
adivinar el estado del repo.

## Invariantes pedidas vs. estado real

| Invariante | Estado | Evidencia |
|---|---|---|
| Una sola lógica de negocio | ✅ Ya implementado | `Services`/`Actions` se comparten entre `Http/Controllers/*` (Inertia) y `Http/Controllers/Api/V1/*` (Sanctum). `docs/api/v1.md`: "Cada endpoint reutiliza los mismos Services/Actions/Policies/Form Requests que la web Inertia — nunca hay lógica de negocio duplicada". |
| Múltiples transports, no un controller que ramifica por `expectsJson()` | ✅ Ya implementado | Dos árboles de controllers separados por transporte: `App\Http\Controllers\*` (web/Inertia) y `App\Http\Controllers\Api\V1\*` (JSON/Sanctum). Ninguno inspecciona el tipo de request para decidir el formato de respuesta. |
| Athlete global separado de participación | ✅ Ya implementado | `AthleteProfile` (identidad global: username, bio, visibilidad) vs. `EventParticipant` (participación en una edición: dorsal, carrera, resultado) son modelos y tablas distintas. Un usuario puede tener 0 o 1 `AthleteProfile` y N `EventParticipant` a través de reclamos de Legacy Code. |
| ¿Cómo reclamo un código? | ✅ Una sola implementación | `App\Services\ClaimLegacyCodeService::claim()` — transaccional, con `lockForUpdate()`, usada tanto por `LegacyCodeController` web como `Api\V1\LegacyCodeController`. |
| ¿Cómo genero una placa? | ✅ Una sola implementación | `App\Services\PlateGenerationService` — único lugar que llama `Plate::create()`. Verificado por `tests/Feature/Operator/IntegratedSnapshotTest.php`, que recorre `app/` y `database/seeders/` en cada corrida para confirmarlo. |
| ¿Cómo congelo los datos (snapshot)? | ✅ Ya implementado | `App\Services\PlateSnapshotBuilder` — el snapshot nunca cambia solo; una reimpresión explícita es la única vía de actualización (`docs/historico/plate-production.md` §7, §12). |
| ¿Cómo genero Legacy Code? | ✅ Ya implementado | `App\Support\CodeGenerator::unique('FL', ...)`. El código es permanente, sin expiración (`docs/historico/legacy-code-lifecycle.md`). |
| ¿Cómo cambio un Production Job? | ⚠️ Implementado, pero solo para humanos | `App\Services\ProductionService` (transición de `PlateStatus` + checklist frente/reverso/QR). Hoy el único actor que llama esto es un humano desde `/production` (kanban Inertia) — no existe ningún actor "dispositivo". |
| ¿Cómo identifico un atleta? | ✅ Ya implementado | `User` (auth) → `AthleteProfile` (perfil público, 0..1) → `EventParticipant` (participación, N, vinculada solo tras reclamar un Legacy Code). |
| ¿Cómo sincronizo un participante? | ⚠️ Un solo mecanismo, no es un adapter genérico | `App\Imports\ParticipantsImport` vía `/imports` (CSV/XLSX subido a mano). No existe una interfaz de "integration adapter" para sistemas de cronometraje/registro externos — hoy hay un solo camino de entrada, no varios adapters intercambiables. |
| Integraciones por adapters | ❌ No existe todavía | No hay ningún directorio `Integrations`/`Adapters`. Fuera de alcance de este ADR (el pedido explícito es empezar por el núcleo de producción láser) — queda anotado para un ADR futuro si se necesitan más fuentes de datos de participantes/resultados. |
| Producción mediante devices | ❌ No existe | No hay ningún concepto de "dispositivo" en el dominio: ni modelo, ni autenticación, ni namespace de rutas. |
| Hardware mediante driver abstraction | ❌ Deliberadamente no implementado | `App\Models\MachineProfile` es, por diseño documentado, **una etiqueta de flujo, no un driver**: "Finisher Legacy nunca controla el láser directamente" (`docs/historico/plate-production.md` §10). §16 lista "Integración directa con el software/driver de la máquina láser" como fuera de alcance de la fase actual, explícitamente. |
| Fallback manual | ✅ Ya implementado — y es hoy el ÚNICO camino | `App\Services\PlateExportService` + `GdPlateRenderer` (PNG) + `PdfExportService` (PDF) + SVG — todos parten del mismo `PlateTemplateRenderService::resolveElements()`, nunca recalculan posiciones por separado. Descarga individual, o por lote (`plates.export-batch`, tope `config('plate-studio.batch_export_limit')`). |

## El hallazgo central

La arquitectura actual **no tiene una laguna accidental** en torno al láser —
tiene una **decisión de producto ya tomada, documentada y probada**: el
grabado termina en un archivo de exportación (SVG/PNG/PDF/ZIP) que un
operador humano importa a mano en LightBurn. `docs/historico/plate-production.md` §10
y §16 lo dejan explícito, con una razón de fondo real: potencia/velocidad/
frecuencia del láser dependen del material y se calibran físicamente, nunca
se envían como default automático — por eso `MachineProfile` fue diseñado
sin esos campos a propósito.

El pedido de esta rearquitectura invierte esa decisión: automatizar el
grabado (front → flip → back → validar QR) vía un Device API + driver de
hardware, dejando la descarga manual de SVG como **fallback**, no como único
camino. Ver ADR 0002 para la resolución de esta contradicción, la frontera
de alcance de este repo, y el plan de slices.

## Estructura de código relevante para el núcleo de producción

```
app/Http/Controllers/ProductionController.php      Kanban web (Inertia), humano
app/Http/Controllers/OperatorController.php         Generación de placas, humano
app/Http/Controllers/Admin/PlateController.php      Exportación manual (fallback)
app/Http/Controllers/Admin/MachineProfileController.php  CRUD de la etiqueta de flujo
app/Services/ProductionService.php                  Máquina de transiciones de Plate/ProductionJob
app/Services/PlateGenerationService.php              Único creador de Plate
app/Services/PlateExportService.php                  SVG/PNG/PDF/ZIP (fallback manual)
app/Models/ProductionJob.php                         Checklist front/back/qr, un job por intento de grabado
app/Models/MachineProfile.php                        Etiqueta de flujo, no driver
app/Enums/PlateStatus.php                            draft…queued…processing…ready…delivered…reprint
app/Enums/ProductionJobStatus.php                     queued/processing/completed/failed/cancelled
routes/web.php                                        `/production`, `/operator`, `/admin/plates/*`
routes/api.php                                        `/api/v1/*` — sin nada de producción hoy
```

No existe todavía `routes/device.php`, ningún controller bajo
`Http/Controllers/Device`, ni ninguna tabla `devices`.
