<?php

namespace App\Actions\Quotas;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\LegacyStory;
use App\Models\Order;
use App\Models\PhotoExpansionPack;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\StoryPhotoQuota;
use App\Support\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns "buy more photo space for this story" into a regular pending
 * Order (one OrderItem on the internal `ampliacion-fotos-recuerdo`
 * product, metadata kind=photo_expansion) — the same pattern as
 * CheckoutEventPhotos. Payment then goes through the existing order
 * payment flow (OpenPay/Stripe/manual); the capacity is granted only by
 * GrantPhotoExpansionEntitlements once MarkOrderPaid runs.
 *
 * The price row in force right now is frozen into the item (amount,
 * currency, tax, schedule id), so later price changes never touch it.
 */
class CheckoutPhotoExpansion
{
    public function __construct(private readonly StoryPhotoQuota $quota) {}

    public function handle(User $buyer, LegacyStory $story, ?int $packId = null): Order
    {
        if (! $story->isOwnedBy($buyer)) {
            throw ValidationException::withMessages(['expansion' => 'Solo quien es dueño del recuerdo puede ampliar su espacio.']);
        }

        $pack = $packId !== null
            ? $this->quota->applicablePacks($story)->firstWhere('id', $packId)
            : $this->quota->expansionPackFor($story);
        $price = $pack?->priceAt();

        if ($pack === null || $price === null) {
            throw ValidationException::withMessages(['expansion' => 'Esta ampliación ya no está disponible. Recarga la página.']);
        }

        $pending = Order::query()
            ->where('user_id', $buyer->id)
            ->where('status', OrderStatus::Pending)
            ->where('payment_status', OrderPaymentStatus::Pending)
            ->whereHas('items', fn ($q) => $q
                ->where('metadata->kind', GrantPhotoExpansionEntitlements::ITEM_KIND)
                ->where('metadata->legacy_story_id', $story->id))
            ->latest('id')
            ->first();

        if ($pending !== null) {
            throw ValidationException::withMessages([
                'expansion' => "Ya tienes una ampliación de este recuerdo en el pedido {$pending->order_number}, pendiente de pago. Complétalo desde Mis pedidos — no se cobró nada.",
                'pending_order' => $pending->uuid,
            ]);
        }

        $product = Product::query()->where('slug', PhotoExpansionPack::PRODUCT_SLUG)->firstOrFail();

        $amount = (int) $price->amount_minor;
        $rate = $price->tax_rate !== null ? (float) $price->tax_rate : 0.0;
        $taxIncluded = $price->tax_included || $rate <= 0.0;
        $taxMinor = $taxIncluded
            ? (int) round($amount - $amount / (1 + $rate))
            : (int) round($amount * $rate);
        $total = $taxIncluded ? $amount : $amount + $taxMinor;

        return DB::transaction(function () use ($buyer, $story, $pack, $price, $product, $amount, $rate, $taxIncluded, $taxMinor, $total) {
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'order_number' => CodeGenerator::unique('FL', fn (string $c) => Order::query()->where('order_number', $c)->exists(), 8),
                'user_id' => $buyer->id,
                'status' => OrderStatus::Pending,
                'payment_status' => OrderPaymentStatus::Pending,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'subtotal_minor' => $amount,
                'discount_minor' => 0,
                // Order.tax_minor only carries tax added on top; included tax
                // is informative and lives on the item metadata.
                'tax_minor' => $taxIncluded ? 0 : $taxMinor,
                'total_minor' => $total,
                'currency' => $price->currency,
                'customer_snapshot' => ['name' => $buyer->name, 'email' => $buyer->email],
            ]);

            $order->items()->create([
                'uuid' => (string) Str::uuid(),
                'product_id' => $product->id,
                'name' => "{$pack->name} · +{$pack->photos} fotos",
                'sku' => 'PHOTO-EXP-'.$pack->id,
                'quantity' => 1,
                'unit_price_minor' => $amount,
                'line_total_minor' => $amount,
                'currency' => $price->currency,
                'metadata' => [
                    'kind' => GrantPhotoExpansionEntitlements::ITEM_KIND,
                    'legacy_story_id' => $story->id,
                    'legacy_story_public_id' => $story->public_id,
                    'photo_expansion_pack_id' => $pack->id,
                    'product_price_schedule_id' => $price->id,
                    'photos' => $pack->photos,
                    'tax_rate' => $rate,
                    'tax_included' => $taxIncluded,
                    'tax_minor' => $taxMinor,
                ],
            ]);

            return $order;
        });
    }
}
