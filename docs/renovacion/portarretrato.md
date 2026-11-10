# Portarretrato inteligente

Marco de acrílico de 20 × 25 cm con un chip NFC en la base (deck de estrategia, pp. 9–10). No tiene pantalla, batería ni códigos impresos: al acercar el celular se abre el **álbum digital** (fotos y mensaje de voz). La foto impresa solo lleva el monograma FL y «Acerca tu celular».

Son dos piezas que trabajan juntas:

| Pieza | Qué es | Dónde vive |
|---|---|---|
| **Físico** | Producto de catálogo `portarretrato-inteligente`. Cada marco es una unidad NFC con su liga `/t/{código}` y su código de activación, igual que un Tag. | `catalog:publish-portrait-frame`, Inventario → Lotes |
| **Digital** | El álbum que abre el NFC (un recuerdo con `kind = album`) y el diseñador de la foto impresa. | `/legacy/{id}`, `/mi-legacy/{id}/portarretrato` |

## 1. Publicar el producto

```
php artisan catalog:publish-portrait-frame              # lo crea como BORRADOR, sin precio
php artisan catalog:publish-portrait-frame --price=1290 # lo crea publicado a $1,290 MXN
php artisan catalog:publish-portrait-frame --dry-run    # solo muestra lo que haría
```

- Crea el producto «Portarretrato inteligente» (tipo Accesorio, `tracks_units = true`, requiere envío), una variante `20 × 25 cm` (SKU `PORTARRETRATO-20X25`), descripción, eslogan y dos imágenes (`public/images/products/portarretrato/`, render conceptual del deck).
- Es **idempotente**: si el producto ya existe no toca precio, nombre, textos, estado ni imágenes. Después de la primera corrida todo se edita en **Admin → Productos**.
- Sin `--price` queda en borrador: el dueño fija el precio y lo publica desde el admin.
- Para venderlo hay que **recibir un lote** (Admin → Inventario → Lotes): al crear el lote se generan las unidades con su liga NFC y el CSV de códigos de activación para las tarjetas de la caja, igual que con los Tags.

En local ya está publicado a $1,290 MXN (precio de arranque, editable) con un lote de 10 marcos recibido en «Main Warehouse».

### Fotos incluidas en el álbum

El límite no está en el código: sale de las reglas de **Admin → Límites y tarifas** (`photo_quota_rules`, ver `cuotas-y-precios.md`). La regla más específica gana: producto → tipo de producto → global.

Para «5 fotos incluidas» en el marco: Admin → Límites y tarifas → Límites → nueva regla con alcance **Producto: Portarretrato inteligente** → *Fotos incluidas* = 5. Lo que se deje vacío hereda de la regla global. El comando `quotas:seed-defaults` deja esa regla creada si el producto ya existe (busca el slug `portarretrato-inteligente`). Bajar el límite nunca borra fotos ya subidas.

## 2. Cómo se configura el NFC (app y web)

El marco usa **exactamente** el flujo de activación de un Tag (`manual-activacion-nfc.md`); no hay código aparte:

1. El cliente abre la app (iOS / Android) o la web en **Chrome para Android** (`/activar`) y captura el código de la tarjeta que viene en la caja.
2. `StartTagActivation` valida el código y responde la liga a grabar: `https://finisherlegacy.com/t/{código}`.
3. La app (o Chrome con Web NFC) graba esa liga en el chip de la base. `ConfirmTagActivation` confirma, liga el chip a la cuenta y crea el álbum.
4. Desde ese momento, cualquier celular que se acerque abre `/t/{código}`:
   - marco activado → el álbum (según su privacidad: público, con liga o privado);
   - marco vendido sin activar → `/activar/{código}`;
   - marco en bodega o dado de baja → una página que lo explica.

Si el marco llega con el chip en blanco no pasa nada: el paso 3 es justo el que lo graba. En iPhone la grabación se hace desde la app (Safari no tiene Web NFC); la lectura funciona en cualquier teléfono con NFC sin instalar nada.

Prueba automática: `tests/Feature/Portrait/PortraitFrameProductTest.php` recorre lote → código → `start` → `confirm` → `/t/{código}` → álbum.

### El álbum

`StoryPresenter::story()` añade `kind`: `album` cuando el producto de la unidad está en `config('portrait.product_slugs')`, `tag` en cualquier otro caso (`App\Services\Portrait\PortraitProduct`). Con `album`, la página pública (`legacy/Story.vue`) antepone «Álbum» al título y al encabezado y muestra **primero las fotos**; el título por omisión es «Mi álbum». Todo lo demás (privacidad, enlaces para compartir, voz, videos) es idéntico a un recuerdo.

## 3. Diseñar la foto impresa

`/mi-legacy/{story}/portarretrato` (solo el dueño, `LegacyStoryPolicy::print`). Cinco pasos:

1. **Foto**: una foto ya lista del álbum o una propia (arrastrar y soltar). La propia no se guarda.
2. **Recorte**: encuadre con la proporción exacta del recuadro de la plantilla, con medidor de calidad (dpi efectivos, mismo cálculo que el servidor).
3. **Plantilla**: miniaturas reales de las cuatro plantillas con la foto y los datos del usuario.
4. **Datos**: nombre, carrera, lugar y fecha, tiempo oficial, distancia, ritmo, lugar, número, categoría y dedicatoria. Se precargan del recuerdo; lo vacío no se imprime.
5. **Descargar**: «Para mi marco» (tamaño exacto) o «Para imprenta» (con sangrado y, si se pide, marcas de corte), en JPG o PDF, con barra de progreso.

La vista previa grande (dentro de una maqueta del marco hecha en CSS) y las miniaturas **las dibuja el servidor** con el mismo `PortraitComposer` del archivo final (`POST …/portarretrato/vista-previa`, a tamaño de pantalla y sin validar resolución). No existe una segunda versión del diseño en CSS: lo que se ve es lo que se imprime.

### Plantillas

| Clave | Nombre | Idea |
|---|---|---|
| `carbono` | Carbono | Fondo Negro Carbono, foto enmarcada, nombre en blanco, tiempo grande en Oro Legacy a la izquierda y datos a la derecha. La de la p. 10 del deck. |
| `hueso` | Blanco hueso | Foto a sangre arriba y ficha Blanco Hueso centrada, como cédula de galería. |
| `minimal` | Minimal | La foto ocupa todo el marco; un degradado al pie sostiene nombre, carrera y tiempo. |
| `editorial` | Editorial | Papel claro y el tiempo en números gigantes a todo el ancho (si no hay tiempo, el nombre toma ese lugar). |

Todas terminan con la firma centrada **monograma FL · línea · «ACERCA TU CELULAR»**, la misma que lleva grabada la base del marco.

Las plantillas son **datos** (`config/portrait.php` §templates), no código. Coordenadas proporcionales: `x`, `w`, tamaños y separaciones son fracciones del **ancho**; `y`, `h` de los bloques, fracciones del **alto**. Cada plantilla define fondo, recuadro de foto y una lista de bloques: `rect`, `scrim` (degradado sobre la foto), `rule` y `stack`. Un `stack` es una columna de filas que **fluye** dentro de su caja (`justify`: start / center / end):

- `text` (una línea; con `fit` ocupa todo el ancho), `paragraph`, `rule`, `spacer`, `mark`, `lockup` (firma), `stats` (columnas etiqueta/valor) y `split` (columnas lado a lado).
- Una fila sin dato desaparece junto con su separación; `when`, `when_any` y `unless` condicionan filas a que existan datos.
- Si el contenido no cabe en la caja, todo el bloque se reduce proporcionalmente.

Los datos de carrera salen en la fila `stats`, hasta cuatro, en este orden: distancia, ritmo, lugar, categoría, número. Si solo hay un texto libre de resultado, se imprime como «Resultado».

### Especificaciones de impresión

| Concepto | Valor por omisión | Variable |
|---|---|---|
| Medida final | 20 × 25 cm | `PORTRAIT_PRINT_WIDTH_CM`, `PORTRAIT_PRINT_HEIGHT_CM` |
| Resolución | 300 dpi → 2362 × 2953 px | `PORTRAIT_PRINT_DPI` |
| Mínimo aceptado / aviso | 150 dpi / 220 dpi efectivos | `PORTRAIT_MIN_DPI`, `PORTRAIT_WARN_DPI` |
| Sangrado («Para imprenta») | 3 mm por lado → 2432 × 3023 px | `PORTRAIT_BLEED_MM` |
| Marcas de corte (opcional) | en un margen blanco de 6 mm → 2574 × 3165 px | `PORTRAIT_CROP_MARKS_SLUG_MM` |
| Color | JPG RGB con perfil **sRGB incrustado** | — |
| Calidad JPG | 92 | `PORTRAIT_JPEG_QUALITY` |
| PDF | una página del tamaño físico exacto del archivo, con el JPG sin recomprimir | — |
| Margen seguro | ningún texto a menos de 5 % del ancho (1 cm) del borde | plantillas |

- **Para mi marco**: se imprime al 100 % («tamaño real», sin «ajustar a la página») en papel fotográfico mate o lustre y entra directo al acrílico.
- **Para imprenta**: el sangrado es un espejo del borde del diseño, así que el área final es idéntica a la vista previa con cualquier plantilla. Las marcas de corte quedan fuera del sangrado.
- Las fuentes son TTF incluidos en `resources/fonts` (Montserrat Bold y SemiBold, Manrope Medium, licencia OFL; DejaVu de respaldo). El monograma es la imagen oficial `public/images/brand/logo/fl-monograma.png`, nunca letras tecleadas.

### Render (`app/Services/Portrait`)

- `PortraitComposer`: decodifica el **original** (con orientación EXIF), valida que no sea una bomba de descompresión, recorta al recuadro, verifica los dpi efectivos (`ancho del recorte × dpi / ancho del recuadro`), dibuja la plantilla, añade sangrado / marcas y exporta el JPG con dpi y perfil sRGB.
- `PortraitLayout`: el motor de bloques descrito arriba. Con lienzo nulo solo mide (lo usan las pruebas).
- `TextFitter`: métricas TTF reales (FreeType). Una línea se reduce hasta su `min_size` y solo entonces se corta con «…». El texto nunca sale de su caja.
- `PortraitOptions` (sangrado, marcas, validar resolución), `PortraitSpec` (medida y dpi; `scaledToWidth` para vistas previas), `PortraitPdf`, `SrgbProfile`, `PortraitAssets`, `PortraitProduct`.
- Se usa GD siempre, aunque `FINISHER_IMAGE_DRIVER=imagick`, porque el texto se dibuja con `imagettftext`. Un lienzo completo más una foto de 12 MP ocupa ~250 MB: `PORTRAIT_MEMORY_LIMIT` (512M) se sube solo durante el render.

## 4. Notas para el proveedor del marco

Complementan `especificacion-nfc-proveedor.md` (mismo chip y mismas reglas que el Tag):

- **Chip**: NTAG213 (144 bytes útiles; la liga `https://finisherlegacy.com/t/XXXXXXXXXXXX` ocupa ~45). Inlay o sticker de 25–30 mm de diámetro. Se entrega **en blanco y sin bloquear**: lo graba el cliente al activar. No se bloquea ni se protege con contraseña de fábrica.
- **Posición**: dentro de la base negra, centrado detrás del grabado «FL | ACERCA TU CELULAR», pegado a la cara frontal (a no más de 2–3 mm de la superficie). El grabado es el punto donde la gente acerca el teléfono, así que el chip debe quedar justo ahí.
- **Sin metal cerca**: la base no debe ser metálica ni llevar lámina, pintura conductiva o contrapeso de metal junto al chip; los separadores metálicos del acrílico quedan a más de 3 cm de la antena. Si el diseño exige metal, usar inlay anti-metal (con capa de ferrita) y volver a probar.
- **Prueba de recepción** por lote (muestra de 5 %): leer y grabar con un iPhone y un Android de gama media, con el marco sobre madera y sobre vidrio; la lectura debe darse a 1–2 cm del grabado en menos de un segundo. Probar también con funda. Anotar las piezas defectuosas al recibir el lote (se dan de baja y no cuentan como inventario).
- **Tarjeta de la caja**: una por marco, con el código de activación del CSV del lote. Sin códigos para escanear.
- **Acrílico**: debe aceptar una impresión de 20 × 25 cm sin recortarla; los separadores y el borde cubren como máximo 5 mm del contorno de la foto (las plantillas dejan 1 cm libre de texto).

## 5. Pruebas

- `tests/Feature/Portrait/PortraitComposerTest.php`: dimensiones, tamaño configurable, las cuatro plantillas, nombres y textos largos (se reducen y se cortan con «…» sin desbordar ni invadir el margen), filas que desaparecen sin datos, datos de carrera, foto pequeña rechazada, proporción del recorte, vista previa, sangrado, marcas de corte, perfil sRGB y PDF.
- `tests/Feature/Portrait/PortraitBuilderTest.php`: página del diseñador, vista previa del servidor, descargas (marco, imprenta, PDF), permisos y validación.
- `tests/Feature/Portrait/PortraitFrameProductTest.php`: comando de catálogo (borrador, publicado, idempotente) y activación de un marco hasta abrir el álbum.

## 6. Pendientes

- **Imagen del producto**: es el render conceptual del deck (716 px ampliado a 1200). Muestra una foto y un logotipo de carrera de ejemplo; conviene cambiarla por fotografía real del marco antes de la campaña (Admin → Productos → Imágenes).
- **Perfil de color de la foto original**: el archivo se etiqueta sRGB, pero GD no convierte fotos que vienen en otro espacio (por ejemplo Display P3 de un iPhone); en esos casos los colores muy saturados pueden variar un poco. Convertir exigiría Imagick con perfiles ICC.
- **App móvil**: `kind` ya viaja en la página web; si la app debe rotular «Álbum», hay que exponerlo también en `apiStory` (contrato `docs/api/v1.md`).
- **Medida definitiva del acrílico**: confirmar con el proveedor el tamaño real de la ventana y, si cambia, ajustar `PORTRAIT_PRINT_*` (las plantillas son proporcionales).
