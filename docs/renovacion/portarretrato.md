# Portarretrato Finisher Legacy

Plantilla de autoservicio para la foto del marco acrílico (deck de estrategia, pp. 9–10). El marco no tiene pantalla, batería ni QR: el álbum y el mensaje de voz se abren por **NFC**; la impresión solo lleva el monograma FL y «Acerca tu celular».

## Flujo del usuario

`/mi-legacy/{story}/portarretrato` (solo el dueño, `LegacyStoryPolicy::print`):

1. **Foto**: una foto ya lista de su recuerdo o una propia (arrastrar y soltar). La propia no se guarda.
2. **Encuadre** con la proporción fija del recuadro de foto de la plantilla (arrastrar, flechas, zoom). El aviso de calidad usa el mismo cálculo de dpi que el servidor.
3. **Plantilla y datos**: nombre, carrera, lugar y fecha, tiempo, distancia/lugar y dedicatoria opcional. Vista previa en vivo.
4. **Descarga**: JPG a tamaño de impresión, generado en el servidor. Después se muestra «Así quedó tu archivo».

## Configuración (`config/portrait.php`)

| Clave | .env | Default |
|---|---|---|
| `print.width_cm` / `height_cm` | `PORTRAIT_PRINT_WIDTH_CM` / `PORTRAIT_PRINT_HEIGHT_CM` | 20 × 25 cm |
| `print.dpi` | `PORTRAIT_PRINT_DPI` | 300 → 2362 × 2953 px |
| `print.min_dpi` | `PORTRAIT_MIN_DPI` | 150 (por debajo se rechaza) |
| `print.warn_dpi` | `PORTRAIT_WARN_DPI` | 220 (por debajo se avisa) |
| `print.jpeg_quality` | `PORTRAIT_JPEG_QUALITY` | 92 |
| `memory_limit` | `PORTRAIT_MEMORY_LIMIT` | 512M (se sube solo mientras se renderiza) |
| `fonts.heading` / `fonts.body` | `PORTRAIT_FONT_HEADING` / `PORTRAIT_FONT_BODY` | se usa el primer TTF que exista |

Las plantillas son **datos**, no código: `templates.*` define el fondo, el recuadro de foto y los slots (`text`, `paragraph`, `rule`, `mark`). Las coordenadas son proporcionales (x/w/tamaños sobre el ancho, y/h sobre el alto), así que funcionan con cualquier medida configurada. Hoy hay dos: **carbono** (fondo Negro Carbono, tiempo en Oro Legacy, como la p. 10) y **hueso** (foto a sangre arriba y banda Blanco Hueso).

## Render (`app/Services/Portrait`)

- `PortraitComposer`: Intervention (GD) solo decodifica y aplica la orientación EXIF. El recorte sale del **original** (`original_path`; si no existe, de la variante más grande) y se remuestrea una sola vez al lienzo. El dpi efectivo es `ancho del recorte × dpi / ancho del recuadro`. Antes de decodificar se valida que la imagen no sea una bomba de descompresión (`finisher.image.max_pixels`).
- `TextFitter`: mide con métricas TTF reales de FreeType (`imagettfbbox`). Una línea se reduce hasta su `min_size` y solo después se corta con «…». Un párrafo hace lo mismo con un máximo de `max_lines`. El texto nunca sale de su caja.
- El monograma FL es la imagen `public/images/brand/logo/logo-mark-{gold,black,white}.png`, nunca letras tecleadas.
- Se usa GD siempre, aunque `FINISHER_IMAGE_DRIVER=imagick`, porque el texto se dibuja con `imagettftext`.

## Fuentes: pendiente

Los TTF de Montserrat y Manrope **no están en el repo** (la web usa Instrument Sans vía Bunny y no hay `@fontsource`). Mientras no estén, la impresión usa DejaVu Sans, que ya viene incluida. Para pasar a las fuentes de marca (licencia OFL) basta copiar a `resources/fonts/` estos archivos:

- `Montserrat-Bold.ttf` (títulos, tiempo)
- `Manrope-Medium.ttf` o `Manrope-Regular.ttf` (texto)

No hace falta cambiar código. La vista previa web ya pide Montserrat y Manrope y, si no están, usa la fuente del sitio.

## Pruebas

- `tests/Feature/Portrait/PortraitComposerTest.php`: dimensiones, tamaño configurable, ambas plantillas, nombres y textos largos (se reducen y se cortan con «…» sin desbordar), foto pequeña rechazada y proporción del recorte forzada.
- `tests/Feature/Stories/StoryMemoryTest.php`: endpoint de descarga, permisos y errores de validación.
