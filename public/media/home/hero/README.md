# Hero media — asset contract

Rendered by `resources/js/components/public/media/HomeHeroMedia.vue`.

| Archivo | Estado | Qué muestra |
|---|---|---|
| `finisher-hero-desktop.mp4` | **LISTO** | H.264 High, yuv420p, sin audio, `+faststart`. Fuente MP4 de fondo full-bleed del Hero. |
| `finisher-hero-desktop.webm` | **LISTO** | Transcode VP9 del mismo video, servido como primer `<source>` (menor peso donde el navegador lo soporta). |
| `finisher-hero-poster.webp` | **LISTO** | Frame fijo real, usado como `poster` del `<video>` y como fallback de imagen si el video falla. |
| `finisher-hero-poster-mobile.webp` | **LISTO** | Mismo frame en un ancho recortado para viewports `<=640px`. |

`HomeHeroMedia.vue` reproduce el video en **todos** los anchos de viewport
(desktop y mobile, paridad desde 2026-08-21) — no hay una escena CSS-only
para mobile. El orden de fallback es:

1. `video` — reproduce con `poster` ya asignado, así el primer paint nunca
   es un frame negro mientras decodifica.
2. `poster` — si el archivo de video da `error`, o si `play()` es
   rechazado por el navegador (autoplay policy, decode failure — algunos
   navegadores rechazan la promesa sin disparar `error`), se muestra el
   frame fijo real (`finisher-hero-poster*.webp`, según viewport).
3. `css` — solo si el poster también falla al cargar. Escena dibujada
   (líneas de pista + luz dorada), nunca vacío.

`prefers-reduced-motion` salta directo al poster (paso 2) — sigue siendo
una foto real, no la escena CSS.

El MP4 original traía una pista de audio en MP3 (el elemento es
`muted`, pero MP3-en-MP4 no es un combo garantizado en todos los motores,
notablemente WebKit/Safari, que solo garantiza AAC). Se retranscodificó
sin audio (`-an`) para eliminar esa variable de compatibilidad — el video
es puramente decorativo y siempre está silenciado, así que no hay pérdida
de contenido.
