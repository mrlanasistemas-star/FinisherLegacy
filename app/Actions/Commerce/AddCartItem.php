<?php

namespace App\Actions\Commerce;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Exceptions\CartEventMismatchException;
use App\Exceptions\LegacyPlateEventRequiredException;
use App\Exceptions\LegacyPlateModelRequiredException;
use App\Exceptions\LegacyPlateModelUnavailableException;
use App\Exceptions\LegacyPlateQuantityInvalidException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EventEdition;
use App\Models\LegacyPlateModel;
use App\Models\ProductVariant;

class AddCartItem
{
    /**
     * @param  array<string, mixed>  $metadata  For a Legacy Plate line, carries `legacy_plate_model_id`.
     */
    public function handle(Cart $cart, ProductVariant $variant, int $quantity = 1, ?EventEdition $eventEdition = null, array $metadata = []): CartItem
    {
        $variant->loadMissing('product');
        $product = $variant->product;

        // The storefront catalog only ever lists active products/variants
        // — never trusted client-side, so re-checked here too (brief item
        // 34: a stale page or a hand-crafted request must not be able to
        // add something no longer for sale).
        if (! $variant->active || ! $product->active || $product->status !== ProductStatus::Active) {
            throw new ProductUnavailableException;
        }

        // A Legacy Plate line is meaningless without an event/model — never
        // add one "incomplete" and hope CheckoutCart catches it later
        // (consolidation brief §2-§3: the same rules apply here, not just
        // at checkout).
        if ($product->type === ProductType::LegacyPlate) {
            $this->validateLegacyPlateLine($eventEdition, $metadata, $quantity);
        }

        // One cart, at most one EventEdition — Order.event_edition_id has
        // nowhere to put a second one (consolidation brief §6-§8).
        if ($eventEdition !== null) {
            $this->guardSingleEventCart($cart, $eventEdition);
        }

        $item = $cart->items()
            ->where('product_variant_id', $variant->id)
            ->where('event_edition_id', $eventEdition?->id)
            ->first();

        if ($item !== null) {
            // Re-adding the exact same (variant, event) Legacy Plate line
            // is a no-op, never a quantity bump past 1.
            if ($product->type === ProductType::LegacyPlate) {
                return $item;
            }

            $item->update(['quantity' => $item->quantity + $quantity]);

            return $item->fresh();
        }

        return $cart->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'event_edition_id' => $eventEdition?->id,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function validateLegacyPlateLine(?EventEdition $eventEdition, array $metadata, int $quantity): void
    {
        if ($eventEdition === null) {
            throw new LegacyPlateEventRequiredException;
        }

        $modelId = $metadata['legacy_plate_model_id'] ?? null;

        if ($modelId === null) {
            throw new LegacyPlateModelRequiredException;
        }

        $model = LegacyPlateModel::query()->find((int) $modelId);

        if ($model === null || ! $model->active) {
            throw new LegacyPlateModelUnavailableException;
        }

        if ($quantity !== 1) {
            throw new LegacyPlateQuantityInvalidException;
        }
    }

    private function guardSingleEventCart(Cart $cart, EventEdition $eventEdition): void
    {
        $hasOtherEvent = $cart->items()
            ->whereNotNull('event_edition_id')
            ->where('event_edition_id', '!=', $eventEdition->id)
            ->exists();

        if ($hasOtherEvent) {
            throw new CartEventMismatchException;
        }
    }
}
