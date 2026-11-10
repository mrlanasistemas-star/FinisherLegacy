<?php

namespace App\Actions\Quotas;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\StoryQuotaKind;
use App\Models\LegacyStory;
use App\Models\Order;
use App\Models\PhotoExpansionPack;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\StoryMediaQuota;
use App\Support\CodeGenerator;
use App\Support\Quotas\ResolvedExpansionPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns "buy more space for this story" (photos, videos or voice
 * messages) into a regular pending Order (one OrderItem on the internal
 * `ampliacion-fotos-recuerdo` product, metadata kind=photo_expansion) —
 * the same pattern as CheckoutEventPhotos. Payment then goes through the
 * existing order payment flow; the capacity is granted only by
 * GrantPhotoExpansionEntitlements once MarkOrderPaid runs.
 *
 * Everything that decides the charge is resolved HERE, server-side, and
 * frozen into the item: the price row in force, the price step that
 * matched what the story already holds, amount, currency, tax, units and
 * billing period. Later tariff or pack changes never touch it.
 */
class CheckoutPhotoExpansion
{
    public function __construct(private readonly StoryMediaQuota $quota) {}

    public function handle(User $buyer, LegacyStory $story, ?int $packId = null, StoryQuotaKind $kind = StoryQuotaKind::Photos): Order
    {
        if (! $story->isOwnedBy($buyer)) {
            throw ValidationException::withMessages(['expansion' => __('Solo quien es dueño del recuerdo puede ampliar su espacio.')]);
        }

        $pack = $packId !== null
            ? PhotoExpansionPack::query()->sellable()->whereKey($packId)->first()
            : $this->quota->expansionPackFor($story, $kind);

        if ($pack !== null && ! $this->quota->packCovers($story, $pack)) {
            $pack = null;
        }

        $price = $pack !== null ? $this->quota->resolvePrice($pack, $story) : null;

        if ($pack === null || $price === null) {
            throw ValidationException::withMessages(['expansion' => __('Esta ampliación ya no está disponible. Recarga la página.')]);
        }

        if (! $this->quota->fitsCap($story, $pack)) {
            throw ValidationException::withMessages(['expansion' => match ($pack->kind) {
                StoryQuotaKind::Photos => __('Este recuerdo ya llegó al máximo de fotos permitido.'),
                StoryQuotaKind::Videos => __('Este recuerdo ya llegó al máximo de videos permitido.'),
                StoryQuotaKind::Audio => __('Este recuerdo ya llegó al máximo de mensajes de voz permitido.'),
            }]);
        }

        $pending = Order::query()
            ->where('user_id', $buyer->id)
            ->where('status', OrderStatus::Pending)
            ->where('payment_status', OrderPaymentStatus::Pending)
            ->whereHas('items', fn ($q) => $q
                ->where('metadata->kind', GrantPhotoExpansionEntitlements::ITEM_KIND)
                ->where('metadata->legacy_story_id', $story->id)
                ->where('metadata->photo_expansion_pack_id', $pack->id))
            ->latest('id')
            ->first();

        if ($pending !== null) {
            throw ValidationException::withMessages([
                'expansion' => __('Ya tienes esta ampliación en el pedido :order, pendiente de pago. Complétalo desde Mis pedidos — no se cobró nada.', ['order' => $pending->order_number]),
                'pending_order' => $pending->uuid,
            ]);
        }

        $product = Product::query()->where('slug', PhotoExpansionPack::PRODUCT_SLUG)->firstOrFail();
        $schedule = $price->schedule;

        $amount = $price->amountMinor;
        $rate = $schedule->tax_rate !== null ? (float) $schedule->tax_rate : 0.0;
        $taxIncluded = $schedule->tax_included || $rate <= 0.0;
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
                'currency' => $price->schedule->currency,
                'customer_snapshot' => ['name' => $buyer->name, 'email' => $buyer->email],
            ]);

            $order->items()->create([
                'uuid' => (string) Str::uuid(),
                'product_id' => $product->id,
                'name' => $pack->lineName(),
                'sku' => 'PHOTO-EXP-'.$pack->id,
                'quantity' => 1,
                'unit_price_minor' => $amount,
                'line_total_minor' => $amount,
                'currency' => $price->schedule->currency,
                'metadata' => [
                    ...self::itemMetadata($story, $pack, $price),
                    'tax_rate' => $rate,
                    'tax_included' => $taxIncluded,
                    'tax_minor' => $taxMinor,
                ],
            ]);

            return $order;
        });
    }

    /**
     * What an expansion order item freezes about the pack, the story and
     * the resolved price (shared with the in-app purchase path).
     *
     * @return array<string, mixed>
     */
    public static function itemMetadata(LegacyStory $story, PhotoExpansionPack $pack, ?ResolvedExpansionPrice $price): array
    {
        return [
            'kind' => GrantPhotoExpansionEntitlements::ITEM_KIND,
            'legacy_story_id' => $story->id,
            'legacy_story_public_id' => $story->public_id,
            'photo_expansion_pack_id' => $pack->id,
            'product_price_schedule_id' => $price?->schedule->id,
            'expansion_kind' => $pack->kind->value,
            'units' => $pack->units(),
            // Pre-kinds name of `units`, still read by older code paths.
            'photos' => $pack->units(),
            'billing_period' => $pack->billing_period->value,
            'price_step_id' => $price?->step?->id,
            'step_from_units' => $price?->fromUnits,
            'step_to_units' => $price?->toUnits,
            'held_units' => $price?->heldUnits,
        ];
    }
}
