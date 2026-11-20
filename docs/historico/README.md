# Documentos históricos

Los documentos de esta carpeta **son históricos y no describen decisiones
vigentes**. Se conservan porque explican tablas, modelos y datos antiguos que
siguen en la base (placas, Legacy Codes, pagos Stripe) y porque las
migraciones ya aplicadas los citan en sus comentarios.

Producto vigente: **Finisher Legacy Tag NFC** con activación digital (sin
placas metálicas, sin QR), OpenPay para bienes físicos y compras dentro de la
app (Apple/Google) para ampliaciones digitales. Documentación vigente:

- [`README.md`](../../README.md) — qué es, instalación y comandos.
- [`docs/FUNCIONALIDADES.md`](../FUNCIONALIDADES.md) — inventario de funciones.
- [`docs/ARQUITECTURA.md`](../ARQUITECTURA.md) — arquitectura.
- [`docs/DESPLIEGUE.md`](../DESPLIEGUE.md), [`docs/PUBLICACION_TIENDAS.md`](../PUBLICACION_TIENDAS.md), [`docs/PENDIENTES_EXTERNOS.md`](../PENDIENTES_EXTERNOS.md).

## Ubicación anterior → actual

Si un comentario de código o una migración apunta a la ruta anterior, el
documento está aquí:

| Antes | Ahora | Por qué es histórico |
|---|---|---|
| `docs/plate-production.md` | `historico/plate-production.md` | Producción de placas grabadas retirada el 2026-11-11 (`docs/renovacion/retiro-modulos.md`). |
| `docs/architecture/legacy-plate-v2.md` | `historico/legacy-plate-v2.md` | Placa metálica (modelo v2). |
| `docs/architecture/legacy-plate-v3.md` | `historico/legacy-plate-v3.md` | Placa metálica V3 (70 × 45, money clip). |
| `docs/architecture/deprecation.md` | `historico/deprecation.md` | Plan de deprecación de 2026-10-27, ya ejecutado (`retiro-modulos.md`). |
| `docs/architecture/light-redesign.md` | `historico/light-redesign.md` | Bitácora del rediseño de octubre (incluye placa V3). |
| `docs/architecture/commerce.md` | `historico/commerce.md` | Comercio Fase 1 con Stripe PaymentSheet y entitlements de placa; vigente: `docs/architecture/commerce-os.md` + `docs/renovacion/pagos.md`. |
| `docs/desktop/technology-decision.md` | `historico/desktop-technology-decision.md` | Estación de escritorio para grabado, nunca construida. |
| `docs/device-api/v1.md` | `historico/device-api-v1.md` | Device API de producción láser (responde 404 desde 2026-11-11). |
| `docs/event-ops/README.md` | `historico/event-ops.md` | Operación de evento para placas. |
| `docs/adr/0002`, `0003`, `0006`, `0007` | `historico/adr/` | ADRs de producción física / Event Ops / Desktop. |
| `docs/legacy-code-lifecycle.md` | `historico/legacy-code-lifecycle.md` | Legacy Code / QR de placas (las URLs `/l/{code}` siguen resolviendo por compatibilidad). |
| `docs/MODULES.md`, `docs/product/module-audit.md` | `historico/MODULES.md`, `historico/module-audit.md` | Mapa de navegación de octubre (incluía «Operación» de placas); vigente: `resources/js/config/navigation.ts` y `docs/FUNCIONALIDADES.md`. |
| `docs/design/FINISHER_UI_SYSTEM.md` | `historico/FINISHER_UI_SYSTEM.md` | Ronda visual con motivos de placa; la identidad vigente es solo clara (Blanco Hueso, Negro Carbono, Oro). |
| `docs/renovacion/auditoria-inicial.md`, `checklist-{android,ios,web}.md` | `historico/renovacion/` | Auditoría y checklists del 2026-11-10 (lectura de código, antes de IAP, sin carpeta `ios/`, con Stripe). Las capturas y el PDF de estrategia siguen en `docs/renovacion/`. |
| `docs/renovacion/dependencias-externas.md` | `historico/renovacion/dependencias-externas.md` | Reemplazado por `docs/PENDIENTES_EXTERNOS.md` (este aún listaba Stripe para la app). |
