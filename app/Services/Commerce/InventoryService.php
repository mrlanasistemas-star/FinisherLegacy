<?php

namespace App\Services\Commerce;

use App\Enums\InventoryMovementType;
use App\Exceptions\ProductOutOfStockException;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every InventoryLevel mutation goes through here, always under
 * `lockForUpdate()` inside its own transaction (brief §53-§54/§147) — never
 * vend/reserve more than available when the product tracks inventory.
 * Products with `tracks_inventory = false` (e.g. Legacy Plate — brief §92)
 * skip the stock check entirely but still get a movement row for audit.
 */
class InventoryService
{
    /**
     * Memoized for this instance's lifetime — see defaultLocation().
     * InventoryService is bound as a singleton (AppServiceProvider), so
     * this correctly stays cached for one request/console run and resets
     * cleanly between tests (each test gets a fresh Application, hence a
     * fresh singleton) — a `static` property would instead leak a stale
     * location across RefreshDatabase-rolled-back test cases.
     */
    private ?InventoryLocation $cachedDefaultLocation = null;

    /**
     * The one location Fase 1 checkout actually reserves/decrements
     * against (consolidation brief §9-§11) — App\Actions\Commerce\
     * CheckoutCart and App\Actions\Commerce\IsVariantAvailableForCheckout
     * both call this instead of each re-deriving it, so "available" can
     * never mean stock at a location checkout will never touch. Cached
     * because IsVariantAvailableForCheckout is called once per variant on
     * a product listing — re-querying this unchanging row every time
     * would be a real N+1 (brief §69).
     */
    public function defaultLocation(): InventoryLocation
    {
        if ($this->cachedDefaultLocation !== null) {
            return $this->cachedDefaultLocation;
        }

        $slug = config('finisher.commerce.default_inventory_location_slug', 'main-warehouse');

        return $this->cachedDefaultLocation = InventoryLocation::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => 'Main Warehouse', 'active' => true],
        );
    }

    public function reserve(ProductVariant $variant, InventoryLocation $location, int $quantity, ?string $referenceType = null, ?int $referenceId = null, ?User $actor = null): InventoryLevel
    {
        return DB::transaction(function () use ($variant, $location, $quantity, $referenceType, $referenceId, $actor) {
            $level = $this->lockedLevel($variant, $location);
            $variant->loadMissing('product');

            if ($variant->product->tracks_inventory && $level->availableQuantity() < $quantity) {
                throw new ProductOutOfStockException;
            }

            $level->increment('quantity_reserved', $quantity);

            $this->record($variant, $location, InventoryMovementType::Reserve, $quantity, $referenceType, $referenceId, $actor);

            return $level->fresh();
        });
    }

    public function release(ProductVariant $variant, InventoryLocation $location, int $quantity, ?string $referenceType = null, ?int $referenceId = null, ?User $actor = null): InventoryLevel
    {
        return DB::transaction(function () use ($variant, $location, $quantity, $referenceType, $referenceId, $actor) {
            $level = $this->lockedLevel($variant, $location);
            $level->update(['quantity_reserved' => max(0, $level->quantity_reserved - $quantity)]);

            $this->record($variant, $location, InventoryMovementType::Release, $quantity, $referenceType, $referenceId, $actor);

            return $level->fresh();
        });
    }

    /**
     * Finalizes a reservation into an actual stock decrease — the pair a
     * paid Order's fulfillment calls once, never twice for the same items
     * (brief §54/§147-§148: no oversell, no double-decrement).
     */
    public function commitSale(ProductVariant $variant, InventoryLocation $location, int $quantity, ?string $referenceType = null, ?int $referenceId = null, ?User $actor = null): InventoryLevel
    {
        return DB::transaction(function () use ($variant, $location, $quantity, $referenceType, $referenceId, $actor) {
            $level = $this->lockedLevel($variant, $location);
            $level->update([
                'quantity_on_hand' => max(0, $level->quantity_on_hand - $quantity),
                'quantity_reserved' => max(0, $level->quantity_reserved - $quantity),
            ]);

            $this->record($variant, $location, InventoryMovementType::Sale, -$quantity, $referenceType, $referenceId, $actor);

            return $level->fresh();
        });
    }

    public function receive(ProductVariant $variant, InventoryLocation $location, int $quantity, ?string $notes = null, ?User $actor = null): InventoryLevel
    {
        return DB::transaction(function () use ($variant, $location, $quantity, $notes, $actor) {
            $level = $this->lockedLevel($variant, $location);
            $level->increment('quantity_on_hand', $quantity);

            $this->record($variant, $location, InventoryMovementType::Receive, $quantity, null, null, $actor, $notes);

            return $level->fresh();
        });
    }

    public function adjust(ProductVariant $variant, InventoryLocation $location, int $delta, ?string $notes = null, ?User $actor = null): InventoryLevel
    {
        return DB::transaction(function () use ($variant, $location, $delta, $notes, $actor) {
            $level = $this->lockedLevel($variant, $location);
            $level->update(['quantity_on_hand' => max(0, $level->quantity_on_hand + $delta)]);

            $this->record($variant, $location, InventoryMovementType::Adjustment, $delta, null, null, $actor, $notes);

            return $level->fresh();
        });
    }

    private function lockedLevel(ProductVariant $variant, InventoryLocation $location): InventoryLevel
    {
        InventoryLevel::firstOrCreate(
            ['product_variant_id' => $variant->id, 'inventory_location_id' => $location->id],
            ['quantity_on_hand' => 0, 'quantity_reserved' => 0],
        );

        return InventoryLevel::query()
            ->where('product_variant_id', $variant->id)
            ->where('inventory_location_id', $location->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function record(
        ProductVariant $variant,
        InventoryLocation $location,
        InventoryMovementType $type,
        int $quantity,
        ?string $referenceType,
        ?int $referenceId,
        ?User $actor,
        ?string $notes = null,
    ): void {
        $variant->inventoryMovements()->create([
            'inventory_location_id' => $location->id,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'actor_id' => $actor?->id,
            'notes' => $notes,
        ]);
    }
}
