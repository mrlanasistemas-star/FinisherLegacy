# ADR 0002 — Device API para producción láser automatizada

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

Estado: Propuesto — pendiente de aprobación del Slice 1
Fecha: 2026-08-20
Contexto previo: ADR 0001 (baseline)

## Decisión que se está tomando

Invertir la operación normal de producción de:

```
buscar dorsal → generar placa → operador descarga SVG →
importa a mano en LightBurn → graba → voltea → graba → escanea QR → Lista
```

a:

```
buscar dorsal → generar placa → cola de producción →
estación toma job → GRABAR FRENTE → voltear → GRABAR REVERSO →
validar QR → Lista
```

manteniendo la descarga manual de SVG/PNG/PDF (ya implementada, ver ADR 0001)
como **fallback explícito** si el dispositivo/driver falla, nunca la
eliminamos.

Esto reversa una decisión previa y documentada (`docs/plate-production.md`
§10, §16) de no controlar el láser directamente. La razón para reversarla es
un cambio de producto (llevar el piloto a operación con hardware real), no
un defecto de la decisión anterior — la decisión anterior fue correcta para
su fase.

## Frontera de alcance (confirmada con el usuario)

Este repositorio es el backend Laravel/Inertia. El **LaserDriver**
(Cloudray/BSL, EZCAD/JCZ, LightBurn bridge, Mock) vive en la **Finisher
Event Desktop app** — un cliente aparte que habla con el hardware por
USB/serial/SDK propietario, fuera de este repo y de este stack.

Lo que este repo construye:

- El **Device API**: autenticación de dispositivo, entrega de production
  jobs con instrucciones de render, y callbacks de checklist
  (front/back/qr).
- El **contrato** (`docs/device-api/v1.md`, análogo a `docs/api/v1.md`) que
  cualquier cliente desktop debe implementar.

Lo que este repo NO construye:

- El propio LaserDriver ni sus implementaciones concretas (EZCAD, LightBurn,
  etc.).
- Código de la Finisher Event Desktop app.

## Invariantes de seguridad que el contrato debe preservar

Del ADR 0001: potencia/velocidad/frecuencia del láser **nunca** se calculan
ni envían desde el backend — dependen del material y se calibran
físicamente por máquina (`docs/plate-production.md` §10). El Device API
sigue esa misma regla:

- El backend entrega **qué grabar** (vectores/SVG resueltos por el mismo
  `PlateTemplateRenderService` que ya usa el fallback manual — nunca un
  segundo cálculo de posiciones) y **datos físicos** (dimensiones en mm,
  `back_transform`). Nunca entrega potencia/velocidad/frecuencia ni ningún
  parámetro que pueda deshabilitar una protección de la máquina.
  Esos valores viven y se calibran en el driver/máquina, no en este backend.
- El backend nunca envía un comando de "ejecutar grabado ya" con parámetros
  de energía — solo expone instrucciones de render + metadata. El driver
  decide cómo grabarlas de forma segura sobre su hardware calibrado.
- Un dispositivo autenticado solo puede reclamar y reportar sobre los
  `ProductionJob` de su `MachineProfile`/edición asignada — nunca un listado
  global.

## Diseño del Device API (target, se implementa por slices)

### Identidad

Nuevo modelo `Device` (no reutiliza `User`): una estación física/instancia
de la Finisher Event Desktop app, vinculada a un `MachineProfile` existente
y opcionalmente a una `EventEdition` activa. Autenticación por token
Sanctum en un **guard propio** (`device`), separado del guard de atletas/
operadores — un token de dispositivo nunca debe poder llamar endpoints de
`/api/v1/*` (perfil, medallas, claim) y viceversa.

### Flujo

1. `POST /api/device/v1/auth/pair` — registra/autentica un dispositivo con
   un código de emparejamiento generado desde `/admin/machine-profiles`
   (humano). Devuelve un token de larga duración para ese dispositivo.
2. `GET /api/device/v1/jobs/next` — devuelve el siguiente `ProductionJob`
   en `queued` para el `MachineProfile` del dispositivo, con las
   instrucciones de render de ambas caras (reutilizando
   `PlateTemplateRenderService`) + `back_transform` + dimensiones físicas.
   No lo asigna todavía (idempotente, se puede pedir varias veces).
3. `POST /api/device/v1/jobs/{job}/claim` — reclama el job (lock optimista,
   mismo patrón `lockForUpdate()` que `ClaimLegacyCodeService`) para que dos
   estaciones no tomen el mismo trabajo.
4. `POST /api/device/v1/jobs/{job}/checklist` — reporta
   `front_engraved`/`back_engraved`/`qr_verified`, reutilizando
   `ProductionService::toggleChecklistItem()` — **nunca duplica** esa
   lógica; el toggle humano en `/production` sigue existiendo como
   corrección manual sobre el mismo dato.
5. `POST /api/device/v1/jobs/{job}/fail` — reporta un fallo físico (atasco,
   error de escaneo QR, etc.) → el job vuelve a `queued` o pasa a
   `cancelled` según el motivo; el operador puede recurrir al fallback
   manual (descarga SVG) para ese job específico sin bloquear la cola.

### Lo que no cambia

- `ProductionService::TRANSITIONS` y las reglas de negocio existentes del
  kanban siguen siendo la única máquina de estados — el Device API es un
  actor adicional que las invoca, no una segunda implementación.
- `PlateExportService` (SVG/PNG/PDF/ZIP) sigue existiendo sin cambios como
  fallback.
- El checklist de 3 marcas (front/back/qr) sigue siendo la condición para
  pasar a `ready`, ahora poblable por humano o por dispositivo.

## Plan de slices

Cada slice se implementa, se prueba, y se presenta para revisión antes de
continuar con el siguiente. Ninguno incluye commit/push.

1. **Slice 1 — Identidad de dispositivo** (el más chico, sin tocar producción
   todavía): migración `devices`, modelo `Device`, guard `device` +
   emparejamiento por token Sanctum, `Http/Controllers/Device/V1/AuthController`,
   `routes/device.php`. Tests: un dispositivo se autentica, un token de
   dispositivo no puede pegarle a `/api/v1/*` y un token de atleta no puede
   pegarle a `/api/device/v1/*`.
2. **Slice 2 — Entrega de jobs (solo lectura)**: `GET jobs/next` +
   `POST jobs/{job}/claim`, columna `production_jobs.device_id` +
   `claimed_at`. Sin callbacks todavía. Tests de condición de carrera sobre
   `claim`.
3. **Slice 3 — Checklist por dispositivo**: `POST jobs/{job}/checklist` +
   `POST jobs/{job}/fail`, reutilizando `ProductionService`. Tests de que el
   kanban humano y el Device API nunca diverjan en el mismo dato.
4. **Slice 4 — Contrato documentado**: `docs/device-api/v1.md` (igual
   formato que `docs/api/v1.md`) + actualizar `docs/plate-production.md`
   para reflejar que el grabado automatizado es ahora la operación normal y
   la descarga manual el fallback (§16 deja de listar el driver como "fuera
   de alcance").

No se toca `EventParticipant`, sincronización de participantes, ni
integraciones externas en ningún slice de este ADR — eso queda fuera,
según el alcance acordado (núcleo de producción láser primero).
