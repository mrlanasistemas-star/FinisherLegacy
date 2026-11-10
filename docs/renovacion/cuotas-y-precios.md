# Cuotas de fotos, ampliaciones y precios

Estado: backend + admin implementados (2026-11-10). Sin tarifas reales sembradas: los precios los configura admin en **/admin/limites-y-tarifas** (`admin.quotas.index`, permiso `quotas.manage`).

## Modelo

| Pieza | Dónde | Notas |
|---|---|---|
| Límite base | `photo_quota_rules` (`PhotoQuotaRule`) | `scope_key` = `product:{id}` o `type:{product_type}`. Resolución: producto → tipo de producto → `config('legacy.stories.max_photos')`. |
| Ampliación | `photo_expansion_packs` (`PhotoExpansionPack`) | Fotos que agrega, alcance (producto / tipo / global), `active`, `archived_at`. Con precios o ventas solo se archiva. |
| Precio | `product_price_schedules` (reutilizado) | Filas con `photo_expansion_pack_id`, `price_type=standard`, `amount_minor`, `currency`, `tax_rate` (fracción, p. ej. 0.16), `tax_included`, `starts_at`, `ends_at`, `active`, `created_by`. `product_id` = producto interno `ampliacion-fotos-recuerdo`. |
| Derecho comprado | `media_entitlements` (reutilizado) | `source=photo_expansion`, `legacy_story_id`, `photo_expansion_pack_id`, `product_price_schedule_id`, `order_item_id` (único), `extra_images` = fotos compradas (congeladas). `event_participant_id` ahora es nullable; las filas Memory Pack existentes no cambian. |

`App\Services\Media\StoryPhotoQuota` es la única fuente: `limitFor`, `usedFor`, `assertCanAdd` (lanza `App\Exceptions\Media\PhotoQuotaExceededException`, código API `MEDIA_LIMIT_REACHED`, `details` = limit/used/can_expand/expansion; en web se convierte en error `file`), `summary`.

## Reglas

- **Historial de precios inmutable**: un precio nuevo siempre es una fila nueva; la fila vigente solo recibe `ends_at` (el inicio del precio nuevo). Solo se puede *cancelar* (`active=false`, se queda en el historial) un precio que aún no empieza y nunca se vendió. Ningún precio se borra.
- **Compras pasadas conservan su precio**: el OrderItem guarda monto, moneda, impuesto e id de la fila de precio en `metadata`; el derecho guarda `product_price_schedule_id`.
- **Bajar un límite o archivar una ampliación nunca borra contenido**: el recuerdo sigue mostrando todas sus fotos; solo no admite nuevas mientras `used >= limit`.
- **Concurrencia**: `ManageStoryMedia::add()` hace una verificación barata antes y la definitiva dentro de una transacción con `lockForUpdate` sobre la fila del recuerdo. La fila `media_files`, el archivo temporal y el pivot se crean dentro de esa misma transacción; si se revierte, se borra el archivo temporal (sin filas ni archivos huérfanos). Una falla al escribir en el disco temporal (`media_tmp`, `throw=true`) se convierte en un error de validación `file` amigable.
- **Impuesto**: `tax_included=true` → total = monto (el impuesto incluido va solo en la metadata del item). `false` → `orders.tax_minor = round(monto × tasa)` y total = monto + impuesto.

## Cómo se otorga el derecho

1. El dueño hace `POST /mi-legacy/{story}/ampliar-fotos` (`my-legacy.photo-expansion.store`, campo opcional `pack`) → `CheckoutPhotoExpansion` crea una **Order pendiente** (mismo patrón que `CheckoutEventPhotos`) y redirige a `store.orders.show` para pagar con el flujo existente (OpenPay/Stripe/manual).
2. Solo cuando el backend confirma el pago (webhook verificado → `ProcessPaymentWebhook`, sincronización servidor-a-servidor `SyncOnlinePayment`, o pago manual registrado por admin) corre `MarkOrderPaid`, que llama a `GrantPhotoExpansionEntitlements`. Ninguna pantalla de "éxito" del cliente otorga nada.
3. Idempotente: índice único en `media_entitlements.order_item_id` + verificación previa. Un webhook repetido no otorga dos veces. Si la Order ya estaba cancelada (expirada), `MarkOrderPaid` no otorga.

## API (Sanctum + `user.token`)

`GET /api/v1/stories/{publicId}/quota` — solo el dueño (otro usuario: 404).

```json
{"data": {"story": "ab12cd34", "limit": 5, "used": 3, "remaining": 2, "can_expand": true,
  "expansion": {"id": 1, "name": "…", "photos": 10, "price": "50.00", "currency": "USD"}}}
```

`GET /api/v1/me/entitlements`

```json
{"data": [{"id": "uuid", "type": "story_photos", "story": "ab12cd34", "story_title": "…", "pack": "…",
  "photos": 10, "price": "50.00", "currency": "USD", "active": true, "granted_at": "…", "revoked_at": null}]}
```

Al subir una foto por API con el límite alcanzado: HTTP 422, `error.code = MEDIA_LIMIT_REACHED`, `error.details.expansion` con la oferta vigente (o `null`).

## Pendiente

- **UI del dueño**: el editor del recuerdo (`legacy/StoryEditor`) aún no muestra `summary()` ni el botón "Ampliar espacio". Necesita: pasar `photoQuota` (= `StoryPhotoQuota::summary`) como prop desde `StoryEditorController::edit`, mostrar `used/limit`, y al llegar al límite un botón que haga `POST my-legacy.photo-expansion.store`. El backend ya está listo.
- **Compras dentro de la app móvil**: vender bienes digitales dentro de la app exige StoreKit (iOS) / Google Play Billing. Fuera de alcance: la app solo lee `quota`/`entitlements`; la compra se hace en la web.
- **Reembolsos**: un reembolso **total** (webhook `refunded` de Stripe/OpenPay, o la sincronización servidor-a-servidor; ambos pasan por `ProcessPaymentWebhook::applyRefund`) llama a `RevokePhotoExpansionEntitlements`, que pone `revoked_at` en los derechos de esa Order. El límite baja y no se borra ninguna foto: el recuerdo las sigue mostrando todas, pero no admite nuevas mientras esté por encima. Un reembolso parcial no revoca nada (decisión manual). Hoy no existe una acción de admin que reembolse dinero de una Order: `SupportCaseController` solo *registra* `refund_minor` y `ReturnOrderItem` mueve inventario. Si se agrega una, debe llamar a la misma acción.
- La entrada de navegación del admin hacia `admin.quotas.index` la agrega otro agente.
