# Commerce — tienda, pagos, entitlements

Ecosistema completo Fase 1: catálogo, inventario, carrito, checkout,
pedidos, pagos (online + manuales), y el puente hacia Legacy Plate
(`LegacyPlateEntitlement`) y Digital Closet (`AthleteOwnedProduct`, ver
`docs/architecture/athlete-assets.md`).

## Catálogo

- `Product` — `type` (`App\Enums\ProductType`: legacy_plate/apparel/
  accessory/equipment) es una clasificación **de dominio** (para lógica,
  p. ej. "Legacy Plate requiere `event_edition_id`"), separada de
  `category_id` (`ProductCategory`, puramente de catálogo/UI).
- `ProductVariant` — `attributes` es JSON libre (`{size, color}`), nunca
  columnas dedicadas por atributo (brief §50).
- Seed: `database/seeders/ProductCatalogSeeder.php` — Legacy Plate,
  Trisuit, FAST T1 Socks, Chill Band, Racepack.

## Inventario

`App\Services\Commerce\InventoryService` — el único lugar que muta
`InventoryLevel.quantity_on_hand`/`quantity_reserved`, siempre dentro de
una transacción con `lockForUpdate()` (brief §54/§147: nunca sobrevender).

```
receive()     → +quantity_on_hand
reserve()     → +quantity_reserved (bloquea si excede disponible y el producto rastrea inventario)
release()     → -quantity_reserved (nunca baja de 0)
commitSale()  → -quantity_on_hand y -quantity_reserved juntos (finaliza una reserva en venta real)
adjust()      → delta libre sobre quantity_on_hand (mermas, conteos)
```

Cada mutación escribe un `InventoryMovement` inmutable (`created_at` sin
`updated_at`) — bitácora de auditoría, no la fuente de verdad del balance
(esa es `InventoryLevel`).

Un producto con `tracks_inventory = false` (Legacy Plate — brief §92)
nunca es bloqueado por stock.

## Precio

`App\Actions\Commerce\ResolveProductPrice` — la única fuente de verdad de
precio, Web y API. Nunca un monto que venga del cliente (brief §68/§93).

- **Legacy Plate**: requiere un `ProductPriceSchedule` activo en ese
  momento, con `price_type` ∈ {`early_presale`, `kit_pickup`,
  `event_day`}, opcionalmente scoped a un `event_edition_id`. Sin
  schedule aplicable → `PriceNotAvailableException` (`PRICE_NOT_AVAILABLE`),
  **nunca** cae a un precio adivinado.
- **Tienda general**: si no hay `ProductPriceSchedule` (`price_type =
  standard`) aplicable, cae al `ProductVariant.base_price_minor`.

Precedencia determinista entre schedules simultáneamente activos
(brief §43 — "no last row wins"): variante específica > evento específico
> tipo de precio (event_day > kit_pickup > early_presale > standard) >
id más reciente como último desempate documentado.

## Carrito → Checkout → Order

`Cart`/`CartItem` **no** son fuente de verdad de precio (brief §56) —
solo selección/cantidad. `App\Actions\Commerce\CheckoutCart` recalcula
todo dentro de una transacción:

1. Por cada línea: resuelve precio (`ResolveProductPrice`), reserva
   inventario (`InventoryService::reserve()`).
2. Crea `Order` + `OrderItem`s — snapshot comercial completo (nombre,
   sku, precio unitario, total) que nunca vuelve a leer `Product`/
   `ProductVariant` después (brief §58).
3. Si la línea es Legacy Plate y trae `metadata.legacy_plate_model_id`,
   crea el `LegacyPlateEntitlement` correspondiente en la misma
   transacción (brief §162 — relación explícita OrderItem → Entitlement).
4. Vacía el carrito.

Checkout requiere sesión autenticada en esta fase (brief §198 — "puedes
requerir login si simplifica ownership", elegido porque todo producto se
vincula al Athlete); carrito de invitado queda como deuda documentada.

Un producto QR-capable o Legacy Plate sin Athlete resuelto lanza
`AthleteRequiredException` — `App\Actions\Athletes\EnsureAthleteForUser`
(ya existente, reutilizado) evita que esto ocurra para un User logueado.

### Ciclo de vida de Order

`status` (pending/confirmed/cancelled/completed) es **independiente** de
`payment_status` y `fulfillment_status` — nunca inferido uno del otro
(brief §61-§63):

- `App\Actions\Commerce\ConfirmOrder` — pending → confirmed.
- `App\Actions\Commerce\CancelOrder` — libera inventario reservado y
  cancela entitlements asociados; nunca sobre una Order completada.
- `App\Actions\Commerce\FulfillOrderItem` — el stand-in deliberadamente
  simple de este proyecto para un modelo `Fulfillment` dedicado (brief
  §105 lo permite explícitamente): confirma la venta en inventario, marca
  `OrderItem.fulfilled_at`, y por cada unidad de un producto QR-capable
  con Athlete conocido, crea un `AthleteOwnedProduct`.
- `App\Actions\Commerce\CompleteOrder` — requiere confirmed + fulfilled.

## Pagos

`App\Contracts\Commerce\PaymentGateway` — contrato para gateways online
(Stripe hoy, OpenPay futuro). Manual (`cash`/`mercado_pago_terminal`)
**no** pasa por este contrato — es
`App\Actions\Commerce\RegisterManualPayment` directamente (brief §65-§66).

```
Payment.provider   stripe | manual
Payment.method     online_card | mercado_pago_terminal | cash
Payment.status     pending | authorized | paid | failed | refunded | partially_refunded | cancelled
```

- **Manual**: monto exacto obligatorio en Fase 1 (brief §76, sin pagos
  parciales), requiere permiso `payments.record_manual`, siempre registra
  `created_by`.
- **Online (Stripe)**: `App\Services\Commerce\Payments\StripePaymentGateway`
  es **un stub deliberado** — el SDK `stripe/stripe-php` no está instalado
  y no existen credenciales reales (brief §67/§71: "no inventar
  credenciales"). Cada método lanza `PaymentGatewayNotConfiguredException`
  (501) en vez de simular un pago exitoso. El contrato,
  `config('finisher.payments.stripe')` y `PaymentGatewayRegistry` ya
  existen — falta únicamente instalar el SDK y escribir la implementación
  real cuando haya llaves.

`App\Actions\Commerce\MarkOrderPaid` centraliza "qué pasa cuando una Order
queda pagada" (brief §83 — componer, no un God Action): confirma la Order
si seguía pendiente, y mueve cualquier `LegacyPlateEntitlement` que esa
Order financió de `pending_payment` a `paid`/`linked`. La usan tanto
`RegisterManualPayment` como `ProcessPaymentWebhook`.

### Webhooks

`App\Actions\Commerce\ProcessPaymentWebhook` recibe un
`PaymentWebhookOutcome` ya verificado (firma comprobada dentro del
gateway, brief §69) — nunca HTTP crudo. Idempotente por
`(provider, event_id)` vía la restricción única de
`payment_webhook_receipts` (brief §77-§78/§148/§170): un webhook repetido
no tiene segundo efecto. Un monto/moneda que no coincide con el `Payment`
esperado nunca marca el pago como pagado (`PaymentAmountMismatchException`,
brief §70/§178).

`POST /api/webhooks/stripe` vive **fuera** de `/api/v1` y de
`auth:sanctum` — el proveedor llama directo, autenticado solo por su
firma (ver `docs/api/v1.md`).

## Deuda conocida

- Stripe real (SDK + implementación) — ver arriba.
- OpenPay — solo el contrato existe, ningún stub siquiera (brief §71
  explícitamente prefiere no crear una implementación falsa sin
  documentación/credenciales).
- Carrito de invitado (`session_token`) — el modelo lo soporta,
  `GetOrCreateCart` lo soporta, pero el checkout actual solo lo ejercita
  autenticado.
- Impuestos/envío/descuentos — fuera de alcance a propósito (brief
  §204-§206), campos preparados (`tax_minor`, `discount_minor`,
  `requires_shipping`) sin motor real.
- `ReleaseExpiredInventoryReservations` (brief §194-§195) — el comando/
  scheduler no se implementó en este pase; una reserva de checkout
  abandonado queda retenida hasta cancelación manual de la Order.
