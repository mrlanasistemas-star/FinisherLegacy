# Rediseño LIGHT + ecosistema deportivo (2026-10)

Resumen técnico de lo que agregó el rediseño. Nada existente se eliminó:
rutas, controladores, permisos y páginas previas siguen funcionando.

## Sistema visual

- Un solo tema **claro** (`resources/css/app.css`): fondo `#F8F7F3`, tarjetas
  blancas, texto `#171714`, dorado `#C9A45C` (relleno/acentos) y
  `fl-gold-ink` `#85662B` (dorado legible como texto). No hay dark mode, ni
  selector, ni lectura de `prefers-color-scheme`; `dark:` está anclado a una
  clase que nunca se aplica. `/settings/appearance` redirige a
  `/settings/profile`.
- Tipografía: Instrument Sans (interfaz) + Fraunces (`font-serif`,
  titulares editoriales). Primitivas: `.fl-eyebrow`, `.fl-display`,
  `.fl-card`, `.fl-container`.

## Páginas públicas nuevas / rediseñadas

| Ruta | Controlador | Notas |
|---|---|---|
| `/` | `HomeController` | Hero, disciplinas (tabla `sports`), comunidad, eventos, tienda |
| `/comunidad`, `/comunidad/publicaciones/{uuid}` | `CommunityController` | Red social sobre Legacy Moments (ver `social.md`) |
| `/atletas/{username}/seguir` (POST/DELETE) | `CommunityController` | Seguir / dejar de seguir |
| `/nosotros` | `AboutController` | Todo editable desde admin |
| `/contact` (GET/POST) | `ContactController` | Guarda en `contact_messages`; no envía correo |
| `/fotos` | `PhotosController` | Búsqueda evento + número sobre `AthleteEventMedia` públicas |
| `/buscar` | `SearchController` | Atletas (privacidad respetada), eventos, productos |

## Administración

| Ruta | Permiso |
|---|---|
| `/admin/content` (Nosotros, trayectoria, galería) | `content.manage` |
| `/admin/messages` (bandeja de contacto) | `content.manage` |
| `/admin/community` (reportes y moderación) | `community.moderate` |
| `/admin/photos` (resumen de fotos de evento) | `media.manage` |
| `/admin/product-categories` | `products.manage` |
| `PATCH /admin/products/media/{media}`, `POST …/replace` | `products.manage` |

Los permisos nuevos están en `config/permissions.php` y además se crean
(y se asignan al rol `admin`) en la migración
`2026_10_06_090700_create_content_and_community_permissions`.

## Tablas / columnas nuevas (migraciones aditivas)

`company_settings`, `company_milestones`, `company_gallery_items`,
`contact_messages`, `products.availability|tagline|sort_order`,
`product_media.is_hover`, `product_categories.description|sort_order`.

`products.availability` (`available` · `coming_soon` · `concept`) es
independiente de `status`: un producto publicado puede mostrarse como
"Próximamente" o "Concepto", pero `AddCartItem` y `CheckoutCart` sólo
venden `available` (`Product::isPurchasable()`).

## Datos de ejemplo (opcionales)

`php artisan db:seed --class=ConceptProductCatalogSeeder` agrega productos
conceptuales (visera, jersey, gorra, backpack, llavero NFC…) sin precio ni
stock. No se ejecuta con `migrate` ni con `DatabaseSeeder`. No hay seeders
de publicaciones ni de trayectoria: esa información debe ser real.

## Pendiente (sin backend todavía — la UI lo indica explícitamente)

- Carga de fotos por fotógrafos, etiquetado por número y venta de fotos.
- Búsqueda por selfie / reconocimiento facial (opcional, requiere consentimiento).
- Visibilidad "Familia / equipo de apoyo" para publicaciones (hoy existe
  sólo dentro de "Mi equipo de apoyo" por evento).
- Videos subidos directamente en publicaciones (el pipeline de
  `CreateMoment` sólo procesa fotos).
