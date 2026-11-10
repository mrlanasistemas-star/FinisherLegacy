# Límites, ampliaciones y precios del recuerdo

Estado: backend, admin, editor web y API implementados (2026-11-12). Todo se configura en **/admin/limites-y-tarifas** (`admin.quotas.index`, permiso `quotas.manage`). Los valores iniciales de abajo son un punto de partida editable.

## Qué guarda el recuerdo (el que abre el tag)

| Dato | Campo (`legacy_stories`) | Notas |
|---|---|---|
| Número de corredor | `bib_number` (≤12) | |
| Nombre a mostrar | `athlete_display_name` (≤80) | Si está vacío se muestra el nombre completo del atleta. Nunca es "oficial". |
| Tiempo, lugar general | `result_time`, `result_position` | Ya existían. |
| Ritmo | `pace` (≤20, texto libre: "5:12 min/km") | Además se **sugiere** uno calculado de distancia + tiempo cuando ambos se entienden (`StoryRaceData::suggestPace`). |
| Categoría y lugares | `category` (≤40), `category_position`, `gender_position` | |
| Estado de llegada | `finish_status` (`finished` / `dnf` / `dns` / `dq`) | Opcional. |
| Desnivel positivo | `elevation_gain_m` | Opcional. |
| Deporte | `sport_id` → `sports` | Opcional. |

**Datos oficiales.** Si el recuerdo está ligado a una participación (`event_participant_id`) con resultados, cada campo que la participación responde (evento, distancia, tiempo, lugar general, dorsal, ritmo, categoría, lugar en categoría, lugar por rama, estado, fecha, sede, deporte) muestra el valor oficial y queda de solo lectura con la marca "Oficial"; un campo sin registro oficial muestra lo que escribió el atleta. Es la misma regla que ya usaba `StoryPresenter::race()` para el lugar general, ahora en un solo lugar: `App\Support\LegacyTags\StoryRaceData`. Lo que se envíe para un campo oficial se ignora (no se guarda encima).

**Contenido.** Fotos (subidas desde el dispositivo o **compradas en el sitio**), **videos** (mp4 / mov / webm), mensajes de voz, certificado, texto e historia.

## Límites por tipo de contenido

`App\Services\Media\StoryMediaQuota` es la única fuente (web, API, admin y la cola de procesamiento). `StoryPhotoQuota` conserva su API pública y delega en él.

```
límite = min( base + extra , tope máximo? )
base   = regla del producto → regla del tipo de producto → regla «global» → config('legacy.stories.*')
extra  = derechos VIGENTES del recuerdo (ampliaciones pagadas + espacio liberado a mano)
         + espacio liberado a mano a la persona (cuenta en todos sus recuerdos)
```

| Tipo (`StoryQuotaKind`) | Cantidad incluida | Duración máx. de cada uno | Tope máximo opcional |
|---|---|---|---|
| `photos` | `included_photos` | — | `cap_photos` |
| `videos` | `included_videos` | `max_video_seconds` | `cap_videos` |
| `audio` | `included_audio` | `max_audio_seconds` | `cap_audio` |

- Tabla `photo_quota_rules` (`PhotoQuotaRule`): una fila por alcance (`scope_key` = `product:{id}`, `type:{product_type}` o `global`). **Cada valor es independiente y nulo = heredar** del siguiente alcance.
- **Bajar un límite, revocar espacio o que venza un plan nunca borra contenido**: el recuerdo sigue mostrando todo y se puede descargar; solo no admite más mientras esté por encima.
- **Tope máximo**: el límite nunca lo rebasa, y no se ofrece ni se vende una ampliación que lo rebasaría.
- **Duración**: se mide de los bytes con ffprobe (al subir cuando está instalado, y siempre en la cola). La duración que reporta el cliente solo sirve para rechazar antes. Hay 1 s de tolerancia.
- **Mensaje de voz**: con límite 1, una grabación nueva reemplaza a la anterior (como siempre); con límite mayor se acumulan.
- **Concurrencia**: `ManageStoryMedia` revisa antes de guardar bytes y otra vez dentro de una transacción con `lockForUpdate` sobre el recuerdo; la fila, el archivo temporal y el pivot se crean ahí mismo y se deshacen juntos.

### Videos

Rol `video` en `legacy_story_media`. Tipos aceptados por contenido real (no por extensión): `video/mp4`, `video/quicktime`, `video/webm`. Peso máximo `legacy.stories.max_video_bytes` (150 MB, `LEGACY_STORY_MAX_VIDEO_BYTES`). Pasa por la cola existente (`ProcessMediaFile` → H.264 MP4 + póster). Estados: `processing` → `ready` (o `failed`). Se sirve por la misma ruta firmada que fotos y audio: un recuerdo privado solo entrega URLs firmadas de corta duración.

> En el servidor de PHP revisa `upload_max_filesize` y `post_max_size`: deben ser mayores que el peso máximo de video.

### Fotos compradas en el sitio

El dueño elige entre sus compras vigentes (`PhotoSale::grantingAccess()`) y las agrega al recuerdo.

- **Decisión de almacenamiento**: el recuerdo necesita versiones web, URLs firmadas y sus propias reglas de acceso, así que cada foto importada tiene su propio `MediaFile` — pero **no se duplica el original**: la cola lee el archivo de alta resolución directamente del disco privado `event_photo_originals`, genera las versiones `display` / `card` / `thumb` en la carpeta del recuerdo y no guarda un segundo original. Quitar la foto del recuerdo borra solo esas versiones.
- Cuentan contra el límite de fotos como cualquier otra. Una misma compra entra una sola vez por recuerdo (`legacy_story_media.photo_sale_id`, único con el recuerdo; `source = purchased`).
- **Reembolso**: `ReversePhotoSale` (reembolso total del pedido vía `PhotoSaleRefundReversal`, o reversa manual de una venta por admin) llama a `ManageStoryMedia::removePurchasedSale`: la foto sale de todos los recuerdos que la importaron. El original del fotógrafo no se toca. Queda en el registro de actividad (`legacy_story.purchased_photo_removed`).

## Ampliaciones (`photo_expansion_packs`)

Cada ampliación agrega `photos` unidades (la columna conserva su nombre) de un `kind` (`photos` / `videos` / `audio`), con alcance producto / tipo / global.

### Cómo se cobra (`billing_period`, `App\Enums\BillingPeriod`)

| Valor | Significado |
|---|---|
| `one_time` | Pago único: el espacio es permanente. |
| `semiannual` | Plan prepagado de 6 meses. |
| `annual` | Plan prepagado de 12 meses. |

- Un plan por periodo es un **prepago**: se paga una vez por el periodo y se renueva **pagando de nuevo**. No se guarda ninguna tarjeta y no hay cargos automáticos.
- Al confirmarse el pago, el derecho (`media_entitlements`) guarda `billing_period`, `starts_at` y `expires_at` (inicio + 6 o 12 meses). **Cambiar el periodo de una ampliación nunca altera lo ya vendido.**
- **Renovar**: comprar la misma ampliación cuando al plan vigente le quedan ≤ 30 días (`StoryMediaQuota::RENEWAL_WINDOW_DAYS`) lo **extiende desde su fecha de vencimiento** (no se pierde ningún día pagado). Si el plan ya venció, o le falta más de 30 días, la compra empieza hoy (en el segundo caso es espacio adicional).
- **Al vencer**: ese espacio deja de contar. Todo el contenido sigue visible y descargable; no se puede agregar más hasta renovar o quitar elementos. El editor muestra "Tu plan venció — renuévalo" con el botón Renovar, y la API lo expone en `plan`.
- **Avisos** (`php artisan quotas:remind-expiring`, programado a diario 10:00 en `routes/console.php`): 30, 7 y 1 día antes del vencimiento y al vencer. Notificación en la app + push (`SendAthleteNotification`, categoría Pedidos, respeta las preferencias del usuario) y un correo (aviso de servicio, siempre se envía). Cada aviso sale una sola vez (`expiry_reminders`); un plan ya renovado no recibe avisos; si el comando no corrió varios días solo sale el aviso más cercano.
- Antes de pagar el cliente ve el periodo: `price_label` = "$99.00 MXN por 12 meses" o "$59.00 MXN · pago único".

### Precios y banderas

- Precio = fila de `product_price_schedules` con `photo_expansion_pack_id`. **Historial inmutable**: un precio nuevo es una fila nueva; a la vigente solo se le pone `ends_at`. Solo se puede cancelar (`active=false`) uno que aún no empieza y nunca se vendió.
- **Banderas** (`photo_expansion_price_steps`): el precio depende de cuánto tiene ya el recuerdo. El monto de la fila aplica desde 0 y cada bandera lo reemplaza a partir de `from_units`. Ejemplo "hasta 20 fotos $X; de 21 a 50 $Y; más de 50 $Z" = monto X + banderas `21 → Y` y `51 → Z`.
- "Lo que ya tiene" = `max(límite actual, elementos guardados)` de ese tipo (`StoryMediaQuota::heldFor`), así borrar fotos no baja de bandera.
- Las banderas se guardan junto con su precio y son igual de inmutables: para cambiarlas se programa un precio nuevo.
- **Se resuelve en el servidor al comprar y se congela en el pedido**: `order_items.metadata` guarda `product_price_schedule_id`, `price_step_id`, `step_from_units`, `step_to_units`, `held_units`, `expansion_kind`, `units`, `billing_period`, monto, moneda e impuesto. Un cambio de tarifa posterior no toca ese pedido.
- Impuesto: `tax_included=true` → total = monto; `false` → se suma `round(monto × tasa)`.

### Compra

1. Web: `POST /mi-legacy/{story}/ampliar` (`my-legacy.expansion.store`; campos `kind`, `pack`) → `CheckoutPhotoExpansion` crea un **pedido pendiente** y redirige a la página del pedido para pagar con el flujo normal de pedidos. La ruta anterior `my-legacy.photo-expansion.store` sigue funcionando.
2. Solo cuando el backend confirma el pago (`MarkOrderPaid` → `GrantPhotoExpansionEntitlements`) se crea el derecho, con el tipo, las unidades y el periodo congelados. Idempotente por `order_item_id`.
3. App: compra dentro de la app — ver [compras-dentro-de-app.md](./compras-dentro-de-app.md).
4. Reembolso total → `RevokePhotoExpansionEntitlements` pone `revoked_at`; no se borra contenido.

### Liberar espacio a mano

Admin → "Liberar espacio" (`GrantStoryCapacity`): a **un recuerdo** (enlace o código) o a **una persona** (correo; cuenta en todos sus recuerdos), con tipo, cantidad, **motivo obligatorio** y vencimiento opcional. Es un `media_entitlement` con `source = manual_grant`, `granted_by`, `grant_reason`. Aparece en la tabla de derechos y se puede **revocar** (`revoked_at`, `revoked_by`, `revocation_reason`). Ambas acciones quedan en el registro de actividad (`log_name = quotas`). Una compra no se revoca desde ahí: se revierte con su reembolso.

## Valores iniciales (`php artisan quotas:seed-defaults`)

Comando idempotente y seguro en producción: **solo crea lo que falta**; nunca modifica una regla, ampliación o precio existente (`--dry-run` para ver qué haría). Ya se ejecutó en la base local.

| Alcance | Fotos | Videos | Duración video | Mensajes de voz | Duración voz | Topes |
|---|---|---|---|---|---|---|
| General (`global`) | 10 | 1 | 60 s | 1 | 180 s | 200 fotos · 20 videos · 10 audios |
| Finisher Legacy Tag (`type:legacy_tag`) | 10 | 1 | 60 s | 1 | 180 s | hereda |
| Portarretrato (`portarretrato-inteligente`, si existe el producto) | 5 | hereda | hereda | hereda | hereda | hereda |

| Ampliación | Tipo | Cobro | Precio base (MXN, IVA incluido) | Banderas |
|---|---|---|---|---|
| +10 fotos | fotos | pago único | $59 (hasta 30 fotos) | de 31 a 60: $79 · 61 o más: $99 |
| +20 fotos | fotos | anual | $99 (hasta 40 fotos) | de 41 a 80: $129 · 81 o más: $159 |
| +1 video | videos | pago único | $79 | — |
| +3 videos | videos | anual | $149 (hasta 6 videos) | 7 o más: $199 |
| +2 mensajes de voz | audio | pago único | $39 | — |

## Suscripción recurrente (no implementada)

Hoy todo es **pago único por compra**: permanente (`one_time`) o prepago por periodo que el cliente renueva pagando otra vez. No existe el cobro automático. Para tenerlo haría falta:

- **Web**: tokenizar y guardar la tarjeta con el procesador (cliente + tarjeta), crear ahí un plan y una suscripción, y procesar sus webhooks (cobro exitoso → extender `expires_at`; cobro fallido → reintentos y periodo de gracia; cancelación). Consentimiento explícito de cargos recurrentes, pantalla para cancelar y cambiar tarjeta, y manejo de contracargos.
- **App**: productos de **suscripción auto-renovable** en App Store / Google Play, verificar renovaciones con las notificaciones de servidor de cada tienda (`DID_RENEW`, `EXPIRED`, `GRACE_PERIOD`…) y enlace a la gestión de suscripciones de la tienda.
- **Modelo**: tabla de suscripciones (estado, próximo cobro, id externo) ligada al derecho; el derecho pasaría de fechas fijas a "vigente mientras la suscripción esté activa".

## API (Sanctum + `user.token`)

Contrato completo en [docs/api/v1.md](../api/v1.md) §Editor del recuerdo. Resumen:

- `GET|PATCH /api/v1/me/stories/{publicId}` — recuerdo editable + `official` + `share_url`.
- `GET|POST /api/v1/me/stories/{publicId}/media`, `DELETE …/media/{uuid}`, `POST …/media/reorder`, `POST …/media/import`.
- `GET /api/v1/me/purchased-photos`.
- `GET /api/v1/stories/{publicId}/quota` — fotos en la raíz (como antes) + `kinds` con los tres tipos.
- `GET /api/v1/me/entitlements` — ahora con `kind`, `units`, `source`, `billing_period`, `starts_at`, `expires_at`, `expired`.
- `GET /api/v1/iap/offer?story=&platform=&kind=`.
- Límite alcanzado: HTTP 422, `code` y `error.code` = `MEDIA_LIMIT_REACHED`, `error.details` = `{kind, limit, used, can_expand, expansion, plan}`.

## Archivos principales

- Servicio: `app/Services/Media/StoryMediaQuota.php`, `StoryPhotoQuota.php`, `app/Support/Quotas/ResolvedExpansionPrice.php`.
- Acciones: `app/Actions/LegacyTags/ManageStoryMedia.php`, `UpdateStory.php`; `app/Actions/Quotas/{CheckoutPhotoExpansion,GrantPhotoExpansionEntitlements,GrantStoryCapacity,RevokePhotoExpansionEntitlements}.php`.
- Datos de carrera: `app/Support/LegacyTags/StoryRaceData.php`, `StoryPresenter.php`.
- Admin: `app/Http/Controllers/Admin/Quotas/*`, `resources/js/pages/admin/quotas/Index.vue`, `resources/js/components/admin/quotas/*`.
- Editor y página pública: `resources/js/pages/my-legacy/Edit.vue`, `resources/js/components/legacy/editor/*`, `resources/js/pages/legacy/Story.vue`, `resources/js/components/legacy/story/*`.
- Comandos: `quotas:seed-defaults`, `quotas:remind-expiring`.
- Migraciones: `2026_11_12_1000xx_*` y `2026_11_12_1001xx…1005xx_*` (todas aditivas y reversibles).
- Pruebas: `tests/Feature/StoryMedia/*`, `tests/Feature/Quotas/*`.

## Pendiente

- Productos reales en App Store Connect / Play Console para videos, audio y cada bandera (ver compras-dentro-de-app.md).
- La razón de un video fallido (demasiado largo vs. dañado) no se expone al cliente: hoy el editor muestra un mensaje genérico.
- Un reembolso **parcial** de un pedido no revoca ampliaciones ni quita fotos compradas automáticamente (no se sabe qué renglón se reembolsó): el admin revierte la venta de la foto a mano.
