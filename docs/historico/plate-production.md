# Producción de placas: de molde a placa entregada

> **OBSOLETO (2026-11-11).** Ya no se producen placas grabadas: este flujo y su código se retiraron. Se conserva solo como referencia histórica. Ver `docs/renovacion/retiro-modulos.md`.

Este documento describe el flujo completo de Plate Studio: cómo se diseña un
molde de **dos caras**, cómo se asigna a un evento, cómo se genera una placa
real, y cómo se exportan los archivos que van a la máquina de grabado láser.
Ver también `docs/legacy-code-lifecycle.md` para la regla de permanencia del
Legacy Code.

**Regla central: Finisher Legacy produce DOS archivos de grabado por
placa, uno por cara.** Nunca uno solo con ambas caras mezcladas (salvo un
layout de producción especial que hoy no existe).

```
Finisher Legacy → SVG (frente + reverso, por separado)
                → LightBurn
                → Fiber Laser
                → grabar frente
                → voltear la placa (jig)
                → grabar reverso
                → escanear QR
                → checklist (frente/reverso/QR)
                → Lista
```

## 1. Conceptos

| Concepto | Qué es | Dónde vive |
|---|---|---|
| **Plate Template** (Molde) | Metadatos de un diseño de placa: nombre, dimensiones físicas (mm), material, orientación, margen de seguridad, tipo de deporte (`sport_type`), transformación del reverso (`back_transform`), tamaño mínimo de QR validado físicamente (`minimum_validated_qr_size_mm`). | `plate_templates` / `App\Models\PlateTemplate` |
| **Plate Template Version** | Una revisión concreta del diseño: configuración de **frente y reverso juntas** (lista de elementos cada una), estado (`draft`/`published`/`archived`). | `plate_template_versions` / `App\Models\PlateTemplateVersion` |
| **Event Plate Template** | Qué versión de molde usa una edición de evento (o una carrera específica dentro de ella) por defecto. | `event_plate_templates` / `App\Models\EventPlateTemplate` |
| **Plate** | Una placa concreta para una persona: snapshot de sus datos en el momento de generarla, más la versión de molde usada. | `plates` / `App\Models\Plate` |
| **Event Result / Split** | El resultado de un corredor y, opcionalmente, cualquier número de parciales (5K, Swim, T1...). | `event_results` / `event_result_splits` |
| **Legacy Code** | Identidad permanente de la placa. Ver `docs/legacy-code-lifecycle.md`. | `legacy_codes` / `App\Models\LegacyCode` |
| **Production Job** | Un trabajo en el kanban de `/production`, con su propio checklist de grabado. Una placa puede tener varios a lo largo de su vida (reimpresiones). | `production_jobs` / `App\Models\ProductionJob` |
| **Machine Profile** | Una etiqueta de flujo de trabajo ("Fiber 30W — LightBurn"), **no un driver** — Finisher Legacy nunca controla el láser directamente. | `machine_profiles` / `App\Models\MachineProfile` |

El **renderer único** (`App\Services\PlateTemplateRenderService`) es lo que
convierte una versión de molde + los datos de una placa en SVG, para
cualquiera de las dos caras (`front`/`back`). El export a PNG
(`GdPlateRenderer`) y PDF (`PdfExportService`) parten de la misma lista de
elementos resuelta por ese servicio — nunca se recalculan posiciones ni
auto-fit por separado para cada formato.

## 2. La regla de las dos caras

**CARA A (frente): datos deportivos.** Prioriza legibilidad — evento, fecha,
atleta, tiempo, splits/distancia/ritmo según el deporte, categoría/posición,
serial. **Sin QR por defecto.**

**CARA B (reverso): Legacy.** QR grande y sin competencia por espacio +
Legacy Code + "Finisher Legacy" +, opcionalmente, frase personal e
iconografía temática (`vector_icon`, ver abajo). Es la cara que el atleta
escanea para reclamar su Legacy.

Los tres presets oficiales (`database/seeders/PlateTemplateSeeder.php`) ya
siguen esta regla:

| Preset | `sport_type` | Frente | Reverso |
|---|---|---|---|
| Triathlon Premium | `triathlon` | splits SWIM/BIKE/RUN, age group, general | QR + Legacy Code + íconos nadador/bici/tenis |
| Running Classic | `running` | distancia, ritmo, general, categoría | QR + Legacy Code + íconos corredor/meta |
| Cycling Classic | `cycling` | distancia, velocidad/ritmo, general, categoría | QR + Legacy Code + íconos bici/trofeo |

El editor **no prohíbe** poner un QR en el frente — un admin puede hacerlo
para un molde custom — pero ningún preset oficial lo hace.

### Elementos condicionales (`visible_when`)

Un elemento con `'visible_when' => 'swim_time'` desaparece por completo
(etiqueta incluida) si ese campo viene vacío, en vez de mostrar `SWIM ` sin
valor. Úsalo en cualquier campo opcional (splits, ritmo, categoría, dorsal,
frase). Implementado en `PlateTemplateRenderService::resolveElements()` —
la vista previa del operador (`/operator`) usa el mismo `resolveElements()`
antes de generar, así que un running nunca muestra "SWIM"/"BIKE"/"RUN"
vacíos ni en el preview ni en la placa final.

### Iconografía temática

Es parte del **molde**, no se genera por placa. El renderer dibuja
primitivos vectoriales: `static_text`/`dynamic_text`/`serial`, `line`,
`rect`, `qr`, `image`/`logo`/`icon` (imagen embebida) y `vector_icon` — un
catálogo curado de pictogramas simples (natación, ciclismo, running,
montaña, meta, trofeo) definidos como formas normalizadas
(`App\Support\PlateVectorIcons`: polylines/círculos en un cuadro unitario
0..1) y dibujados de forma idéntica en las tres salidas (SVG vía
`DOMDocument`, PNG vía GD, PDF vía el mismo raster de GD embebido).
**Deliberadamente no acepta** paths SVG arbitrarios subidos por un admin ni
emoji — solo IDs del catálogo — para no introducir geometría sin validar en
un archivo de producción. Los tres presets oficiales ya usan `vector_icon`
en el reverso en vez de simularlo con texto y líneas.

### Marcas protegidas

Los presets genéricos (Running/Triathlon/Cycling/Trail) **solo usan** el
wordmark de Finisher Legacy e iconografía propia — nunca el logo de un
evento/organizador real. Un logo de evento solo aparece si el admin lo sube
explícitamente a ese Template/Event vía un elemento `image`/`logo`.

## 3. Crear un molde

1. Entrar a `/admin/plate-studio` (permiso `platetemplates.manage`).
2. "Nuevo molde": nombre, ancho/alto en mm, orientación, margen de
   seguridad. Se crea el `PlateTemplate` y su primera versión en estado
   `draft`.
3. En el editor: pestañas **[FRENTE] [REVERSO]** prominentes en la barra
   superior (no escondidas), cada una con su propio lienzo. Cambiar de
   pestaña **no pierde** los cambios de la otra cara — ambas viven en
   memoria (`elementsFront`/`elementsBack`) hasta guardar.
4. El toggle **Vista producto / Vista grabado** cambia entre los colores
   configurados y blanco/negro puro (lo que realmente se graba — sin metal,
   sombras ni glow). Junto al toggle, un badge muestra la transformación
   del reverso activa (Normal / Espejo horizontal / Espejo vertical /
   Rotado 180°) cuando estás viendo el reverso en modo grabado — ver §9.
5. "Guardar" (Ctrl/Cmd+S) guarda **ambas caras juntas** en un solo request.
   "Publicar" congela la versión completa (frente V1 + reverso V1 son
   siempre la misma versión — nunca se versionan por separado, así se evita
   que una placa histórica termine con "front V1 + back V3"). Para cambiar
   el diseño hay que crear una nueva versión ("Nueva versión" duplica la
   última como nuevo borrador, copiando frente y reverso completos).

### Campos dinámicos disponibles

`athlete_name`, `bib_number`, `event_name`, `event_date`, `race_name`,
`distance`, `official_time`, `pace`, `overall_position`, `category`,
`category_position`, `swim_time`, `bike_time`, `run_time`, `personal_phrase`,
`legacy_code`, `plate_serial` — catálogo completo en
`App\Support\PlateDynamicFields`.

`distance` se calcula automáticamente desde `EventRace.distance_value` /
`distance_unit` (`App\Support\RaceDistanceFormatter`, un único punto de
formato — nada más en la app formatea una distancia a mano); nunca se
escribe a mano en una placa integrada.

## 4. Versionado

Una versión **publicada nunca se edita**. Si hay que cambiar el logo o
corregir un texto después de haber impreso 300 placas con la V1, se crea la
V2 — las placas ya generadas siguen apuntando a `plate_template_version_id`
= V1 (columna en `plates`, ver `App\Models\Plate::plateTemplateVersion()`).
Nada reescribe esa referencia retroactivamente. Frente y reverso viven en la
**misma fila** (`front_configuration`/`back_configuration` en
`plate_template_versions`), así que no pueden desincronizarse en versión.

Publicar se bloquea (422) si el molde tiene `minimum_validated_qr_size_mm`
configurado (solo se pone después de una prueba física real — ver §10) y el
QR del reverso mide menos que eso.

Un molde tampoco se elimina nunca (no existe endpoint de borrado). Solo se
puede archivar (`active = false` en el molde, o `status = archived` en una
versión), lo que lo oculta de los selectores de "asignar a evento" sin
afectar placas históricas.

## 5. Splits del resultado (`event_result_splits`)

`EventResult` puede tener cualquier número de parciales
(`App\Models\EventResultSplit`: `type`, `label`, `sequence`,
`distance_value`/`distance_unit`, `segment_time`/`elapsed_time`, `pace`) —
**no está limitado a swim/bike/run**. Un running usa parciales tipo `split`
con etiquetas como "5K"/"10K"/"21K"; un tríatlon usa `swim`/`bike`/`run`; un
trail podría usar sus propios tramos. El seed de demo crea ambos ejemplos
(marathon 42K con 5K/10K/21K/30K, y un evento "Finisher Legacy Triathlon
Demo" con swim/bike/run — nombre genérico, sin marca real).

`swim_time`/`bike_time`/`run_time` (los tres campos dinámicos históricos)
siguen existiendo por compatibilidad hacia atrás — `App\Services\PlateSnapshotBuilder`
los rellena buscando, entre los splits del resultado, uno con
`type = swim|bike|run`; si ese tipo no existe (ej. una carrera de solo
running), quedan `null` y `visible_when` oculta el elemento completo en vez
de mostrar una etiqueta vacía.

En el detalle de una placa (`/admin/plates/{id}`) se muestra una sección
"Parciales" con la lista completa de splits del resultado **actual** del
corredor (label, distancia, tiempo, ritmo) — independiente de lo que haya
quedado congelado en la placa, igual que la comparación de tiempo oficial
ya existente (§8).

### Importar splits desde CSV/XLSX

El importador de participantes (`/imports`, `App\Imports\ParticipantsImport`)
puede opcionalmente mapear columnas de tiempo oficial/ritmo y cualquier
número de columnas de parciales — la **etiqueta de cada parcial es el
encabezado de esa columna en el archivo** (ej. "5K", "Swim"), editable antes
de importar. Completamente opcional: un archivo que solo trae
dorsal/nombre/carrera importa exactamente igual que siempre, sin crear
ningún `EventResult`. Reimportar el mismo archivo actualiza los splits
existentes por etiqueta en vez de duplicarlos.

## 6. Asignar un molde a un evento

En `/admin/events/{edition}/production-setup`:

- Seleccionar una versión **publicada** como molde principal del evento.
- Opcionalmente, sobreescribir por distancia (21K, 42K, etc.) si el evento
  tiene carreras con moldes distintos.
- Si la edición está `in_progress`, cambiar el molde principal muestra una
  confirmación fuerte (puede provocar placas con diseños distintos dentro
  del mismo evento).
- Checklist de la pantalla: molde asignado, versión publicada, prueba física
  de QR realizada (usuario + fecha, es la única atestación manual — el resto
  se deriva de los datos existentes).
- Botones "Prueba de grabado" (frente/reverso) generan un SVG con datos demo
  fijos para calibrar la máquina antes del evento real, sin crear ninguna
  placa ni Legacy Code. Distinto de la **tarjeta de calibración** de §10,
  que prueba tamaños de QR y legibilidad de texto, no el molde real.

`App\Models\EventEdition::defaultPlateTemplateVersion($eventRaceId)` es la
única función que resuelve "qué versión le toca a esta placa" — la usan por
igual `PlateGenerationService` (al generar) y `OperatorController::previewPlate`
(al previsualizar antes de generar). `PlateGenerationService` **rechaza**
crear una placa (y su Legacy Code/ProductionJob) si esta función devuelve
null — nunca existe una placa sin molde.

## 7. Generar una placa

Todo pasa por `App\Services\PlateGenerationService` (dos entradas) — es el
**único** lugar de la aplicación que llama `Plate::create()`; un test de
invariante (`tests/Feature/Operator/IntegratedSnapshotTest.php`) recorre
todo `app/` y `database/seeders/` para confirmarlo en cada corrida:

- **Integrada** (`generateIntegrated`): desde `/operator`, buscando un
  corredor existente. Usa sus datos de `EventParticipant`/`EventResult`/`EventResultSplit`,
  congelados vía `App\Services\PlateSnapshotBuilder` (ver §5).
- **Rápida** (`generateQuick`): sin corredor en base de datos — el operador
  escribe nombre y, opcionalmente, dorsal/tiempo/splits/frase personal.
  `user_id` queda `NULL`, pero el molde (ambas caras), el Legacy Code y el
  QR del reverso se generan exactamente igual que en el camino integrado.
  `PlateSnapshotBuilder` no participa aquí — no hay un `EventResult` que
  leer, los valores vienen tal cual del formulario.

Ambas, en una sola transacción: crean el `Plate` (snapshot de los datos +
`plate_template_version_id`), un `LegacyCode` permanente, y un
`ProductionJob` en cola. El preview que se muestra *antes* de confirmar usa
datos demo con Legacy Code `FL-PREVIEW` — nunca se crea un código real hasta
pulsar "Generar placa". La pantalla de éxito muestra el frente y el reverso
(tabs), dejando explícito que son dos grabados físicos distintos.

**El snapshot nunca cambia solo:** si el `EventResult` de un corredor se
corrige después de generar su placa, la placa sigue mostrando lo que tenía
al momento de generarla — nunca hay drift silencioso. La única manera de
actualizar lo grabado es una reimpresión explícita eligiendo "no mantener
los datos originales" (§12).

## 8. Exportar para producción

Desde el detalle de una placa (`/admin/plates/{id}`) o desde la pantalla de
éxito del operador: "Descargar para láser" abre un diálogo con:

- **Formato**: SVG (recomendado, vectorial, marcado explícitamente en la
  UI), PNG (300/600 DPI), PDF (tamaño físico exacto, no A4), o paquete ZIP
  completo (ambas caras).
- **Cara**: frente o reverso — selección independiente para SVG/PNG/PDF.
  El paquete ZIP siempre trae **ambas caras como archivos separados**
  (nunca mezcladas en un solo SVG).
- Para SVG: casilla opcional "Convertir texto a trazos (paths)" — ver §9.
- El paquete ZIP (`plate-{serial}/`) incluye: `front.svg`, `back.svg`,
  `front.png`, `back.png`, `front.pdf`, `back.pdf`, `qr.svg` y
  `production.json` (serial, Legacy Code, evento, molde, versión,
  dimensiones físicas, `back_transform`, caras incluidas — sin datos
  sensibles).

Nada se guarda en disco: cada descarga se regenera en el momento desde el
snapshot de la placa + la versión de molde exacta que usó. Descargar dos
veces produce el archivo idéntico byte a byte (mismo texto, mismo QR).

Para varias placas a la vez: en `/admin/plates`, seleccionar filas con el
checkbox y "Exportar lote" (tope configurable en
`config('plate-studio.batch_export_limit')`, 50 por defecto) — ZIP con
`{dorsal-o-serial}_{NOMBRE-SANITIZADO}_FRONT.svg` /
`..._BACK.svg` por placa (nunca el correo del atleta) más un
`manifest.json`. Sanitización en `App\Support\PlateFilename`.

**Cola asíncrona para lotes más grandes que el tope configurado: no
implementada.** Sigue siendo trabajo futuro documentado — el pilotaje actual
no necesita más que el tope duro síncrono.

## 9. Texto como trazos (paths) — elimina la dependencia de fuentes

**Investigado y resuelto.** El SVG normal referencia la fuente por nombre
(`font-family="Inter, Arial, sans-serif"`) — si la PC de producción no
tiene esa fuente instalada, LightBurn puede sustituirla y el grabado no
coincide con el preview. La opción **"Convertir texto a trazos"** (checkbox
en el diálogo de descarga, solo para SVG) convierte cada carácter en su
contorno vectorial real (`<path>`) antes de exportar — el archivo deja de
depender de cualquier fuente instalada, en cualquier máquina.

Viable **sin agregar ninguna dependencia nueva**:
`dompdf/php-font-lib` ya está vendored transitivamente (dompdf la usa para
incrustar fuentes en PDF) y expone directamente el contorno de un glifo TTF
como datos de path SVG (`FontLib\Glyph\Outline::getSVGContours()`); dompdf
además ya trae archivos `.ttf` reales en `vendor/dompdf/dompdf/lib/fonts/`
(DejaVu Sans), así que tampoco hace falta empaquetar una fuente nueva.
`App\Services\FontOutlineService` usa esa tabla `glyf` + `cmap` (mapeo
Unicode→glifo, soporta acentos/ñ) + `hmtx` (anchos de avance) para construir
un `<g>` de `<path>` posicionados exactamente donde iría un `<text>` normal.

**Limitación honesta, documentada en la propia UI:** esto usa DejaVu Sans,
no Inter — Inter no está vendored como `.ttf` en este repo. El trazo
resultante difiere ligeramente de lo que se ve en pantalla. Por eso es
**opt-in** (checkbox desmarcado por defecto) y **solo aplica al export de
producción SVG** — la vista previa web (`/admin/plate-studio`, `/operator`)
siempre muestra `<text>` real con Inter, nunca trazos, así que el editor
sigue viéndose y comportándose igual que siempre. PNG y PDF nunca tuvieron
este problema (ya son portables: un raster no depende de fuentes, y el PDF
de dompdf ya incrusta la fuente usada).

Si en algún momento se agrega Inter como `.ttf` real al repo,
`FontOutlineService::fontPath()` es el único lugar que hay que cambiar.

## 10. Perfil de máquina

`/admin/machine-profiles` (permiso `platetemplates.manage` para
crear/editar, `platetemplates.view` para ver) — `MachineProfile` es una
etiqueta de flujo, no una integración: Finisher Legacy no manda trabajos a
la máquina ni controla USB. Sirve para que la UI diga "Descargar para Fiber
30W — LightBurn" en vez de un genérico "Descargar SVG". Campos: nombre,
tipo, software, formato predeterminado (SVG/PNG/PDF), ancho/alto de área de
trabajo, ancho/alto de placa, `back_transform`, activo. Seed de desarrollo:
**Fiber 30W — LightBurn**, formato SVG, 60×40mm.

**No incluye potencia/velocidad/frecuencia** — esos valores dependen del
material, acabado, fuente y lente de cada máquina y se calibran físicamente
con una placa de prueba, nunca se envían como default universal. Un
"Material Profile" que sí capture esos valores queda como concepto futuro,
explícitamente no automatizado en esta fase.

### Transformación del reverso (`back_transform`) — ya se aplica al archivo

Al voltear físicamente la placa en el jig, el reverso puede necesitar
`none` (default), `mirror_x`, `mirror_y` o `rotate_180` según el proceso —
columna `plate_templates.back_transform` (`App\Enums\PlateBackTransform`).
**Nunca se elige automáticamente**: se determina con una prueba física y se
guarda en el molde.

**Se aplica de verdad al archivo exportado**, no solo como metadata: el
modo Producto/Original vs. Producción existente en el renderer es
exactamente la distinción "BACK ORIGINAL" (cómo se diseñó, sin transformar
— lo que ves en Plate Studio en modo vista producto) vs. "BACK PARA
PRODUCCIÓN" (con la transformación del molde ya aplicada, lo que sale en
cualquier export de producción — SVG/PNG/ZIP/lote). No hay un segundo
toggle nuevo: se reutiliza el existente para no duplicar UI.

- SVG: envuelve los elementos del reverso en un `<g transform="...">` con
  la matriz correspondiente (`translate+scale` para espejos,
  `rotate(180, cx, cy)` para rotación) — el ancho/alto de la placa nunca
  cambia, solo lo que está dibujado.
- PNG: `imageflip()`/`imagerotate()` de GD sobre el raster ya dibujado.
- El editor (`/admin/plate-studio`) muestra un badge junto al toggle de
  modo (Normal / Espejo horizontal / Espejo vertical / Rotado 180°) cuando
  estás viendo el reverso, para que nunca sea una sorpresa al abrir el
  archivo en LightBurn.

Tests geométricos en `tests/Feature/Admin/PlateBackTransformTest.php`
verifican las matrices exactas, que el frente nunca se transforma, y que el
modo producto/vista previa nunca se transforma.

### Jig / posicionador

Usa una plantilla física (jig) para colocar cada placa siempre en la misma
posición al grabar frente y reverso — sin eso, "voltear y grabar el
reverso alineado" no es repetible. Finisher Legacy no construye ni vende
hardware; esto es una recomendación de proceso.

## 11. Prueba física y calibración de QR

Antes de producción masiva: en `/admin/plate-studio`, "Prueba láser
(frente)" / "Prueba láser (reverso)" descargan una **tarjeta de
calibración** fija de 60×40mm (`App\Services\CalibrationCardService`,
SVG con unidades en mm) — no depende de ningún molde real:

- **Frente**: escalera de nombres de distinta longitud, escalera de tamaño
  de texto (2/2.5/3/3.5/4pt) para saber qué tan chico se puede grabar
  legible, y tres grosores de línea de muestra.
- **Reverso**: cuatro QR de prueba a 8/10/12/14mm, cada uno apuntando a una
  URL de prueba claramente falsa (`finisherlegacy.local/laser-test?size=...`,
  nunca `/l/{code}`) con la leyenda "FL-TEST0000 · NO ES UN LEGACY CODE
  REAL" — **nunca crea una Plate ni un Legacy Code real** (verificado por
  test).

Con el tamaño mínimo de QR que escaneó bien en la placa sacrificial, un
admin lo guarda en `plate_templates.minimum_validated_qr_size_mm` (Plate
Studio → editor del molde). **Nunca se fija un valor por defecto** —
mientras sea `null`, el renderer usa un umbral genérico de advertencia
(~10mm) sin bloquear publicación; una vez configurado, publicar una versión
con un QR más chico que ese mínimo se rechaza (§4).

## 12. Checklist de producción y reimpresión

Cada `ProductionJob` tiene tres marcas independientes:
`front_engraved_at/by`, `back_engraved_at/by`, `qr_verified_at/by`. En
`/production`, cada tarjeta en la columna "En proceso" tiene tres toggles
táctiles (frente/reverso/QR) — **"Marcar lista" queda bloqueado hasta que
los tres estén marcados**, para que una placa nunca pase a `ready` con el
reverso sin grabar por error. Es una capa adicional sobre el estado grueso
existente (pendiente/proceso/lista/entregada), no lo reemplaza.

### Reimpresión

Desde el detalle de la placa, "Reimprimir" (solo si está `ready` o
`delivered`): pide un motivo y si se debe mantener el snapshot original o
actualizarlo con el resultado actual del corredor (si cambió después de
generada la placa — se muestra la comparación "grabado en la placa" vs.
"resultado actual", igual criterio que la sección de Parciales de §5).

**El Legacy Code nunca cambia en una reimpresión**, y ambas caras se
reexportan desde la **misma** `plate_template_version_id` original — nunca
se reasigna a una versión más nueva del molde. Se crea un nuevo
`ProductionJob` **con el checklist vacío** (frente/reverso/QR sin marcar de
nuevo — una reimpresión es un grabado físico nuevo, no hereda las marcas
del trabajo anterior) y un registro en `plate_reprints` para auditoría.

## 13. Producción física y escaneo

`/production` (kanban): pendiente → en proceso → lista → entregada, con el
checklist de §12 como condición para avanzar de "en proceso" a "lista".
Cada tarjeta tiene un ícono de descarga rápida que usa el formato/DPI por
defecto configurado en la edición del evento
(`event_editions.production_export_format` / `default_dpi`), para no tener
que abrir el diálogo completo durante un evento en vivo. El board se probó
con ~30 placas simultáneas en producción sin crecer las consultas por
placa (`tests/Feature/Production/ProductionKanbanTest.php`).

Después de entregada, el atleta escanea el **QR del reverso** →
`/l/{code}` → login/registro si no tiene cuenta → confirma "Vincular a mi
Legacy" → el mismo código de siempre, nunca uno nuevo.

## 14. Permisos

| Rol | Puede |
|---|---|
| `super_admin` / `admin` | Todo. |
| `event_manager` | Ver moldes (`platetemplates.view`), asignar molde a sus eventos (`editions.manage`). |
| `event_operator` | Generar placas, ver y descargar archivos (`plates.view`/`plates.manage`) — **no** puede editar moldes publicados. |
| `production_operator` | Ver placas, gestionar producción (incluye el checklist), descargar archivos. |
| `athlete` | Sin acceso a ningún panel de administración. |

## 15. URLs de prueba

- Plate Studio: `/admin/plate-studio`
- Perfiles de máquina: `/admin/machine-profiles`
- Preparar evento para producción: `/admin/events/{edition}/production-setup`
- Generar placa (integrada/rápida): `/operator`
- Placas (admin): `/admin/plates`
- Importar participantes (con splits opcionales): `/imports`
- Producción: `/production`
- Página pública permanente: `/l/{code}`

## 16. Lo que falta para producción real

Todo lo de esta lista es **físico o de escala**, no de lógica de negocio —
la lógica de datos (snapshot, splits, checklist, back_transform, texto como
trazos) ya está implementada y probada:

- Conectar y configurar la máquina láser real, instalar/calibrar LightBurn.
- Construir o adquirir el jig físico.
- Calibrar potencia/velocidad/frecuencia con el material real (nunca un
  default universal — depende de la máquina y el material).
- Grabar una placa de prueba real con la tarjeta de calibración (§11) y
  determinar el `back_transform` real del proceso físico elegido.
- Determinar el tamaño mínimo de QR que escanea de forma confiable sobre el
  material real y guardarlo en el molde.
- Cola asíncrona para lotes de exportación más grandes que el límite
  configurado (hoy es síncrono con tope duro) — fase futura, no bloquea el
  pilotaje actual.
- Cache de miniaturas de preview (`preview_front_path`/`preview_back_path`
  existen en el esquema pero no se usan; el preview siempre se renderiza
  SVG en vivo) — optimización futura, no un bloqueador.
- Integración directa con el software/driver de la máquina láser — esta
  fase termina en el archivo de exportación (SVG/PNG/PDF); el operador lo
  importa manualmente a LightBurn.

## Event Ops (Slice 5)

`App\Http\Controllers\OperatorController` (`/operator`) es la consola
donde un operador busca un corredor y produce su placa — ver
`docs/adr/0006-event-operations.md`. Antes de producir, siempre puede
consultar:

- **Elegibilidad** (`App\Services\PlateEligibilityService`, Slice 4):
  `NO_RESULT`/`NO_TEMPLATE`/`IDENTITY_CONFLICT`/`PLATE_ALREADY_EXISTS`.
- **Preparación del evento** (`App\Services\Production\EventProductionReadiness`):
  molde, carrera, fuente de datos, estación, perfil de máquina,
  calibración QR — solo el molde bloquea la producción.
- **Métricas reales** (`App\Queries\Production\GetProductionMetrics`):
  tiempos medidos (cola, frente, volteo, reverso, QR, total), nunca un
  número prometido de antemano.

El driver de láser (`App\Contracts\Production\LaserDriver`,
`App\Services\Production\Drivers\MockLaserDriver`) es una abstracción
para el Desktop Event Station — ver `docs/adr/0007-desktop-event-station.md`.
Sigue sin existir integración real con LightBurn/el láser; esta fase
sigue terminando en el archivo de exportación, como arriba.
