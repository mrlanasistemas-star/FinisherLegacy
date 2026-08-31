<?php

namespace App\Actions\Commerce;

use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Exceptions\AthleteRequiredException;
use App\Models\Athlete;
use App\Models\Cart;
use App\Models\EventEdition;
use App\Models\InventoryLocation;
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

                if (($product->qr_capable || $product->type === ProductType::LegacyPlate) && $athlete === null) {
                    throw new AthleteRequiredException;
                }

                $eventEdition = $item->event_edition_id !== null ? EventEdition::find($item->event_edition_id) : null;
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
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $subtotal,
                'currency' => $cart->currency,
                'customer_snapshot' => $customerSnapshot === [] ? null : $customerSnapshot,
            ]);

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

            return $order->fresh('items');
        });
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
