# Desktop Event Station — decisión de tecnología

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

Estado: **PENDING HARDWARE/SDK VALIDATION** — ver
`docs/adr/0007-desktop-event-station.md` para el contrato completo
(driver, estado local, offline/outbox). Este documento es solo la
comparación de tecnología y la recomendación provisional.

## Requisitos del escenario

- Windows (estación fija en el evento, junto a la máquina láser).
- Posible interoperabilidad con DLL/SDK/COM del fabricante de la
  controladora del láser — desconocido hasta tener hardware real.
- USB/driver de la controladora.
- Auto-actualización (el equipo de operaciones no debe instalar manual).
- UI reutilizable por el equipo actual (Vue/Inertia) si es razonable.
- Seguridad: token de dispositivo en almacén de credenciales del SO, nunca
  plano.
- Tamaño de instalador razonable para distribuir por evento.

## Comparación

| Criterio | .NET (WPF/WinUI) | Tauri | Electron |
|---|---|---|---|
| Interop DLL/SDK/COM de fabricante | ✅ nativo | ⚠️ vía Rust FFI | ⚠️ vía addon nativo Node |
| USB/controladora | ✅ maduro en Windows | ⚠️ depende de crates disponibles | ⚠️ igual, vía addon nativo |
| Auto-update | ✅ maduro | ✅ maduro | ✅ maduro |
| UI con stack actual (Vue) | ❌ requiere WPF/WinUI aparte | ✅ reutilizable | ✅ reutilizable |
| Tamaño de instalador | Medio | Pequeño (~10-20MB) | Grande (150MB+, Chromium) |
| Superficie de seguridad | Buena | Buena (Rust memory-safe) | Aceptable (Node completo) |
| Developer experience del equipo actual | Baja (stack nuevo) | Alta | Alta |

## Recomendación provisional

**.NET**, por la fila que más pesa en este dominio: interoperabilidad con
SDK/DLL/COM de fabricante de láser, que casi siempre se publica en
C/C++/.NET — no en JavaScript ni Rust. Si el SDK real resulta tener
bindings limpios en C, **Tauri** es la alternativa razonable (reutiliza
Vue, evita el salto de stack a WPF/WinUI) sin perder demasiado en
interoperabilidad.

Electron queda en tercer lugar: mismo problema de interop que Tauri
(addon nativo) sin su ventaja de tamaño/seguridad — la única razón para
elegirlo sería developer experience pura, que Tauri ya cubre igual de
bien.

## Decisión final

**Pendiente** — depende del SDK/controladora reales, que no existen
todavía en este slice. Esta tabla se revisita en cuanto llegue hardware
(Slice 6+); ver `docs/desktop/hardware-checklist.md` (a crear cuando
llegue la máquina) para qué documentar del fabricante/modelo/SDK.
