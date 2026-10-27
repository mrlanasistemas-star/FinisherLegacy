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
