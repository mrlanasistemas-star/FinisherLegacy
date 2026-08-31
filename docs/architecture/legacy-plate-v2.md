# Legacy Plate v2 — grabado dinámico sobre pieza pre-manufacturada

Ver `docs/plate-production.md` para el pipeline histórico (PlateTemplate,
molde completo diseñado en Plate Studio). Este documento cubre **solo lo
nuevo**: el rediseño de producto donde la placa física llega ya fabricada
desde China y Finisher Legacy únicamente graba información variable.

## Por qué existen dos pipelines

Antes: cada placa se diseñaba completa (forma, relieves, decoración, QR,
todo) vía `PlateTemplate`/`PlateTemplateVersion`, renderizada por
`PlateTemplateRenderService`.

Ahora: el proveedor entrega la pieza física ya terminada — forma,
mecanismo de sujeción, relieves, marcas permanentes, iconografía del
modelo. Finisher Legacy **solo** coloca texto/QR dentro de una zona
delimitada.

Las placas históricas (`layout_type = legacy_template`) siguen
reproduciéndose exactamente igual — nada de `PlateTemplate` se tocó ni se
migró. Una placa nueva usa `layout_type = manufactured_dynamic`.

```
Plate
 ├─ layout_type = legacy_template        → plate_template_version_id (histórico, sin cambios)
 └─ layout_type = manufactured_dynamic   → legacy_plate_model_id (nuevo)
```

## Modelo de datos

**`LegacyPlateModel`** — una referencia física (`App\Models\LegacyPlateModel`):

- `name`/`slug`/`sku`/`description` — editables desde catálogo, nunca
  hardcodeados en reglas de negocio.
- `width_mm`/`height_mm` — dimensiones reales de la pieza.
- `engraving_area` (JSON `{x,y,width,height}`) — la única zona donde puede
  existir grabado, relativa a `width_mm`/`height_mm`.
- `preview_image_path` — foto del modelo físico para el admin.

**`LegacyPlateModelField`** — un campo dinámico dentro de `engraving_area`
(`App\Models\LegacyPlateModelField`):

- `field_key` (`App\Enums\LegacyPlateFieldKey`): `athlete_name`,
  `race_label`, `official_time`, `pace`, `qr` — lista cerrada, nunca
  decoración arbitraria.
- `x`/`y`/`width`/`height`/`font_size`/`alignment`/`max_chars`.
- `required`/`visible`/`sort_order`.

Seed inicial: `database/seeders/LegacyPlateModelSeeder.php` — dos modelos
("Núcleo Reveal", "Dial de Distancia"), nombres explícitamente
provisionales y editables.

## Snapshot físico

`Plate` (extendida, no reemplazada) gana:

- `legacy_plate_model_id` (nullable — null en toda placa histórica).
- `engraving_display_name` — snapshot editable por el operador, **nunca**
  muta `Athlete::full_name`. Ver "Name fitting" abajo.
- `layout_type` / `layout_version` — para reproducibilidad; el renderer
  de producción sabe exactamente qué esquema de layout usar sin adivinar.

Como con el pipeline histórico, `Plate::dynamic_fields` sigue siendo el
snapshot congelado de distancia/tiempos/posición al momento de generar —
si el resultado fuente cambia después, la placa no se actualiza sola.

## Name fitting

`App\Services\LegacyPlates\AthleteNameFormatter` genera sugerencias de
nombre corto a partir de `Athlete::first_name`/`last_name` (nunca del
`full_name` ya condensado, para no perder información):

```
JESÚS ALEJANDRO ÁVILA GONZÁLEZ
 → JESÚS ALEJANDRO ÁVILA GONZÁLEZ  (completo)
 → JESÚS ÁVILA                    (corto)
 → JESÚS A. ÁVILA
 → J. ALEJANDRO ÁVILA
```

`App\Services\LegacyPlates\LegacyPlateNameFitService::check()` decide si
un nombre candidato cabe en el campo `athlete_name` de un
`LegacyPlateModel`:

1. `max_chars` del campo, si está configurado.
2. Ancho real vía `App\Services\FontOutlineService::measureWidthMm()` —
   **la misma tabla de métricas (`hmtx`) que ya usa el export SVG de
   producción**, no un cálculo inventado. Si la fuente no está disponible
   en el entorno, el servicio **no finge** una medición gráfica —
   responde `METRICS_UNAVAILABLE` y cae solo al chequeo de `max_chars`
   (brief §10: "no hacer cálculo gráfico falso si el renderer no lo
   soporta").

El operador puede guardar cualquier sugerencia (o texto propio) como
`Plate::engraving_display_name` sin tocar la identidad del Athlete.

## Producción

`App\Services\PlateGenerationService::generateForLegacyPlateModel()` —
método nuevo en el **mismo** servicio (brief §128: "no crear otro
pipeline separado si el actual puede evolucionar"), no una clase paralela.
Reutiliza `PlateSnapshotBuilder`, `attachLegacyCode()` y `queueProduction()`
tal cual. El archivo de producción (`ProductionArtifact`) para una placa
`manufactured_dynamic` contiene **solo** los campos dinámicos — nunca
decoración/fondo, eso ya existe físicamente.

## Gate de pago (Legacy Plate v2)

`App\Services\PlateEligibilityService::checkForEntitlement()` — método
nuevo junto al `check()` histórico, para el flujo comercial nuevo
(`LegacyPlateEntitlement`, ver `docs/architecture/commerce.md`). Un
entitlement no pagado nunca es elegible; el pago nunca dispara producción
por sí solo — un operador siempre presiona "producir" explícitamente vía
`App\Actions\LegacyPlates\GenerateLegacyPlate`.

## Deuda conocida

- No existe todavía UI (`Legacy Plate Layouts` / canvas simple) para
  editar `LegacyPlateModelField` visualmente — solo backend/admin CRUD
  directo por ahora.
- `layout_version` es un string libre (`"v1"`) sin un esquema de
  versionado formal todavía — suficiente para Fase 1, documentado como
  simplificación.
