# Fotografías pendientes — prompts

En este entorno no fue posible generar **fotografías**. Los renders de producto
(Legacy Plate y la línea FL) sí se generaron como renders conceptuales con
`node tools/renders/build.mjs` (salida en `public/media/brand/plate/` y
`public/media/products/concepts/`).

Para sustituir cualquier imagen basta con guardar el WebP final en la misma
ruta (o cambiar la ruta en `resources/js/config/media.ts`); no hay que tocar Vue.
En productos, una imagen subida desde Administración → Productos siempre gana
sobre el render conceptual.

Formato sugerido: WebP, 2 anchos (p. ej. 1000 y 1600 px), calidad ~82.

Legacy Plate V3 (referencia para cualquier foto o render): 70 × 45 mm, Zamak
niquelado ligeramente cepillado, diseño solo al frente con resina, panel negro
FL con NFC; el reverso NO lleva diseño — solo metal limpio y un clip de acero
inoxidable tipo money clip que sujeta la placa al listón de la medalla. Nunca
QR, nunca datos en el reverso.

| Uso | Ruta destino sugerida | Prompt |
| --- | --- | --- |
| Lifestyle Legacy Plate (F‑7) | `public/media/brand/plate/legacy-plate-lifestyle-{1000,1600}.webp` | Documentary sports photograph, runner just after crossing a marathon finish line in Mexico, sweaty, bib number visible, the finisher medal hanging from its ribbon with a small horizontal metal plate (nickel-plated Zamak, lightly brushed silver, 7×4.5 cm, rounded corners, left area cream resin with the printed name and time, right area glossy black panel with a gold "FL" monogram) clipped onto the ribbon just above the medal; plate in sharp focus, athlete slightly soft; warm late-afternoon light; no QR code, no third-party logos; 35mm, f/2, natural colors. |
| Clip real (V3) | `public/media/brand/plate/legacy-plate-back-{800,1600}.webp` (reemplaza el render) | **Debe ser una foto del producto real**: reverso de la Legacy Plate sobre fondo crema, metal niquelado limpio sin ninguna impresión y el clip tipo money clip de acero inoxidable (≈52×20 mm) fijado al centro; luz suave de estudio. |
| Listón sujeto (V3) | `public/media/brand/plate/legacy-plate-ribbon-held-{480,800,1200}.webp` (reemplaza el render) | **Foto del producto real** sobre el listón de una medalla: listón vertical pasando entre la placa y el clip, placa visible al frente, medalla debajo; fondo neutro claro. |
| Hero campaña (O) | `public/media/home/hero/finish-line-{1000,1600}.webp` (luego actualizar `MEDIA.photo.celebration`) | Editorial endurance-sport campaign photo, female trail runner arriving at the finish arch at golden hour, arms opening in emotion, race bib visible, crowd softly blurred behind, dust in backlight, vertical 4:5 composition with empty upper-left area for text, warm neutral grade, no brand logos. |
| Nosotros — México (P) | `public/media/about/mexico-{1000,1600}.webp` | Wide atmospheric photo of runners on a Mexican city avenue at dawn (Paseo de la Reforma style), mist, soft golden light, small figures, documentary feel, no identifiable faces, no logos. |
| Nosotros — Equipo (P) | `public/media/about/team-{1000,1600}.webp` | **Debe ser una foto real del equipo** (no generar personas ficticias). |
| Nosotros — Actividades (P) | `public/media/about/activity-{1..4}-{1000}.webp` | Real photos of Finisher Legacy at events: aid station, kit pickup, finish line, plate delivery. Si no existen, no generar. |
| Fotógrafos — hero (R) | `public/media/photographers/hero-{1000,1600}.webp` | Sports photographer kneeling at the side of a road race course with a telephoto lens, runners blurred passing by, early morning light, shallow depth of field, no camera brand logos visible. |
| Eventos — portadas por disciplina | `public/media/home/story/*` (ya existen fotos atmosféricas) | Natación (aguas abiertas, salida de playa) y Obstacle Racing (muro/lodo) — hoy usan la foto genérica de pista. |
| NFC en uso real (F‑3, foto) | `public/media/brand/plate/legacy-plate-nfc-photo-{1000}.webp` | Close-up photo of a hand bringing a smartphone close to the FRONT of the small metal Legacy Plate (clipped on a medal ribbon) resting on a wooden table, the phone screen softly lit (no detailed UI), shallow depth of field, warm interior light, no QR code. |
