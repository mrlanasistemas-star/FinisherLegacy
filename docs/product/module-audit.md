# Auditoría de módulos — Finisher Legacy (2026-10)

Regla (§5): un módulo solo merece entrada propia en la navegación si alguien
necesita llegar a él **varias veces por semana**. Todo lo demás vive como
pestaña, sub-navegación, acción, drawer o página contextual. Nada se borra a
ciegas (§132/§136): las rutas retiradas redirigen (301) o siguen vivas por URL
directa, y la API se depreca sin romperse.

Decisiones:

- **KEEP** — se queda como destino de primer nivel o como pestaña visible.
- **MERGE** — su contenido vive dentro de otro espacio (pestaña / contexto).
- **HIDE** — fuera de la navegación; la ruta sigue funcionando por URL directa.
- **REMOVE-DEPRECATE** — la UI deja de ofrecerlo; la URL redirige; los datos
  históricos se conservan.

Frecuencia: **D** diaria · **S** varias veces por semana · **M** mensual o
menos · **E** solo el día del evento · **R** rara/técnica.

## 1. Administración (antes: 8 grupos y ~35 entradas en el sidebar)

Primer nivel final (§8/§133): **Inicio · Eventos · Comercio · Legacy + NFC ·
Comunidad · Personas · Configuración**. Cada elemento se muestra si la cuenta
puede entrar a *algo* de su interior.

| Módulo actual (URL) | Propósito | Usuario principal | Frec. | Decisión | Destino |
|---|---|---|---|---|---|
| Resumen `/admin` | KPIs del negocio | Dirección / operación | D | KEEP | **Inicio** (con bloque operativo "Hoy") |
| Eventos `/admin/editions` | Lista y alta de eventos | Operación de eventos | S | MERGE | **Eventos** → `/admin/events` (301 desde `/admin/editions`) |
| Detalle de evento `/admin/editions/{id}` | Datos, carreras, precios, Legacy Plate | Operación de eventos | S | MERGE | Pestaña **Resumen** de `/admin/events/{id}` (301) |
| Participantes `/admin/participants` | Corredores de un evento | Operación de eventos | S | MERGE | Pestaña **Participantes** `/admin/events/{id}/participants` (301 con `event_edition_id`; sin evento → `/admin/events`) |
| (no existía) Resultados | Tiempos y posiciones | Operación de eventos | S | MERGE | Pestaña **Resultados** `/admin/events/{id}/results` |
| Prerregistros `/admin/preregistrations` | Prerregistro + preventa Legacy Plate | Operación de eventos | S | MERGE | Pestaña **Prerregistros** `/admin/events/{id}/preregistrations` (301 con `event_edition_id`; la vista global queda por URL directa, fuera del menú) |
| Importaciones `/imports` | Carga CSV/XLSX de participantes | Operación de eventos | M | MERGE | Pestaña **Datos** del evento (historial + "Importar archivo"); `/imports` sigue vivo por URL |
| Fuentes del evento (tab "Datos" del detalle) | Qué fuente alimenta participantes/resultados | Operación de eventos | M | MERGE | Pestaña **Datos** `/admin/events/{id}/data` |
| (A8) Seguimiento en vivo | Tracking de participantes | Operación de eventos | E | KEEP | Pestaña **Tracking** `/admin/events/{id}/tracking` (página de A8) |
| Fotografías `/admin/photos` | Revisión de fotos de fotógrafos | Contenido | S | MERGE | Pestaña **Media** del evento (`/admin/events/{id}/media`) + pestaña "Fotos por revisar" en Eventos (cola global) |
| Operación del evento `/operator` | Consola del día del evento (placa rápida) | Staff de evento | E | HIDE | Fuera del menú (hardware/placa por QR); URL viva |
| Preparación del evento `/admin/events/{id}/production-setup` | Plantilla de placa + prueba QR | Producción | R | HIDE | Fuera de la lista de eventos (UX láser, §36); URL viva |
| Incidencias `/admin/incidents` | Incidencias de evento | Operación | R | HIDE | URL viva (ya estaba oculto) |
| Pedidos `/admin/orders` | Pedidos de la tienda | Comercio | D | MERGE | **Comercio** › Pedidos |
| Productos `/admin/products` | Catálogo | Comercio | S | MERGE | **Comercio** › Productos |
| Categorías `/admin/product-categories` | Categorías del catálogo | Comercio | M | MERGE | **Comercio** › Categorías |
| Inventario `/admin/inventory` | Stock por variante | Comercio | D | MERGE | **Comercio** › Inventario |
| Pagos `/admin/payments` | Pagos recibidos | Comercio | S | MERGE | **Comercio** › Ventas |
| Cupones `/admin/coupons` | Cupones | Comercio | M | MERGE | **Comercio** › Cupones |
| Ofertas `/admin/promotions` | Precios de oferta automáticos | Comercio | M | MERGE | **Comercio** › Ofertas |
| (A2) Comercio `/admin/comercio` | Resumen comercial (§11) | Comercio | D | KEEP | **Comercio** › Resumen (lo construye A2) |
| Personalización `/admin/legacy-plates/production` | Cola de placas por evento | Producción | D | MERGE | **Legacy + NFC** › Preparación y entrega (A6 renombra) |
| Legacy Plates `/admin/plates` | Placas generadas | Producción | S | MERGE | **Legacy + NFC** › Legacy Plates |
| Layouts de impresión `/admin/legacy-plate-models` | Diseños de placa | Producción | M | MERGE | **Legacy + NFC** › Diseños |
| Preventas `/admin/legacy-plates/presales` | Preventas sin participante | Producción | S | MERGE | **Legacy + NFC** › Preventas |
| (A6) Legacy + NFC `/admin/legacy-nfc` | Etiquetas NFC, asignaciones | Producción | S | KEEP | **Legacy + NFC** › Resumen (lo construye A6) |
| Vinculación NFC `/admin/legacy-codes` | Legacy Codes (QR histórico) | Producción | R | HIDE | Fuera del menú (QR = histórico, §130); URL viva |
| Plate Studio `/admin/plate-studio` | Moldes de grabado láser | Producción | R | HIDE | Fuera del menú (§36/§131); URL viva |
| Perfiles de máquina `/admin/machine-profiles` | Perfil láser | Técnico | R | HIDE | Fuera del menú (§36); URL viva |
| Estaciones `/admin/production-devices` | Emparejar estaciones de grabado | Técnico | R | HIDE | Fuera del menú (§36); URL viva |
| Producción `/production` | Estación de grabado (frente/reverso/QR) | Producción | R | HIDE | Fuera del menú; A6 lo convierte en "Preparación y entrega" |
| Comunidad `/admin/community` | Reportes y moderación | Moderación | S | KEEP | **Comunidad** (A5 agrega Reportes / Contenido moderado / Bloqueados) |
| Usuarios `/admin/users` | Cuentas, roles, estado | Administración | S | MERGE | **Personas** › Usuarios |
| Atletas `/admin/athletes` | Identidad canónica del atleta | Operación | S | MERGE | **Personas** › Atletas |
| Fotógrafos `/admin/photographers` | Alta y pagos a fotógrafos | Contenido | S | MERGE | **Personas** › Fotógrafos |
| Roles y permisos `/admin/roles` | Matriz de permisos | Super admin | M | MERGE | **Personas** › Roles |
| Conflictos de identidad `/admin/identity-conflicts` | Duplicados de atleta | Operación | M | MERGE | **Personas** › Duplicados (antes oculto) |
| Mensajes de contacto `/admin/messages` | Bandeja del formulario de contacto | Atención | S | MERGE | **Personas** › Mensajes |
| Nosotros y trayectoria `/admin/content` | Contenido del sitio | Marketing | M | MERGE | **Configuración** › Contenido del sitio |
| Organizadores `/admin/organizers` | Organizadores y sus fuentes | Operación | M | MERGE | **Configuración** › Organizadores |
| Fuentes de datos `/admin/data-sources` | Fuentes por organizador | Operación | M | MERGE | **Configuración** › Fuentes de datos |
| Integraciones `/admin/integrations` | Conexiones de resultados | Técnico | M | MERGE | **Configuración** › Conexiones |
| Configuración `/admin/settings` | Valores del sistema (solo lectura) | Administración | M | KEEP | **Configuración** › Ajustes (+ enlace a colas para super admin) |
| Auditoría `/admin/audit` | Bitácora de cambios | Administración | M | MERGE | **Configuración** › Auditoría |
| Horizon `/horizon` (A1) | Salud de colas | Técnico | R | HIDE | Solo un enlace en Configuración › Ajustes para super admin |

## 2. Área personal ("Mi espacio", §7/§134)

| Módulo actual (URL) | Propósito | Usuario | Frec. | Decisión | Destino |
|---|---|---|---|---|---|
| Mi Legado `/dashboard` | Historia del atleta | Atleta | S | KEEP | **Mi legado** |
| Mis eventos `/dashboard/my-events` | Participaciones | Atleta | S | KEEP | **Mis eventos** (A10 rediseña el contenido) |
| Mi equipo `/dashboard/my-gear` | Productos conectados | Atleta | S | KEEP | **Mi equipo** (A6 rediseña) |
| Mis pedidos `/mis-pedidos` | Compras | Atleta | S | KEEP | **Mis pedidos** |
| Mi perfil `/dashboard/profile` | Perfil público | Atleta | S | KEEP | **Mi perfil** |
| Notificaciones `/notifications` | Bandeja | Atleta | D | MERGE | Campana en la barra superior (A1) |
| Mis medallas `/dashboard/medals` | Medallas manuales | Atleta | M | MERGE | Dentro de Mi legado (URL viva) |
| Mis Legacy Plates `/dashboard/my-plates` | Placas propias | Atleta | M | MERGE | Dentro de Mi legado / Mi equipo (URL viva) |
| Mis fotos `/fotos` | Encontrar fotos | Atleta | M | MERGE | Grupo "Explorar" del sidebar (página pública) |
| Comunidad `/comunidad` | Feed social | Atleta | D | KEEP | Grupo "Explorar" (página pública, A5) |
| Tienda `/tienda` | Comprar | Atleta | S | KEEP | Grupo "Explorar" + pestaña móvil **Tienda** |
| Explorar eventos `/events` | Catálogo público | Atleta | S | KEEP | Grupo "Explorar" |
| Escanear QR (FAB móvil) | Escanear QR de prerregistro/Legacy Code | Atleta | R | REMOVE-DEPRECATE | Se quita de la UI (NFC abre directo, §29); `/l/{code}` sigue vivo |
| Apoyo `/support/{code}` | Mensajes de apoyo | Familia | E | KEEP | Contextual al evento (sin entrada de menú) |
| Portal del fotógrafo `/fotografo` | Subir y vender fotos | Fotógrafo | S | KEEP | Grupo "Fotógrafo" en Mi espacio |
| Configuración de cuenta `/settings/*` | Cuenta y seguridad | Todos | M | KEEP | Menú de usuario (pie del sidebar) |

## 3. Navegación móvil

- **Admin (≤5):** Inicio · Eventos · Comercio · Personas · Más (hoja con
  Legacy + NFC, Comunidad, Configuración, Mi espacio, cuenta).
- **Atleta (5):** Legado · Eventos · Tienda · Pedidos · Perfil. El resto
  (Mi equipo, Comunidad…) queda en el sidebar (botón de menú de la barra
  superior). Sin pestaña de escáner QR.

## 4. Búsqueda global (⌘K, §111)

Busca solo: **evento, atleta, participante, pedido, producto** — 5 resultados
por tipo, término de 2–60 caracteres, cada tipo filtrado por el permiso que
permite abrirlo, `throttle:60,1`. No es una búsqueda SQL universal.

## 5. Redirecciones de URLs retiradas (301)

| URL vieja | Nuevo destino |
|---|---|
| `/admin/editions` | `/admin/events` |
| `/admin/editions/create` | `/admin/events/create` |
| `/admin/editions/{id}` | `/admin/events/{id}` |
| `/admin/participants` (sin evento) | `/admin/events` |
| `/admin/participants?event_edition_id={id}` | `/admin/events/{id}/participants` |
| `/admin/preregistrations?event_edition_id={id}` | `/admin/events/{id}/preregistrations` |

Siguen funcionando sin redirección (fuera del menú): `/imports`,
`/admin/data-sources`, `/admin/integrations`, `/admin/preregistrations`
(vista global), `/operator`, `/production`, `/admin/legacy-codes`,
`/admin/plate-studio`, `/admin/machine-profiles`, `/admin/production-devices`,
`/admin/incidents`, `/dashboard/medals`, `/dashboard/my-plates`.
