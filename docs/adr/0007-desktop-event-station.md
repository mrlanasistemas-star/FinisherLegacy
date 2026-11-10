# ADR 0007 — Desktop Event Station

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

Estado: Aceptado (decisión de tecnología PENDIENTE de validación de hardware)
Fecha: 2026-08-23
Contexto previo: ADR 0002 (Device API, Slice 1), ADR 0003 (producción,
Slice 2)

## Decisión

El backend sigue siendo la única fuente de verdad — `ProductionJobStatus`
(ADR 0003) nunca se sustituye por el estado local del Desktop. Este ADR
define el **contrato** que un Finisher Event Desktop real deberá cumplir;
no se construye una app instalable en este slice. Lo que sí se entrega:
un cliente de referencia (`php artisan finisher:simulate-station`,
existente desde Slice 2, sin cambios) y un driver de láser simulado
(`App\Contracts\Production\LaserDriver` + `MockLaserDriver`) para que el
contrato de driver quede probado en código real, no solo documentado.

## 1. Responsabilidades — quién manda en qué

| | Backend | Desktop |
|---|---|---|
| Estado del `ProductionJob` | ✅ único dueño | solo lee/propone transiciones vía Device API |
| Estado de UI local (`IDLE`, `ENGRAVING_FRONT`, …) | no le importa | ✅ dueño, nunca sustituye al de arriba |
| Decisión física (cuándo empezar a grabar) | nunca decide | ✅ el humano confirma en el Desktop |
| Parámetros del láser (potencia, frecuencia, velocidad) | ❌ nunca los manda | ✅ perfil calibrado localmente |
| Verificación de artifact (SHA-256) | genera el hash | ✅ verifica antes de grabar |

## 2. Station state (local, UI-only)

```
UNPAIRED → IDLE → JOB_LOADED → READY_FRONT → ENGRAVING_FRONT →
AWAITING_FLIP → READY_BACK → ENGRAVING_BACK → VERIFYING_QR → COMPLETED
                                                                  │
                                                                ERROR (desde cualquier estado)
```

Cada transición local corresponde 1:1 a una llamada al Device API — el
Desktop nunca avanza su UI sin que el backend confirme la transición
correspondiente de `ProductionJobStatus` primero (excepción: modo
offline, ver §6).

## 3. Driver contract

```php
interface LaserDriver
{
    public function connect(): DriverConnectionResult;
    public function disconnect(): void;
    public function getStatus(): DriverStatus;
    public function isReady(): bool;
    public function frame(EngraveJob $job): void;      // opcional — solo si supportsFraming()
    public function engrave(EngraveJob $job): EngraveResult;
    public function pause(): void;
    public function resume(): void;
    public function cancel(): void;
    public function capabilities(): DriverCapabilities; // framing, pause/resume, etc.
}
```

Capability-based: `pause()`/`resume()`/`frame()` no son obligatorios —
`DriverCapabilities` declara qué soporta cada implementación, y el
Desktop pregunta antes de ofrecer el botón correspondiente. Ningún driver
recibe `laserPower`/`frequency`/`speed` del backend — esos viven en el
perfil de máquina calibrado localmente (§116 del prompt: la máquina
decide, nunca el backend).

`App\Services\Production\Drivers\MockLaserDriver` (implementado en este
slice, PHP, usado por `finisher:simulate-event-day`): simula latencia de
conexión, duración de grabado configurable
(`frontDurationMs`/`backDurationMs`), y fallas configurables
(`INTERLOCK_OPEN`, `DEVICE_NOT_READY`, `ENGRAVING_FAILED`) — nunca un
método para saltarse una protección, solo para *simular* que se activó.

## 4. Por qué el "cliente de referencia" vive en Laravel y no en un proyecto Node/Electron separado

Sin hardware real ni SDK todavía, un prototipo de Desktop instalable
sería especulativo — construiría UI y empaquetado sobre una integración
con el láser que no existe. `finisher:simulate-station` (Slice 2) y el
nuevo `LaserDriver`/`MockLaserDriver` prueban exactamente el contrato que
un Desktop real tendrá que implementar (llamadas HTTP al Device API +
abstracción de driver), sin la inversión de un empaquetado real que
habría que rehacer en cuanto llegue el hardware. Cuando el hardware
llegue (Slice 6, ADR futuro), el contrato aquí definido es lo que un
Desktop real — en la tecnología que se elija — deberá satisfacer.

## 5. Comparación de tecnología para el Desktop real

| | .NET (WPF/WinUI) | Tauri | Electron |
|---|---|---|---|
| Interop con DLL/SDK/COM de fabricante de láser | ✅ nativo (P/Invoke, COM directo) | ⚠️ vía Rust FFI, más fricción | ⚠️ vía addon nativo Node, más fricción aún |
| USB/controladora | ✅ WinUSB/driver .NET maduro | ⚠️ posible vía crates, menos probado en este dominio | ⚠️ igual, vía addon nativo |
| Auto-update | ✅ maduro (Squirrel, MSIX) | ✅ maduro (tauri-updater) | ✅ maduro (electron-updater) |
| UI | WPF/WinUI, curva de aprendizaje si el equipo es web-first | Web (Vue/React) + shell Rust | Web (Vue/React) + shell Node |
| Tamaño de instalador | Medio | Pequeño (~10-20MB) | Grande (~150MB+, Chromium embebido) |
| Seguridad de superficie | Buena, .NET moderno | Buena, Rust memory-safe | Aceptable, más superficie (Node completo) |
| Developer experience del equipo actual (Vue/Inertia) | Baja — stack nuevo | Alta — Vue reusable | Alta — Vue reusable |

**Recomendación provisional: .NET**, específicamente por la columna que
más importa para este dominio — interoperabilidad con SDK/DLL/COM de
fabricante de láser, que suele publicarse en C/C++/.NET, no en
JavaScript/Rust. Tauri es la alternativa razonable si el SDK real resulta
tener bindings limpios en C que Rust FFI pueda envolver sin fricción
mayor que .NET.

**FINAL DECISION PENDING HARDWARE/SDK VALIDATION** — esta tabla se
revisita en cuanto exista una máquina física y su SDK; ninguna decisión
aquí es definitiva hasta entonces (§21-22, §122 del prompt original).

## 6. Offline strategy

Si el Desktop ya tiene un job asignado con sus artifacts descargados y
verificados (SHA-256), y pierde conectividad: puede **continuar
físicamente** ese job (frente/reverso/QR son pasos locales una vez que el
material y el diseño ya están en la máquina). Lo que NO puede hacer sin
conexión: reclamar un job nuevo (§41, evita que dos estaciones offline
reclamen el mismo job al reconectar) ni marcar `delivered` de forma
definitiva sin que el backend lo confirme.

## 7. Local outbox

Transiciones que ocurren mientras no hay conexión
(`front_completed`, `flip_confirmed`, `back_completed`, `qr_verified`) se
guardan en una cola local (outbox) con su timestamp físico real. Al
reconectar, el Desktop reproduce la cola en orden contra el Device API —
cada llamada es la misma que ya existe (`POST .../front/complete`, etc.),
así que la idempotencia ya construida en Slice 2
(`App\Actions\Production\*` son operaciones de transición de estado,
rechazan una transición ya aplicada) hace que reproducir la cola dos
veces por error de red sea seguro, sin necesidad de un mecanismo de
deduplicación adicional en el backend.

No hay Desktop real todavía para implementar el outbox — queda como
contrato probado solo a nivel de diseño en este ADR; el día que exista un
Desktop de verdad, esta sección es su especificación de partida.

## 8. Artifact cache

El Desktop cachea localmente: `job_id`, artifact de frente, artifact de
reverso, y sus hashes SHA-256 — exactamente lo que
`finisher:simulate-station` ya descarga y verifica hoy (`downloadAndVerify()`).
Nada nuevo del lado backend: los artifacts ya eran inmutables/congelados
desde Slice 2 (ADR 0003 §Frozen artifacts).

## 9. Seguridad del token local

El token de dispositivo (Sanctum) nunca debe vivir en un JSON plano junto
al ejecutable — debe ir al almacén de credenciales del SO (Windows
Credential Manager / DPAPI). No implementado aquí (no hay Desktop real
todavía) — queda como requisito no negociable para cuando se construya.

## 10. Candidatos de driver futuros (documentados, no implementados)

`BslLaserDriver`, `JczLaserDriver`, `EzCadBridgeDriver`,
`LightBurnBridgeDriver` — nombres provisionales para cuando exista SDK
real; ninguno tiene código todavía (§29, nunca inventar llamadas a un SDK
que no tenemos).

## 11. Lo que Slice 5 NO hace

- Ningún SDK real de láser conectado.
- Ninguna app Desktop instalable (Electron/Tauri/.NET) — solo el
  contrato + el cliente CLI existente.
- Ningún outbox real (no hay proceso de larga duración donde
  implementarlo todavía).
- Ninguna decisión final de tecnología — sigue pendiente de hardware.
