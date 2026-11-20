# Módulos del modelo anterior — plan de deprecación (2026-10-27)

Contexto: el negocio pasó de "placa grabada en el evento" (Plate Studio,
producción frente/reverso, estaciones) a **Commerce + Product + Activation
OS** para el **Finisher Legacy Tag** terminado de fábrica
(`docs/architecture/commerce-os.md`). **En esta entrega no se borró nada**:
todas las rutas, tablas y pruebas siguen activas. En la navegación, estos
módulos viven en **Operación (histórico)** (colapsada al final del sidebar,
solo visible con sus permisos).

Cómo se evaluó: rutas (`routes/web.php`, `routes/api.php`,
`routes/web/nfc.php`), migraciones (FKs con `constrained()` hacia la tabla),
uso en `app/` (archivos que referencian el modelo) y pruebas en
`tests/Feature/*`. Cifras aproximadas a la fecha.

Acciones: **conservar** (sigue siendo parte del producto), **adaptar** (se
reutiliza para tags), **legacy-oculto** (funciona, sin promoción; solo en
Operación histórico), **retirar a futuro** (candidato a borrar cuando no
haya datos/clientes que dependan; requiere su propia migración y plan).

| Módulo | Rutas / superficie | Datos (tablas, FKs entrantes) | Uso / pruebas | Acción | Motivo |
|---|---|---|---|---|---|
| **Plate Studio** (plantillas y versiones de placa) | `/admin/plate-studio/*` (`platetemplates.view/manage`), preview, test-export, tarjeta de calibración | `plate_templates` (2 FKs entrantes), `plate_template_versions` (3) — `plates` y `production_jobs` guardan el snapshot de versión | ~28 archivos en `app/`; `PlateStudioTest`, `PlateCalibrationCardTest`, `PlateTextAsPathsTest` | legacy-oculto | El tag sale terminado de fábrica: no hay diseño por participante. Las placas existentes dependen de su versión (FK + snapshot), por eso no puede borrarse mientras existan `plates`. |
| **Production Jobs** (cola de grabado) | `/production`, `/production/jobs/{job}/*` (preparar, frente, voltear, reverso, entregar, fallar, cancelar) | `production_jobs` (1 FK entrante) ← `plates` | ~50 archivos en `app/`; `tests/Feature/Production/*` (~36 archivos mencionan producción) | legacy-oculto | Sin producción en sitio para tags. Mantiene la trazabilidad de placas ya vendidas y entregadas. |
| **Devices / Device API** (estaciones de grabado, emparejamiento, tokens con abilities) | `/admin/production-devices` (`productiondevices.*`), API `devices/*` y `devices/production/jobs/*` (`ability:production:*`, `device.idempotent`) | `production_devices` (3 FKs entrantes), `device_pairing_requests`, `device_idempotency_keys` | `tests/Feature/Devices/*`, `ProductionDeviceAdminTest` | retirar a futuro | Solo existe para manejar grabadoras láser en evento. Antes de retirar: revocar tokens activos y confirmar que ninguna estación sigue emparejada. No confundir con `me/push-devices` (notificaciones de la app, se conserva). |
| **Flujo de grabado frente/reverso** (front start/complete, flip confirm, back start/complete) | Rutas web `production.jobs.*` + equivalentes Device API | Estados en `production_jobs` / `plates` (`PlateBackTransform`, `PlateStatus`) | `PlateTwoFaceProductionTest`, `PlateBackTransformTest` | retirar a futuro | Específico de la placa de dos caras. Se conserva mientras haya placas en estados intermedios; después se puede congelar en solo lectura. |
| **QR verify** (verificación del QR grabado) | `POST /production/jobs/{job}/qr/verify`, `App\Actions\Production\VerifyProductionQr`, `LegacyQrVerifier` | Resultado en `production_jobs` | Pruebas de producción | legacy-oculto | El QR del tag viene impreso de fábrica (`/t/{code}?src=qr`); el control de calidad pasa al lote (`ReceiveLot` marca defectuosos). |
| **Importación de participantes** | `/imports/*` (`imports.manage`) | `event_imports` (1 FK entrante), `event_import_errors` | `tests/Feature/Imports/*` (~6) | conservar | Sigue alimentando eventos, resultados y la historia del atleta (activar un tag puede ligarse a una participación real). Solo se movió del menú Eventos a Operación. |
| **Operator / Event OS** | `/operator/*` (`operator.access`) — búsqueda de participante, placa integrada/rápida | Lee `event_participants`, crea `plates` | `tests/Feature/Operator/*` (~19 archivos lo mencionan) | legacy-oculto | Pantalla de mostrador para generar placas en el evento. La venta en evento hoy es **Venta rápida** (`/admin/venta-rapida`). |
| **Legacy Codes** (`/l/{code}`) | Público `/l/{code}`, `/l/{code}/qr.svg`, claim; admin `/admin/legacy-codes` (`legacycodes.view`) | `legacy_codes` (1 FK entrante), 1:1 con `plates` | ~22 archivos en `app/`; `tests/Feature/LegacyCode/*` (~20) | conservar (público) / legacy-oculto (admin) | Las placas ya entregadas llevan `/l/{code}` grabado: **la URL pública nunca puede romperse**. Los tags nuevos usan `/t/{code}` (`legacy_tags.public_code`); no se emiten Legacy Codes nuevos para tags. |
| **Machine profiles** | `/admin/machine-profiles` (`platetemplates.*`) | `machine_profiles` (3 FKs entrantes: plantillas/jobs/estaciones) | Pruebas de Plate Studio / producción | retirar a futuro | Etiqueta de flujo de grabadora ("Fiber 30W — LightBurn"). Sin uso para tags; se retira junto con Devices y Production Jobs. |
| **Preventas** (Legacy Plate entitlements) | `/admin/legacy-plates/presales`, link a participante (`legacyplates.manage`); producción `/admin/legacy-plates/production` | `legacy_plate_entitlements` (sin FKs entrantes) ← pedidos de tipo `legacy_plate` | `PreregistrationLegacyPlateTest`, `LegacyPlateOpsWebTest`, `tests/Feature/LegacyPlates/*` | legacy-oculto | Derechos de placa ya pagados que aún deben producirse/entregarse. Retirar cuando no queden preventas abiertas; los pedidos históricos se conservan. |
| **Preparación NFC** (`/admin/legacy-nfc/preparacion`, etiquetas, productos conectados) | `routes/web/nfc.php` (`production.access`, `nfc.view`) | `nfc_tags` (2 FKs entrantes, incl. `legacy_tags.nfc_tag_id`), `athlete_owned_products` | `tests/Feature/Nfc/*` (~21) | adaptar | Preparación y entrega de placas → histórico (Operación). **Etiquetas NFC** y **Productos conectados** siguen en Legacy: un `LegacyTag` puede enlazar su chip y la activación crea un `AthleteOwnedProduct` (Mi equipo / `/api/v1/me/gear`). |

## Qué NO se debe borrar

- URLs públicas grabadas en objetos físicos: `/l/{code}` (placas),
  `/n/{token}` (chips NFC), `/t/{code}` (tags).
- `plates`, `legacy_codes`, `nfc_tags`, `athlete_owned_products`: sostienen
  historias de atletas ya entregadas.

## Orden sugerido para retirar (fuera de esta entrega)

1. Confirmar 0 `production_jobs` abiertos y 0 preventas abiertas.
2. Revocar tokens de `production_devices`; desactivar la Device API
   (responder 410) un ciclo antes de borrar.
3. Congelar Plate Studio / Machine profiles en solo lectura.
4. Migraciones de borrado reversibles por módulo, cada una con sus pruebas
   eliminadas o reescritas en el mismo PR.
