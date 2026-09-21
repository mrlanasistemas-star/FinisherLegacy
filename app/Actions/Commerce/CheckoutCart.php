<?php

namespace App\Actions\Commerce;

use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Enums\FulfillmentStatus;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Exceptions\AthleteRequiredException;
use App\Exceptions\LegacyPlatePresaleDuplicateException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Athlete;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\EventEdition;
use App\Models\InventoryLocation;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Support\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only place an Order/OrderItem is created from a Cart (brief §59) —
 * everything (price, stock, totals) is recalculated server-side inside one
 * transaction; the Cart is never trusted as-is (brief §56). A Legacy Plate
 * line also creates its LegacyPlateEntitlement in the same transaction, so
 * the two are never out of sync (brief §162).
 */
class CheckoutCart
{
    public function __construct(
        private readonly ResolveProductPrice $resolvePrice,
        private readonly InventoryService $inventory,
        private readonly CreateLegacyPlateEntitlement $createEntitlement,
        private readonly ValidateCoupon $validateCoupon,
        private readonly ResolveCartDiscount $resolveDiscount,
    ) {}

    /**
     * @param  array<string, mixed>  $customerSnapshot
     */
    public function handle(Cart $cart, ?User $user, ?Athlete $athlete, array $customerSnapshot = []): Order
    {
        $cart->loadMissing('items.productVariant.product');
        $items = $cart->items;

        if ($items->isEmpty()) {
            throw new RuntimeException('El carrito está vacío.');
        }

        $location = $this->defaultLocation();

        return DB::transaction(function () use ($cart, $user, $athlete, $customerSnapshot, $items, $location) {
            $eventEditionId = $items->first(fn ($i) => $i->event_edition_id !== null)?->event_edition_id;
            $subtotal = 0;
            $lines = [];

            foreach ($items as $item) {
                $variant = $item->productVariant;
                $product = $variant->product;

                // Time may have passed since this line was added to the
                // cart — re-checked here too, not just at add-to-cart time
                // (brief item 34: never trust the frontend/cart state).
                if (! $variant->active || ! $product->active || $product->status !== ProductStatus::Active) {
                    throw new ProductUnavailableException;
                }

                if (($product->qr_capable || $product->type === ProductType::LegacyPlate) && $athlete === null) {
                    throw new AthleteRequiredException;
                }

                $eventEdition = $item->event_edition_id !== null ? EventEdition::find($item->event_edition_id) : null;

                if ($product->type === ProductType::LegacyPlate && $athlete !== null && $eventEdition !== null) {
                    $this->guardAgainstDuplicatePresale($athlete, $eventEdition);
                }
                $price = $this->resolvePrice->handle($product, $variant, $eventEdition);

                $this->inventory->reserve($variant, $location, $item->quantity, Cart::class, $cart->id, $user);

                $lineTotal = $price->amountMinor * $item->quantity;
                $subtotal += $lineTotal;

                $lines[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $item->quantity,
                    'unit_price_minor' => $price->amountMinor,
                    'line_total_minor' => $lineTotal,
                    'currency' => $price->currency,
                    'price_type' => $price->priceType->value,
                    'event_edition' => $eventEdition,
                    'metadata' => $item->metadata ?? [],
                ];
            }

            // Locked here (not just re-fetched) so a second concurrent
            // checkout using the same coupon blocks until this transaction
            // commits, then re-validates against the now-current
            // redemption count — the only way "1 use left, two checkouts
            // at once" resolves to exactly one winner (brief §39).
            $coupon = $cart->coupon_id !== null
                ? Coupon::query()->whereKey($cart->coupon_id)->lockForUpdate()->first()
                : null;

            if ($coupon !== null) {
                $this->validateCoupon->handle($coupon, $cart->currency, $subtotal, $user);
            }

            $discount = $this->resolveDiscount->handle($coupon, $subtotal);
            $total = max($subtotal - $discount, 0);

            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'order_number' => CodeGenerator::unique('FL', fn (string $c) => Order::query()->where('order_number', $c)->exists(), 8),
                'user_id' => $user?->id,
                'athlete_id' => $athlete?->id,
                'event_edition_id' => $eventEditionId,
                'status' => OrderStatus::Pending,
                'payment_status' => OrderPaymentStatus::Pending,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'tax_minor' => 0,
                'total_minor' => $total,
                'currency' => $cart->currency,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'coupon_name' => $coupon?->name,
                'customer_snapshot' => $customerSnapshot === [] ? null : $customerSnapshot,
            ]);

            if ($coupon !== null) {
                CouponRedemption::create([
                    'uuid' => (string) Str::uuid(),
                    'coupon_id' => $coupon->id,
                    'user_id' => $user?->id,
                    'order_id' => $order->id,
                    'code' => $coupon->code,
                    'discount_minor' => $discount,
                ]);
            }

            foreach ($lines as $line) {
                $variantName = $line['variant']->name;
                $productName = $line['product']->name;

                $orderItem = $order->items()->create([
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']->id,
                    'name' => $variantName !== '' && $variantName !== $productName ? "{$productName} - {$variantName}" : $productName,
                    'sku' => $line['variant']->sku,
                    'quantity' => $line['quantity'],
                    'unit_price_minor' => $line['unit_price_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'currency' => $line['currency'],
                    'metadata' => ['price_type' => $line['price_type']],
                ]);

                if ($line['product']->type === ProductType::LegacyPlate) {
                    $modelId = $line['metadata']['legacy_plate_model_id'] ?? null;

                    if ($modelId !== null && LegacyPlateModel::query()->whereKey($modelId)->exists()) {
                        $this->createEntitlement->handle([
                            'athlete_id' => $athlete?->id,
                            'event_edition_id' => $line['event_edition']->id,
                            'legacy_plate_model_id' => $modelId,
                            'order_item_id' => $orderItem->id,
                            'price_type' => $line['price_type'],
                        ]);
                    }
                }
            }

            $cart->items()->delete();
            $cart->update(['coupon_id' => null]);

            return $order->fresh('items');
        });
    }

    /**
     * The same Athlete buying a second Legacy Plate for the same event is
     * almost always an accidental double-click, not intent (brief §11) —
     * blocked here, the one place every Legacy Plate line item passes
     * through, regardless of which model was picked. A deliberate second
     * purchase is an explicit admin action, not a second checkout.
     */
    private function guardAgainstDuplicatePresale(Athlete $athlete, EventEdition $eventEdition): void
    {
        $exists = LegacyPlateEntitlement::query()
            ->where('athlete_id', $athlete->id)
            ->where('event_edition_id', $eventEdition->id)
            ->where('status', '!=', LegacyPlateEntitlementStatus::Cancelled)
            ->exists();

        if ($exists) {
            throw new LegacyPlatePresaleDuplicateException;
        }
    }

    private function defaultLocation(): InventoryLocation
    {
        $slug = config('finisher.commerce.default_inventory_location_slug', 'main-warehouse');

        return InventoryLocation::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => 'Main Warehouse', 'active' => true],
        );
    }
}
