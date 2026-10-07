# Legacy Plate V3 — diseño frontal + clip money clip (2026-10)

Especificación vigente del producto. Reemplaza el concepto "impresión frente
y reverso" de `legacy-plate-v2.md` y el pipeline de moldes grabados de
`docs/plate-production.md` (ambos se conservan como **históricos**).

## Especificación

| | |
|---|---|
| Medidas | **70 × 45 mm** (default; editable) |
| Cuerpo | **Zamak niquelado**, ligeramente cepillado |
| Cara frontal | Diseño deportivo personalizado, protegido con resina |
| NFC | Integrado al frente, bajo el panel FL, sobre **ferrita anti-metal** (pasivo: sin batería, sin GPS) |
| Reverso | Metal limpio + **clip tipo money clip**. **Sin diseño**, sin datos, sin QR, sin branding |
| Clip | **Acero inoxidable estampado**, ≈ 52 × 20 × 1 mm, ondulaciones con 2–3 puntos de presión |
| Grosor total | ≈ 6 mm (cuerpo + clip) |

Construcción (de fuera hacia dentro): resina/acabado → inlay NFC → ferrita →
cuerpo de Zamak → clip posterior (se instala al final, en el reverso).

Uso: el listón de la medalla se introduce entre la placa y el clip por el
extremo abierto; las ondulaciones lo presionan y la placa queda visible sobre
el listón. El teléfono se acerca al **frente** para abrir el Legacy.

## Datos

- `legacy_plate_models`: nuevas columnas `spec_version` (`v3`),
  `clip_length_mm`, `clip_height_mm`, `clip_thickness_mm`, `total_depth_mm`,
  `body_material`, `clip_material` (migración
  `2026_10_09_090000_legacy_plate_v3_front_only_with_clip`). Se editan en
  **Admin → Legacy Plate → Layouts → Especificación física**
  (`PATCH /admin/legacy-plate-models/spec`), igual para los tres layouts.
- Los tres layouts (`App\Support\LegacyPlateLayouts::v3()`) son **solo
  frente**: Núcleo (nombre protagonista, dos líneas), Distancia (la distancia
  gigante, franja champagne) y Trayecto (ficha técnica en celdas: atleta,
  evento, fecha, tiempo, distancia, ritmo, posición, dorsal).
- `LegacyPlateFieldKey::defaultFace()` siempre es `front`; el editor rechaza
  `face = back`. `LegacyPlateModel::toViewerArray()` solo envía campos del
  frente.
- Columnas `back_area`, `back_artwork_path`, `back_background`,
  `back_text_color` y `legacy_plate_model_fields.face` **se conservan** solo
  para lectura histórica — la UI ya no las expone.

## Compatibilidad / snapshots

- `plates.layout_snapshot` (JSON): al generar una placa
  (`PlateGenerationService::generateForLegacyPlateModel`) se congela el layout
  completo (`LegacyPlateModel::toSnapshotArray()`, `spec_version = v3`).
- La migración V3 primero congeló el layout **v2** (frente + reverso impreso,
  90 × 34) en cada placa ya producida y luego actualizó los layouts.
- `Plate::layoutViewer()` devuelve el snapshot si existe; si no, el layout
  vivo. `PlatePrintFace` dibuja el reverso impreso **solo** para snapshots
  `spec_version = v2`; para V3 el reverso es `PlateClipBack` (clip).
- `LegacyPlateLayouts::definitions()` queda como set histórico v2 porque
  migraciones ya ejecutadas lo leen. No usar para producto nuevo.
- Plate Studio / moldes (`PlateTemplate`, 60 × 40, QR, grabado láser,
  frente/reverso) = **flujo histórico**: fuera del menú, con aviso
  `HistoricalPipelineNotice`, conservado para consultar/reimprimir pedidos
  antiguos. Moldes nuevos parten de 70 × 45.

## Dibujo y renders

`resources/js/lib/plate-art.ts` es el único dibujo del producto físico
(frente, reverso con clip, perfil lateral, placa sobre listón). Lo usan:

- `PlateShowcase.vue` ("Ver frente / Ver broche"), `PlateClipBack.vue`,
  `PlateClipSteps.vue` ("Cómo se sujeta", 4 pasos).
- `tools/renders/*` (Node lo importa directamente; `node tools/renders/build.mjs`).

Renders en `public/media/brand/plate/legacy-plate-{name}-{w}.webp`:
`front`, `perspective`, `back` (clip), `clip-macro`, `profile`,
`ribbon-insert`, `ribbon-held`, `nfc`, `exploded` (cuerpo Zamak vs clip
acero), más `hero`, `portrait`, `float`, `macro`. Referenciados desde
`resources/js/config/media.ts` y `Product::conceptGallery()`; una
`ProductMedia` real siempre los sustituye.

## Producción

Tablero `/admin/legacy-plates/production`: Por pagar → Lista para imprimir →
En impresión → Impresa · NFC · clip → Entregada. El Dashboard admin presenta
las etapas Pedido / Personalización / Impresión / NFC + clip / Control de
calidad / Envío sobre los estados internos existentes (sin renombrarlos).
