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

## Administración (Commerce + Activation OS, 2026-10-27)

Secciones por dominio de negocio (`adminSections`); Catálogo / Inventario /
Tags vienen de `resources/js/config/admin-catalog-links.ts`. Detalle en
`docs/architecture/commerce-os.md` §Administración comercial y operación.

| Sección | Entrada | Pestañas |
|---|---|---|
| Inicio | `/admin` | — (ventas, canales, inventario, activación, producto, colecciones, KPI venta rápida + "Hoy") |
| Comercial | `/admin/orders` | Pedidos (+ Pagos) · Venta rápida `/admin/venta-rapida` · Ventas `/admin/comercio/ventas` · Aliados `/admin/aliados` · Cupones y ofertas |
| Catálogo | `/admin/products` | Productos · Colecciones `/admin/catalogo/colecciones` · Categorías |
| Inventario | `/admin/inventario/existencias` | Existencias · Ubicaciones · Lotes · Transferir · Proveedores (`/admin/comercio/inventario` y `/admin/inventory` siguen funcionando) |
| Legacy | `/admin/tags` | Tags · Activaciones `/admin/activaciones` · Etiquetas NFC · Productos conectados |
| Soporte | `/admin/soporte` | Incidencias (reemplazos, devoluciones, defectos, propiedad) |
| Eventos | `/admin/events` | Eventos · Fotos por revisar |
| Comunidad | `/admin/community` | Moderación |
| Personas | primera pestaña permitida | Usuarios · Atletas · Fotógrafos · Duplicados · Mensajes · Roles |
| Configuración | `/admin/settings` | Ajustes · Contenido del sitio · Organizadores · Fuentes de datos · Conexiones · Auditoría |
| Operación (histórico) | `/admin/plates` | Legacy Plates · Preparación y entrega · Preventas · Diseños · Plate Studio · Perfiles de máquina · Estaciones · Legacy Codes · Incidencias de evento · Importaciones · Event OS — colapsada al final del sidebar ("Histórico"), solo con sus permisos |

Una sección aparece si la cuenta tiene `dashboard.admin.view` y puede abrir
al menos una de sus pestañas. Móvil (`adminMobileTabs`, según permisos):
**Inicio · Venta rápida · Pedidos · Tags · Más** (Más = todas las secciones).

`/admin/comercio` (Resumen comercial) sigue respondiendo; ya no está en el
menú (lo reemplaza Inicio).

### Portal del aliado — `/aliado` (`ally.portal`)

Sin panel de administración. Sidebar "Trabajo": Portal aliado · Venta rápida
(`/aliado/venta-rapida`, solo ubicaciones de su aliado). Ve únicamente su
inventario, sus ventas y sus incidencias (`ally_user`).

### Espacio de trabajo de un evento — `/admin/events/{id}`

Resumen · Participantes (`/participants`) · Resultados (`/results`) ·
Prerregistros (`/preregistrations`) · Datos (`/data`: fuentes, sincronización,
importaciones) · Tracking (`/tracking`) · Media (`/media`).

### Búsqueda global (⌘K)

`GET /admin/search?q=` — eventos, atletas, participantes, pedidos y
productos; 5 por tipo, solo los tipos que el usuario puede abrir,
`throttle:60,1`.

## Ocultos (la URL sigue funcionando)

Ahora en "Operación (histórico)" para quien tenga permiso: `/operator`,
`/production`, `/admin/legacy-codes`, `/admin/plate-studio`,
`/admin/machine-profiles`, `/admin/production-devices`, `/admin/incidents`,
`/imports`. Sin enlace: `/admin/preregistrations` (vista global), `/dashboard/medals`,
`/dashboard/my-plates`. Horizon (`/horizon`) solo como enlace para super
admin en Configuración › Ajustes.

## Redirecciones 301

`/admin/editions` → `/admin/events` · `/admin/editions/{id}` →
`/admin/events/{id}` · `/admin/editions/create` → `/admin/events/create` ·
`/admin/participants[?event_edition_id=]` → evento › Participantes (o
`/admin/events`) · `/admin/preregistrations?event_edition_id=` → evento ›
Prerregistros.
