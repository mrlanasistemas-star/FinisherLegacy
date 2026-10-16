# Mapa de módulos

Fuente única de navegación: `resources/js/config/navigation.ts` (sidebar,
pestañas de espacio de trabajo, barra inferior móvil y ⌘K). Detalle y
razones de cada decisión: `docs/product/module-audit.md`.

## Mi espacio (atleta)

| Menú | URL |
|---|---|
| Mi legado | `/dashboard` |
| Mis eventos | `/dashboard/my-events` |
| Mi equipo | `/dashboard/my-gear` |
| Mis pedidos | `/mis-pedidos` |
| Mi perfil | `/dashboard/profile` |
| Notificaciones | campana en la barra superior |

Grupo "Explorar": Comunidad, Eventos, Tienda, Fotos (páginas públicas).
Fotógrafos ven además su portal (`/fotografo`).
Móvil: **Legado · Eventos · Tienda · Pedidos · Perfil**.

## Administración (7 secciones)

| Sección | Entrada | Pestañas |
|---|---|---|
| Inicio | `/admin` | — (bloque "Hoy": eventos de la semana, preparación, reportes) |
| Eventos | `/admin/events` | Eventos · Fotos por revisar |
| Comercio | `/admin/comercio` | Resumen · Productos · Inventario · Pedidos · Ventas · Cupones · Ofertas · Categorías |
| Legacy + NFC | `/admin/legacy-nfc` | Resumen · Preparación y entrega · Legacy Plates · Preventas · Diseños |
| Comunidad | `/admin/community` | Moderación |
| Personas | primera pestaña permitida | Usuarios · Atletas · Fotógrafos · Duplicados · Mensajes · Roles |
| Configuración | `/admin/settings` | Ajustes · Contenido del sitio · Organizadores · Fuentes de datos · Conexiones · Auditoría |

Una sección aparece si la cuenta tiene `dashboard.admin.view` y puede abrir
al menos una de sus pestañas. Móvil: **Inicio · Eventos · Comercio ·
Personas · Más**.

### Espacio de trabajo de un evento — `/admin/events/{id}`

Resumen · Participantes (`/participants`) · Resultados (`/results`) ·
Prerregistros (`/preregistrations`) · Datos (`/data`: fuentes, sincronización,
importaciones) · Tracking (`/tracking`) · Media (`/media`).

### Búsqueda global (⌘K)

`GET /admin/search?q=` — eventos, atletas, participantes, pedidos y
productos; 5 por tipo, solo los tipos que el usuario puede abrir,
`throttle:60,1`.

## Ocultos (la URL sigue funcionando)

`/operator`, `/production`, `/admin/legacy-codes`, `/admin/plate-studio`,
`/admin/machine-profiles`, `/admin/production-devices`, `/admin/incidents`,
`/imports`, `/admin/preregistrations` (vista global), `/dashboard/medals`,
`/dashboard/my-plates`. Horizon (`/horizon`) solo como enlace para super
admin en Configuración › Ajustes.

## Redirecciones 301

`/admin/editions` → `/admin/events` · `/admin/editions/{id}` →
`/admin/events/{id}` · `/admin/editions/create` → `/admin/events/create` ·
`/admin/participants[?event_edition_id=]` → evento › Participantes (o
`/admin/events`) · `/admin/preregistrations?event_edition_id=` → evento ›
Prerregistros.
