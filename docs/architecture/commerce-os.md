# Commerce + Product + Activation OS (2026-10-27)

## Antes → Ahora

**Antes:** placa (Legacy Plate) personalizada y grabada en el evento →
captura → grabado frente/reverso → verificación → entrega. El admin giraba
alrededor de eventos, Plate Studio, producción y dispositivos.

**Ahora:** **Finisher Legacy Tag** terminado de fábrica (diseño por colección,
correa, NFC, URL única, QR de respaldo, código) → cobro → entrega inmediata →
**activación digital** → el atleta construye su recuerdo. La plataforma es el
valor; el objeto es la puerta.

```
Lote (fábrica) → inventario (ubicación) → venta (canal) → entrega → activación → atleta → participación → recuerdo
```

## Decisión placa → tag (§63)

`plates` representa **A**: una placa fabricada/grabada en sitio, 1:1 con
`legacy_codes`, `production_jobs`, plantillas y snapshot de layout. No se
renombra ni generaliza. Se introduce **`legacy_tags`** = unidad física
individual de cualquier producto serializado (`products.tracks_units`). Hoy:
el tag; mañana: llavero, pulsera, portarretrato NFC (Smart Frame) — todos son
`Product` + `ProductVariant` + `LegacyTag`, sin `product = metal_plate`.

`nfc_tags` (chips con `/n/{token}`) sigue sirviendo a placas y productos
conectados existentes. Un `LegacyTag` puede enlazar opcionalmente su chip
(`nfc_tag_id`), pero **su URL es `/t/{public_code}`**.

## Tablas nuevas / extendidas

| Tabla | Qué es |
|---|---|
| `collections` | Colección (destino, disciplina, evento, año, edición, temporada, estado, lanzamiento, portada/hero, SEO). Pública si `status=active` y `launch_at` pasado. |
| `products` (+) | `collection_id`, `tracks_units`, `material`, `strap_type`, `dimensions`, `instructions`, `seo_*`. Nuevo `ProductType::LegacyTag`. |
| `suppliers` | Proveedor simple (contacto, país, lead time, MOQ). |
| `allies` + `ally_user` | Empresa aliada (no es un usuario); sus vendedores se ligan por `ally_user`. Comisión/condiciones son datos. |
| `inventory_locations` (+) | `type` (warehouse/ally/event/stand/transit), `ally_id`, `event_edition_id`. |
| `inventory_lots` | Lote de fabricación por SKU (proveedor, costo, fechas, incidencias). |
| `legacy_tags` | **Unidad física**: `serial` interno, `public_code` aleatorio (12 chars, alfabeto sin ambigüedad ≈59 bits), SKU, lote, ubicación, estado, canal, aliado, pedido/ítem, dueño, `athlete_owned_product_id`, `replaces_tag_id`, fechas venta/entrega/activación, contadores de apertura. |
| `legacy_tag_events` | Historial append-only por unidad (kardex de pieza). |
| `legacy_tag_scans` | Analítica sin PII: `tag_opened`, `activation_started`, `activation_completed`, `legacy_viewed`, `share_opened` × fuente `nfc`/`qr`/`code`/`link`. Sin IP ni UA; hash diario. Prune 365 d. |
| `orders` (+) | `sales_channel` (direct/ally/own_event/online), `ally_id`, `inventory_location_id`, `seller_user_id`, `delivery_method`, envío/tracking, `sale_started_at` (KPI ≤90 s). |
| `product_price_schedules` (+) | `sales_channel`, `ally_id` → precio por canal/aliado. La tienda online solo ve precios sin canal. |
| `legacy_stories` | El recuerdo que abre un tag (1:1 opcional con tag). Participación real o datos libres, dedicatoria, destinatario, visibilidad `public`/`unlisted`/`private`. |
| `legacy_story_media` | Fotos/audio/certificado → `media_files` (MediaStorage). |
| `legacy_story_shares` | Enlace de destinatario ("Para mamá"), revocable; abre aunque la historia sea privada. |
| `support_cases` | Incidencias: activación, NFC, QR, defecto, devolución, reemplazo, propiedad. |

Migraciones: `2026_10_27_1000xx_*` — solo aditivas, reversibles, sin drops.

## Estados del tag (`App\Enums\LegacyTagStatus`)

```
manufactured → in_stock ⇄ reserved → sold → delivered → activated
                     ↘ returned (→ in_stock al reponer) · replaced · disabled
```

- Solo `App\Actions\LegacyTags\*` cambia estados, siempre vía
  `App\Services\LegacyTags\TagLifecycle` (escribe `legacy_tag_events`) y
  `activity_log`.
- Activable: `sold`, `delivered`; además `in_stock` en ubicación **de campo**
  (ally/event/stand) si `legacy.tags.activate_unreported_field_sales` → se
  registra la venta inferida (`metadata.unreported_sale`) para conciliar.
  Stock de almacén/tránsito **nunca** activa (evita que alguien reclame una
  pieza en bodega).

## Acciones (`app/Actions/LegacyTags`)

| Acción | Uso |
|---|---|
| `CreateLot` | Registra lote y genera N tags `manufactured` (códigos listos para codificar NFC e imprimir QR). |
| `ReceiveLot` | Lote recibido → `in_stock` en una ubicación; defectuosos → `disabled`. Stock agregado vía `InventoryService::receive`. |
| `TransferTags` | Unidades o cantidad FIFO entre ubicaciones (`InventoryService::transfer`, movimientos `transfer_out`/`transfer_in`). |
| `RecordInPersonSale` | Venta rápida (POS, aliado, stand): pedido pagado/surtido/completado con canal, ubicación, vendedor; pago manual; `sellDirect`; piezas `delivered`. |
| `AssignTagsToOrderItem` | Pedido online: al surtir (`FulfillOrderItem`) liga las piezas exactas; envío → `sold`, entrega confirmada → `delivered`. |
| `ActivateTag` | Reclamo: lock + `lockForUpdate`, idempotente para el dueño, `TAG_ALREADY_ACTIVATED` (409) para otro, `TAG_NOT_ACTIVATABLE` (422, `reason`: disabled/replaced/returned/not_sold). Crea `AthleteOwnedProduct` (Mi equipo / `/api/v1/me/gear` siguen funcionando) y la `LegacyStory` (no listada). |
| `ManageTag` | `replace`, `return` (revoca dueño, la historia se conserva), `revokeOwnership`, `disable`/`enable`. |
| `RecordTagScan` | Analítica privada. `?src=qr` = QR; sin parámetro = NFC. |

`InventoryService` ganó `sellDirect()` (venta sin reserva, nunca toca
reservas ajenas) y `transfer()`. `ResolveProductPrice::handle()` acepta
`$channel`, `$allyId`.

## URLs públicas

| URL | Qué hace |
|---|---|
| `/t/{code}` | Lo que lleva el chip NFC; el QR lleva `/t/{code}?src=qr`. Resuelve estado → historia, activación o página honesta. |
| `/activar` | Escribir el código impreso (Legacy Code). |
| `/activar/{code}` | Flujo de activación (login/registro → reclamar → completar recuerdo). |
| `/legacy/{publicId}` | Historia (según visibilidad). |
| `/legacy/para/{token}` | Enlace de destinatario. |
| `/mi-legacy` | Biblioteca de recuerdos del atleta. |
| `/colecciones`, `/colecciones/{slug}` | Catálogo por colección. Producto: `/tienda/{slug}` (se conserva). |

Throttle `tag-open` por IP (30/min + 300/h). Códigos nunca incrementales.

## Roles

`sales_staff` (Vendedor: `pos.sell`, `tags.view`, `orders.view`,
`support.*`), `ally` (`ally.portal`: solo inventario/ventas de su aliado vía
`ally_user`). Permisos nuevos: `tags.view|manage`, `allies.view|manage`,
`support.view|manage`, `pos.sell`, `ally.portal`.

## Riesgos conocidos

- Activación inferida en aliado: alguien con acceso al mostrador podría
  activar una pieza no vendida; queda registrada como venta del aliado
  (conciliable) y soporte puede `revokeOwnership`. Desactivable por config.
- `public_code` impreso en la pieza: quien tenga la pieza física puede
  activarla si está vendida — es el comportamiento esperado.

## Activación y Mi Legacy

Capa web + API sobre las acciones de arriba. `ActivateTag` sigue siendo el
único escritor; `App\Support\LegacyTags\TagActivationState` responde (sin
escribir) "¿qué hace abrir este tag para esta persona?" con las mismas
reglas, y `App\Support\LegacyTags\StoryPresenter` arma lo que ven web y API.

### Rutas (`routes/web/legacy.php`, `routes/api/legacy.php`)

| Ruta | Nombre | Qué hace |
|---|---|---|
| `GET /t/{code}` | `tag.open` | Normaliza (`TagCode::normalize`), registra `tag_opened` (`?src=qr` → qr, sin src → nfc). Activado → historia (dueño siempre; otros según visibilidad, si no `legacy/Private`). Activable (vendido/entregado o stock en ubicación de campo) → `/activar/{code}?src=…`. Resto → `activate/Unavailable` con `reason`. |
| `GET /n/{token}` | `nfc.tap` | Si un `LegacyTag.nfc_tag_id` apunta a ese chip → redirige a `/t/{code}`. |
| `GET /activar` · `POST /activar` | `activate.index` · `activate.lookup` | Escribir el código (o pegar la URL del QR) → `/activar/{code}?src=code`. Código inválido/desconocido → error de validación sin pistas. |
| `GET /activar/{code}` | `activate.show` | Pasos: 1 Tu tag · 2 Tu cuenta · 3 Activar · 4 ¿De qué carrera? Recuerda la fuente por código en sesión (sobrevive al login). Registra `activation_started`. Desconocido → 404 con página amable. |
| `GET /activar/{code}/continuar/{login\|register}` | `activate.continue` | Guarda `url.intended` = `activate.show` (patrón NFC). |
| `POST /activar/{code}` 🔒 | `activate.store` | `ActivateTag` (throttle `api-claim`). 409/422 → toast + estado `taken`/`unavailable`. |
| `POST /activar/{code}/recuerdo` 🔒 | `activate.complete` | Paso 4: participación propia / "Otra carrera" / "Lo completo después" → editor. |
| `GET /legacy/{publicId}` | `legacy.story.show` | Historia (policy). Registra `legacy_viewed` (fuente heredada de `/t`). |
| `GET /legacy/para/{token}` | `legacy.share.show` | Enlace de destinatario (40 chars). Registra `share_opened` + contadores. Revocado → página honesta. |
| `GET /mi-legacy` 🔒 | `my-legacy.index` | Biblioteca por año. "Mi legado" (`/dashboard`) se conserva. |
| `/mi-legacy/{publicId}/editar`, `PATCH /mi-legacy/{publicId}`, `…/media` (POST, `media/orden` PATCH, `media/{id}` PATCH/DELETE), `…/enlaces` (POST, `enlaces/{id}` DELETE) 🔒 | `my-legacy.*` | Editor (solo dueño). Subidas con throttle `uploads`. |
| `GET|POST /mi-legacy/{publicId}/imprimir` 🔒 | `my-legacy.print(.download)` | Plantilla de impresión → PDF (dompdf, 3 diseños). |
| `GET /api/v1/tags/{code}`, `POST /api/v1/tags/{code}/activate`, `GET /api/v1/me/stories`, `GET /api/v1/stories/{publicId}` | `api.v1.*` | Ver docs/api/v1.md §Finisher Legacy Tag (contrato estable para el app). |

Throttle `tag-open` (30/min + 300/h por IP) lo registra
`App\Providers\LegacyTagServiceProvider` (con fallback, como NFC), que
también programa `model:prune` diario de `LegacyTagScan`.

### Política (`App\Policies\LegacyStoryPolicy`)

| Visibilidad | Dueño | Con el tag / enlace directo | Enlace de destinatario | Buscadores |
|---|---|---|---|---|
| `public` | ✔ ver/editar | ✔ | ✔ | indexable (nunca en sitemap) |
| `unlisted` (default) | ✔ | ✔ (el `public_id` aleatorio es la capacidad) | ✔ | `noindex` (meta + `X-Robots-Tag`) |
| `private` | ✔ | ✘ → "Este recuerdo es privado" | ✔ | `noindex` |

`update`/`share`/`print`/`delete`: solo el dueño (403 para cualquier otro;
filas de media de otra historia → 404). API: `private` → 404 a terceros.
Media: `LegacyStory` implementa `OwnsMediaFiles` (sin firma solo el dueño;
los demás reciben URLs firmadas emitidas tras la policy).

### Media de historias

`legacy_story_media.role`: `photo` (hasta `legacy.stories.max_photos`,
ordenadas, la primera es portada) · `audio` (uno; M4A/MP3/AAC/WebM/OGG
sniffados) · `certificate` (uno; PDF o imagen). Todo vía `MediaStorage`
(privado, MIME detectado por contenido, límites `legacy.stories.max_*_bytes`).
HEIC no se acepta (el pipeline de imágenes no lo soporta). La duración del
audio solo se valida en el navegador (no hay ffprobe obligatorio).

### Analítica (`legacy_tag_scans`)

`tag_opened` (/t, GET /api/v1/tags) · `activation_started` (vista de
activación activable) · `activation_completed` (ActivateTag) ·
`legacy_viewed` (historia) · `share_opened` (enlace de destinatario) ×
fuente `nfc`/`qr`/`code`/`link`, `is_owner`, hash diario de visitante.

## Administración: catálogo, inventario y tags

Controladores en `app/Http/Controllers/Admin/Catalog/*`, rutas en
`routes/web/admin-catalog.php` (todas bajo `auth` + `verified` +
`can:dashboard.admin.view` + el permiso indicado). Páginas en
`resources/js/pages/admin/{catalog,inventory,tags}`; enlaces para la
navegación en `resources/js/config/admin-catalog-links.ts`.

| URL | Qué es | Permiso |
|---|---|---|
| `/admin/catalogo/colecciones` (+ `/crear`, `/{id}`, `/{id}/editar`) | CRUD de colecciones; imágenes en disco `public` bajo `collections/`. Con productos → se **archiva**, no se borra. | `products.manage` |
| `/admin/products/{id}` (pestañas Información / Variantes) | Campos nuevos (colección, `tracks_units`, material, correa, dimensiones, instrucciones, SEO) + **Precios por canal** (`POST /admin/products/{id}/precios-canal`, `PATCH /admin/products/precios-canal/{schedule}` activa/desactiva; nunca se borran; `activity_log`). | `products.manage` |
| `/admin/inventario/existencias` | Piezas por SKU × ubicación (en inventario / reservadas / vendidas / activadas), alerta con el mínimo del SKU. El agregado sigue en `/admin/comercio/inventario`. | `inventory.manage` |
| `/admin/inventario/ubicaciones` (+ `/{id}`) | CRUD de ubicaciones; con historial → se desactiva. Detalle: stock por SKU y movimientos. | `inventory.manage` |
| `/admin/inventario/lotes` (+ `/{uuid}`) | Lotes y sus piezas. | `tags.view` |
| `/admin/inventario/lotes/crear`, `POST /lotes`, `/lotes/{uuid}/codigos.csv`, `POST /lotes/{uuid}/recibir` | `CreateLot` (≤ 5000), CSV para fábrica (`serial, public_code, display_code, nfc_url, qr_url`), `ReceiveLot` (defectuosos por código, URL o serie). | `tags.manage` |
| `/admin/inventario/transferir` | `TransferTags::quantity` o `::units` (códigos/URLs/series pegados). | `tags.manage` |
| `/admin/inventario/proveedores` | Proveedores (con lotes → inactivo). | ver `allies.view`, escribir `allies.manage` |
| `/admin/tags`, `/admin/tags/{uuid}`, `/admin/tags/{uuid}/qr.svg` | Registro por pieza con filtros (estado, SKU, colección, ubicación, lote, canal, aliado, activado, rango de fechas) y búsqueda por serie / código / URL escaneada / pedido / correo del dueño; detalle con trazabilidad, historial, aperturas, soporte, NFC/QR. | `tags.view` |
| `POST /admin/tags/{uuid}/{desactivar,reactivar,reemplazar,devolver,revocar,notas}` | `ManageTag` / `TagLifecycle::note`, motivo obligatorio. | `tags.manage` |

Notas:

- URLs públicas de una pieza: `App\Support\LegacyTags\TagUrls` (delega en
  `LegacyTag::nfcUrl()/qrUrl()`, ruta `tag.open`). La historia se enlaza con
  `route('legacy.story.show', $publicId)`.
- Estados y colores de tag/lote/movimiento: un solo helper,
  `resources/js/lib/tagStatus.ts`.
- `InventoryService` puede lanzar `ProductOutOfStockException` si el stock
  agregado no coincide con las piezas; los controladores lo convierten en
  error de validación (nada se mueve).
