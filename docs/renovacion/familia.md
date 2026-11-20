# Familia y enlaces de seguimiento

<!-- Contactos autorizados del atleta y ubicación en vivo compartida: modelo, privacidad, API, web y app. -->

## Idea central

- **Familia** = personas en las que el atleta confía ("Mamá", "Mi pareja").
  Es distinto de un **seguidor** (`athlete_follows`), que solo ve el perfil público.
- **Un vínculo familiar NO da acceso a la ubicación.** Solo vuelve a la persona
  elegible para recibir un **compartir explícito**.
- La ubicación se comparte **solo** cuando el atleta lo decide, para una sesión
  de seguimiento en curso, eligiendo **quién** (toda la familia, algunas
  personas y/o un enlace público) y **por cuánto tiempo** (hasta que termine,
  2 h, 6 h o 24 h). Siempre termina, a más tardar, cuando se detiene la sesión.

## Datos

| Tabla | Qué guarda |
| --- | --- |
| `athlete_family_links` | Invitación atleta → persona. `status` pending/accepted/declined/revoked. `contact_user_id` nulo mientras una invitación por enlace no se acepta. El token del enlace se guarda solo como SHA-256 y se borra al usarse (dura 7 días). |
| `tracking_shares` (extendida) | `audience` `link` (página pública `/live/{token}`) o `family` (una fila por persona; se lee con sesión, su token nunca se entrega). `ends_with_session` + `ended_at`: al detener la sesión se cierran. |

Estados de un compartir: usable · `revoked_at` (el atleta lo quitó → **404**) ·
`ended_at` o vencido (terminó → **410**). La regla vive en
`TrackingShare::usable()` / `isUsable()`: la usan la página pública, la API de
familia y el envío en tiempo real (`IngestTrackingPoints` solo transmite a
compartidos usables). Al cerrarse se envía `.tracking.ended` al canal
`live.{channel_key}` y la página quita el mapa.

## Minimización de datos

Quien mira recibe solo la sesión compartida: última posición, recorrido
simplificado (≤ 500 puntos, ~1 m) y estado `waiting · active · paused · stopped`
(`paused` = sin punto nuevo en `tracking.paused_after_seconds`, 180 s). Con la
sesión detenida no se envía **ninguna** posición (ni último punto, ni recorrido,
ni batería). Nunca correos, dorsal, ids ni precisión GPS.

## API (`/api/v1`, Sanctum)

| Método | Ruta | Quién |
| --- | --- | --- |
| GET | `family` | Yo: `links` (mi familia), `invitations` (me invitaron), `memberships` (soy familia de), `following_count` |
| POST | `family/links` `{email?, label?}` | Con correo invita a esa cuenta; sin correo devuelve `invite_url` (una sola vez) |
| PATCH | `family/links/{uuid}` `{label}` | Dueño |
| DELETE | `family/links/{uuid}` | Dueño (quitar) o contacto (salir); revoca sus compartidos |
| POST | `family/links/{uuid}/accept` · `/decline` | La cuenta invitada |
| GET/POST | `family/invites/{token}` · `/accept` · `/decline` | Quien abre el enlace (con sesión) |
| GET | `family/following` | Lo que me comparten ahora ("Siguiendo a…") |
| GET | `family/following/{shareUuid}` | Instantánea en vivo (404 sin acceso, 410 terminado) |
| GET | `me/tracking/sharing` | Mis sesiones en curso con sus compartidos + mi familia aceptada |
| GET/POST/DELETE | `tracking/sessions/{uuid}/sharing` | Ver · compartir `{family: all\|selected\|none, family_link_ids[], public_link, duration: until_stop\|2h\|6h\|24h}` · dejar de compartir todo |

Público: `GET live/{token}` (404 revocado/desconocido, 410 terminado).

## Web

- `/live/{token}` — página pública ligera (mapa, estado En curso/Pausado/Finalizado,
  "hace 2 min"), 404/410 sin datos, botón "Abrir en la app".
- `/familia/invitacion/{token}` — vista previa; aceptar/rechazar con sesión
  (invitado → `/entrar?volver=…`).

## App

- Perfil → Configuración → **Familia** (`/family`): invitar, etiqueta, quitar,
  responder invitaciones, "Siguiendo a…".
- **Quién ve mi ubicación** (`/family/sharing`): también desde la pantalla de
  seguimiento en vivo. Compartir, dejar de compartir, quitar a una persona.
- Visor (`/family/following/{uuid}`, `/live/{token}`).
- Enlaces: `https://…/familia/invitacion/{token}` → `/family/invite/{token}`,
  `https://…/live/{token}` → `/live/{token}`.

## Notificaciones (categoría Avisos, push incluido)

`family_invite` (te invitaron) · `family_accepted` (aceptaron) ·
`family_tracking_started` (empezó a compartir contigo; target `tracking` + uuid
del compartir).

## Entrenamientos en vivo

`ShareTrackingSession` trabaja sobre un `TrackingSession`. Cuando un
entrenamiento transmita puntos en vivo debe abrir (o tener) una sesión de
seguimiento y llamar a la misma acción: familia, página pública y reglas de
privacidad aplican igual.
