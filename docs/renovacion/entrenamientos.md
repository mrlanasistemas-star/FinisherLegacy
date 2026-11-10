# Entrenamientos — «Envíame ánimo»

> Documento del módulo Entrenamientos: flujo, contrato de la API para la app
> móvil y reglas de privacidad. Código: `app/Actions/Training`,
> `app/Http/Controllers/Training`, `app/Http/Controllers/Api/V1/Training`,
> `app/Models/Cheer*`, `resources/js/pages/training`. Pruebas:
> `tests/Feature/Training`.

## Qué es

El atleta comparte un enlace «Envíame ánimo» con su familia y amigos. Ellos lo
abren en cualquier navegador, **sin cuenta y sin app**, y le dejan un mensaje
de voz grabado ahí mismo (o un audio que ya tengan), un texto corto opcional,
su nombre, su relación con el atleta y un ícono. Durante el entrenamiento el
atleta escucha esos mensajes en la app (o en la web) como una lista de
reproducción.

## Flujo

1. **El atleta crea un enlace** en `/mi-entrenamiento` (web) o con
   `POST /api/v1/training/links` (app): un nombre (≤ 80) y un vencimiento
   opcional de 7, 30 o 90 días. Se genera un token aleatorio de 32 caracteres
   (`Str::random(32)`, nunca secuencial ni derivado del id). URL:
   `{APP_URL}/animo/{token}`. Máximo 20 enlaces activos por atleta; cada
   enlace acepta hasta 200 mensajes (`config/training.php`).
2. **Lo comparte**: copiar o compartir con la Web Share API (web) / hoja
   nativa (app).
3. **Quien lo recibe abre `/animo/{token}`** (página pública, sin la barra
   del sitio, `noindex`): ve el nombre del atleta y su foto (solo si su perfil
   es público), graba con el botón grande (medidor de nivel en vivo, contador
   hasta 3:00, escuchar antes de enviar, grabar de nuevo) o sube un archivo, y
   llena nombre, relación, texto e ícono. Envía con barra de progreso y ve una
   pantalla de éxito.
   - Grabación: `MediaRecorder` con `audio/webm;codecs=opus`; en iOS Safari
     cae a `audio/mp4` (`resources/js/composables/useVoiceRecorder.ts`).
   - Estados manejados: micrófono denegado o ausente, navegador sin
     `MediaRecorder`, conexión no segura, sin internet (se puede grabar y
     enviar al volver la conexión), enlace desactivado / vencido / lleno
     (página amable), token desconocido (404).
4. **El servidor valida y guarda** (`App\Actions\Training\SubmitCheerMessage`):
   - Nombre ≤ 60, relación ≤ 40, texto ≤ 500, ícono de la lista fija
     (`heart, fire, medal, muscle, star, flag, sun, rocket`); audio o texto
     obligatorio (al menos uno).
   - El audio pasa por el mismo pipeline que el audio de las historias Legacy
     (`MediaStorage::accept`): tipo **detectado por los bytes** (fileinfo,
     lista `ManageStoryMedia::AUDIO_MIMES`), nunca por la extensión; ≤ 10 MB;
     disco temporal → cola (Process → Transfer → Finalize) → disco de medios;
     **siempre privado**. Carpeta: la sección `support` del atleta.
   - Duración ≤ 180 s: si el servidor tiene ffprobe (`FFMPEG_BINARY` /
     `FFPROBE_BINARY`), se mide el archivo y manda la medición; si no, se usa
     la duración medida por el navegador, limitada a 180 s.
   - Bajo un bloqueo de la fila del enlace se vuelve a comprobar que siga
     activo, para que el último lugar no se entregue dos veces.
5. **Se notifica al atleta** con la infraestructura existente
   (`SendAthleteNotification` → bandeja + realtime + push por cada
   `PushDevice` activo). Tipo `training_cheer`; `target`
   `{type: "training", uuid: <uuid del mensaje>}`; la app abre
   **`finisherlegacy://training`** (también en `metadata.deep_link`). El
   `action_url` web es `/mi-entrenamiento`.
6. **El atleta escucha**: en la app (API abajo) o en `/mi-entrenamiento`
   (reproductor web: play/pausa, anterior/siguiente, velocidad 0.75/1/1.25/
   1.5/2, volumen, barra de posición). Filtros: todos / sin escuchar /
   favoritos. Por mensaje: favorito, quitar (ocultar) y bloquear remitente.

## API para la app móvil

Todas bajo `/api/v1`, con `auth:sanctum` + `user.token`. **Solo el dueño**:
cualquier uuid ajeno responde **404** (nunca 403). Envoltura estándar del API
(`{data, message, meta}`; `meta.request_id` siempre presente).

### `GET /training/messages?filter=all|unheard|favorites&page=`

20 por página, más recientes primero. `filter` inválido → 422.

```json
{
  "data": [
    {
      "uuid": "9b0c…",
      "sender_name": "Mamá",
      "relationship": "Mamá",
      "body": "¡Tú puedes, hijo!",
      "icon": "heart",
      "audio_url": "https://…/media/f/{uuid}/default?expires=…&signature=…",
      "duration_seconds": 42,
      "listened_at": null,
      "favorite": false,
      "created_at": "2026-11-10T18:20:00+00:00",
      "link": { "uuid": "1f3e…", "title": "Mi maratón" }
    }
  ],
  "message": null,
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "unheard_count": 1, "request_id": "…" }
}
```

- `audio_url` es una URL firmada temporal (`finisher.media.urls.private_ttl_minutes`,
  120 min por defecto, siempre ≥ 60). Es `null` si el mensaje es solo texto **o
  mientras el audio sigue procesándose en la cola** (unos segundos): la app
  debe volver a pedir la lista.
- La URL firmada sirve el audio sin sesión; sin firma, solo el atleta
  (sesión web) puede abrirlo.

### Acciones sobre un mensaje (todas → `204 No Content`)

| Método | Ruta | Cuerpo | Efecto |
|---|---|---|---|
| POST | `/training/messages/{uuid}/listened` | — | Marca escuchado (idempotente) |
| POST | `/training/messages/{uuid}/favorite` | `{"favorite": true}` | Marca/desmarca favorito |
| DELETE | `/training/messages/{uuid}` | — | Oculta el mensaje (sale de la lista) |
| POST | `/training/messages/{uuid}/block-sender` | — | Bloquea al remitente en ese enlace y oculta todos sus mensajes de ese enlace |

Un mensaje oculto ya no existe para la API (404).

### `GET /training/links`

```json
{ "data": [ { "uuid": "1f3e…", "title": "Mi maratón", "url": "https://finisherlegacy.com/animo/AbC…32", "expires_at": "2026-12-10T18:00:00+00:00", "revoked_at": null, "messages_count": 3 } ], "message": null, "meta": { "request_id": "…" } }
```

### `POST /training/links` → `201`

Cuerpo `{"title": "Mi maratón", "expires_in_days": 7 | 30 | 90 | null}`.
Respuesta `{"data": {uuid, title, url, expires_at, revoked_at, messages_count}}`.
Errores 422: `title` (requerido, ≤ 80), `expires_in_days` (solo 7/30/90/null),
o `title` con «Ya tienes muchos enlaces activos» al pasar de 20.

### `DELETE /training/links/{uuid}` → `204`

Desactiva el enlace al instante y para siempre. Los mensajes recibidos se
conservan.

## Web

| Ruta | Quién | Qué |
|---|---|---|
| `GET /animo/{token}` | Público | Página para dejar el mensaje (`training/Cheer`) |
| `POST /animo/{token}` | Público | Envío (multipart) |
| `GET /mi-entrenamiento` | Atleta | Enlaces + mensajes + reproductor (`training/Index`) |
| `POST /mi-entrenamiento/enlaces` · `DELETE …/enlaces/{uuid}` | Atleta | Crear / desactivar enlace |
| `POST /mi-entrenamiento/mensajes/{uuid}/escuchado` · `/favorito` · `/bloquear` · `DELETE …/mensajes/{uuid}` | Atleta | Acciones del reproductor |

Menú: «Mi entrenamiento» en el menú de cuenta (`resources/js/config/public-links.ts`).

## Límites y anti-abuso

- `cheer-open`: 60 vistas por minuto por IP.
- `cheer-submit`: 4 envíos por minuto y 20 por hora por IP, y 60 por hora por
  enlace (`config/training.php`). Al pasarse, el formulario muestra un error
  amable (no un 429 en blanco).
- Campo trampa (`website`) oculto para personas.
- Bloqueo por remitente: como el remitente no tiene cuenta, se bloquea el HMAC
  de su IP **para ese enlace** (`cheer_blocks`). Sus envíos posteriores ven la
  pantalla de éxito pero **no se guardan** — el bloqueo nunca se revela.

## Privacidad

- La IP del remitente **nunca se guarda en claro**: solo
  `HMAC-SHA256('cheer-ip|'+ip, APP_KEY)` en `cheer_messages.sender_ip_hash`,
  usado únicamente para límites y bloqueos. Nunca sale en la API ni en la web.
- El audio es un `MediaFile` **privado** cuyo dueño de dominio es el mensaje
  (`CheerMessage implements OwnsMediaFiles`): solo el atleta destinatario
  puede verlo; los clientes solo reciben URLs firmadas de corta duración.
  Nunca hay URL pública ni ruta del NAS.
- La página pública muestra únicamente el nombre de la cuenta del atleta y su
  foto si su perfil es público. Nunca correo, usuario ni otros datos.
- `/animo/*` es `noindex, nofollow` (meta + `X-Robots-Tag`) y no aparece en el
  sitemap.
- El personal no tiene una vista para escuchar estos mensajes: son privados
  entre quien los manda y el atleta. La moderación la hace el propio atleta
  (ocultar / bloquear). Ver «Pendiente».

## Datos

| Tabla | Columnas clave |
|---|---|
| `cheer_links` | `uuid`, `user_id`, `token` (32, único), `title` (≤ 80), `legacy_story_id` (opcional), `expires_at`, `revoked_at`, `max_messages`, `messages_count` |
| `cheer_messages` | `uuid`, `cheer_link_id`, `recipient_user_id`, `sender_name` (≤ 60), `relationship` (≤ 40), `body` (≤ 500), `icon`, `audio_media_file_id`, `duration_seconds`, `status` (`visible`/`hidden`), `listened_at`, `favorite`, `sender_ip_hash` |
| `cheer_blocks` | `user_id`, `cheer_link_id`, `sender_ip_hash` (único por enlace), `cheer_message_id` |

Migraciones `2026_11_10_600000…600200`, solo aditivas y reversibles.

## Pendiente

- **Moderación en Administración**: no se agregó a `/admin/community`. Esa
  pantalla trabaja sobre reportes públicos de la comunidad; los mensajes de
  ánimo son privados y no tienen reportes. Si se necesita, agregar una
  pestaña de solo conteos (mensajes por día, bloqueos) sin acceso al audio, y
  una acción de soporte para reactivar mensajes ocultos (`status = visible`).
- `legacy_story_id` existe en `cheer_links` pero aún no se expone para ligar
  un enlace a una historia Legacy.
- La duración solo se mide en el servidor si `FFMPEG_BINARY`/`FFPROBE_BINARY`
  están configurados; sin ffprobe se confía en la duración del navegador
  (limitada a 180 s) y en el límite de 10 MB.
