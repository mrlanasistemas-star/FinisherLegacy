<!-- Mirror of FinisherLegacyMobile/docs/SOCIAL_ARCHITECTURE.md — keep both in sync. -->
# Arquitectura social — Legacy Moments

> Estado: **implementado** (backend `FinisherLegacy` + esta app). Reemplaza la sección "COMUNIDAD / LEGACY MOMENTS" de `MOBILE_BACKEND_REQUIREMENTS.md` (ya obsoleto).

Finisher Legacy no es un chat ni otra red de fotos: toda interacción cuelga de **actividad deportiva** — una carrera terminada, una medalla, un entrenamiento, un recuerdo, un gear. No hay DMs.

## Modelo de datos (backend)

| Tabla | Qué guarda | Notas |
|---|---|---|
| `athlete_follows` | `follower_id → following_id` (users) | `unique(follower_id, following_id)`, sin self-follow (Action) |
| `legacy_moments` | autor, `type`, `caption`, `visibility`, refs opcionales a `event_participant_id` / `medal_id` / `athlete_owned_product_id`, `metrics` JSON | soft delete; índices `(user_id, created_at)`, `(visibility, created_at)` |
| `legacy_moment_media` | referencia a `athlete_event_media` **o** foto subida con el momento | nunca duplica archivos de evento |
| `legacy_moment_reactions` | `like` / `cheer` | `unique(moment, user, type)` → toggle idempotente |
| `legacy_moment_comments` | "mensajes de apoyo" planos (sin respuestas anidadas) | soft delete |
| `user_blocks` | `blocker_id → blocked_id` | bloquear elimina follows en ambos sentidos |
| `reports` | `reporter_id`, `target_type` (profile/moment/comment), `target_id`, `reason`, `status` (open/reviewing/resolved/dismissed) | base de moderación; un reporte por persona/objetivo |

`type`: `race_completed · medal_claimed · memory · training · personal_record · gear · manual`.
`metrics` (sólo entrenamientos/PR): `title`, `distance_km`, `duration_seconds`, `pace` (**calculado en el servidor**), `is_personal_record`. No hay GPS: es un momento, no un tracker.

## Privacidad — una sola regla, en el servidor

`App\Services\Social\SocialVisibility` es la **única** fuente de verdad. Feed, perfil, búsqueda, explorar, acceso directo a un momento y comentarios la usan; el cliente nunca decide.

1. **Perfil privado** → sólo su dueño lo ve (y sus momentos). No se le puede seguir.
2. **Bloqueo** (en cualquier dirección) → ninguno ve al otro: perfil, momentos, comentarios, búsqueda.
3. Encima, cada momento: `public` (cualquiera que pueda ver el perfil) · `followers` (sólo seguidores) · `private` (sólo el autor).
4. Lo que no se puede ver responde **404**, nunca 403 — no se revela que existe.

Policies: `LegacyMomentPolicy` (view / interact / update / delete), `LegacyMomentCommentPolicy` (borra el autor del comentario **o** el autor del momento). `super_admin` pasa todo (moderación).

## API (`/api/v1`, todo requiere `auth:sanctum` salvo `GET /athletes/{username}`)

| Método | Ruta | Uso |
|---|---|---|
| GET | `feed?scope=following\|discover&cursor=` | tus momentos + los de quienes sigues (o todos los públicos). Cursor pagination (`meta.next_cursor`) |
| GET | `explore` | atletas recomendados, momentos públicos recientes, próximos eventos |
| GET | `search?q=&type=all\|athletes\|events\|products` | búsqueda global (mín. 2 letras, rate-limited) |
| POST | `moments` | crear (JSON o multipart con `photos[]`), `Idempotency-Key` |
| GET/PATCH/DELETE | `moments/{uuid}` | detalle / editar caption+visibilidad / borrar |
| PUT/DELETE | `moments/{uuid}/reactions/{like\|cheer}` | reacción idempotente → `{reactions, my_reactions}` |
| GET/POST | `moments/{uuid}/comments` | paginado `{data,links,meta}`; POST con `Idempotency-Key` |
| DELETE | `comments/{uuid}` | |
| POST/DELETE | `athletes/{username}/follow` | → `{is_following, followers_count, following_count}` |
| GET | `athletes/{username}/followers\|following\|moments` | listas con `is_following` del viewer |
| POST/DELETE | `athletes/{username}/block`, GET `me/blocks` | |
| POST | `reports` | `{target_type, target (username\|uuid), reason, details?}` |
| GET | `athletes/{username}` | perfil público + `stats` (medallas, carreras, seguidores, siguiendo, momentos) + `viewer` + `recent_moments` + `recent_events` + `medals` |

Rate limits: `social-write` 40/min, `social-comment` 10/min y 120/h, `search` 60/min, `reports` 5/min.
N+1: `App\Queries\Social\MomentQuery::withCardData()` carga autor, media, evento/carrera/resultado, medalla, gear, conteos de reacciones/comentarios y las reacciones del viewer en un número fijo de queries por página.

## Notificaciones

`new_follower`, `moment_reaction` (una por persona y momento, nunca por tus propias acciones), `moment_comment`. Canal **database** (push queda apagado hasta que exista un proveedor real — ver `RELEASE_CHECKLIST.md`). `GET me/notifications` expone `target: {kind, username?, moment_uuid?}` para que la app navegue sin parsear URLs, y `meta.unread_count` para el badge.

## Momentos sugeridos (nunca automáticos)

La app **ofrece** "Compartir como Legacy Moment" después de: ver/terminar una carrera, reclamar una medalla, reclamar gear y subir una foto/video de evento. Siempre abre el compositor prellenado (`/moments/create?participantId=…|medalUuid=…|gearUuid=…|mediaUuids=…`); el atleta escribe y publica. No se publica nada solo.

## App

- Pantallas: `feed`, `explore`, `moments/[uuid]` (detalle + mensajes con input fijo), `moments/create`, `athlete/[username]` y `athlete/[username]/connections`, perfil propio en la pestaña Perfil (`components/profile/profile-view.tsx`, compartido).
- Hooks: `src/hooks/use-social.ts`. Claves: `['feed', scope]`, `['explore']`, `['athlete', u]`, `['athlete', u, 'moments']`, `['moment', uuid]`, `['moment', uuid, 'comments']`, `['followers', u]`, `['following', u]`, `['blocks']`.
- Optimista: reacciones y follow se actualizan al instante en **todas** las listas donde aparece el momento/perfil y se revierten si el servidor dice que no (`features/social/moment-state.ts`, con tests).
- Offline: toda escritura social falla de inmediato con un mensaje claro (`utils/network.ts`), nunca un "éxito" falso. Lectura: feed/perfil/momentos quedan en caché persistida 24 h.

## URLs

| Web | App |
|---|---|
| `finisherlegacy.com/@username` (ya existe en web) | `finisherlegacy://athlete/username` |
| `finisherlegacy.com/comunidad/publicaciones/{uuid}` (web, CommunityController) | `finisherlegacy://moments/{uuid}` |

`src/app/+native-intent.tsx` + `features/links/map-incoming-path.ts` traducen ambos formatos (y `/reset-password/{token}`) a rutas de la app. Para que los enlaces `https://` abran la app hace falta configurar Universal Links / App Links (archivos `apple-app-site-association` y `assetlinks.json` en el dominio) — ver `RELEASE_CHECKLIST.md`.

## Web (Comunidad)

`/comunidad` (feed Para ti / Siguiendo / Eventos), `/comunidad/publicaciones/{uuid}` (detalle + mensajes de apoyo) y seguir/dejar de seguir desde `/@username` — todo en `App\Http\Controllers\CommunityController`, que reutiliza las mismas Actions, `MomentQuery`/`FeedQuery`, `SocialVisibility` y policies que la API. Una sola reacción visible: "Celebrar" (`cheer`); quitarla borra cualquier reacción propia (también un `like` del app).

## Comunidad v1 (2026-10, spec §52–59)

- **Tipos**: `MomentType` suma `photo`, `legacy_plate`, `text`. La UI muestra 6 familias (`PostKind`: moment, race_result, photo, gear, legacy_plate, text) derivadas del tipo — `MomentResource.kind` / `kind_label`. Sin polimorfismo.
- **Evento relacionado**: `legacy_moments.event_edition_id` (se deriva de la participación si no se envía) → `MomentResource.event`.
- **Feed** (`App\Queries\Social\FeedQuery`): `following` (default API), `discover`, `for_you` (día más reciente primero; dentro del día: tú + a quien sigues +2, eventos en los que corriste +1) y `events` (solo con evento). Cursor estable (`meta.next_cursor`). Web usa `Inertia::scroll()` + `<InfiniteScroll>`.
- **Perfil "Solo seguidores"** (`ProfileVisibility::Followers`): contenido solo para seguidores; con sesión se ve el encabezado y se puede seguir (`restricted: true` en web y en `GET /athletes/{username}`); nunca aparece en búsqueda/explorar.
- **Web**: reportar (`POST /comunidad/reportes`), bloquear/desbloquear (`/atletas/{username}/bloquear`), listas `/@{username}/seguidores|siguiendo`, bloqueados en `/comunidad/conexiones?tab=bloqueados`.
- **Realtime**: `community.post.created` en canal público `community.feed` (solo publicaciones públicas de perfiles públicos, sin contenido) y `community.comment.created` en `private-App.Models.User.{autor}`.
- **API nueva**: `GET /api/v1/community/meta`; `reactions.total` en reacciones. Video en publicaciones: no (solo fotos).

## Moderación

Admin → Comunidad (`/admin/community?tab=reportes|contenido|bloqueos`). `hidden_at/hidden_by` en publicaciones y comentarios: oculto = solo lo ve su autor; restaurable. Ocultar/restaurar/resolver/eliminar se registra en `activity_log` (`log_name=community`) vía `App\Actions\Social\ModerateContent`; ocultar cierra los reportes abiertos de ese objetivo.
