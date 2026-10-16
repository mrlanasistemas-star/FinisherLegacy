<?php

namespace App\Actions\Commerce;

use App\Models\OrderItem;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A delivered unit that comes back (spec §14: "return → inverse
 * movement"). Only for already-fulfilled items and never more units than
 * were delivered minus what already came back — the running total lives
 * in `OrderItem.metadata.returned_quantity`, updated under a row lock so
 * two clicks can't return the same unit twice. Money (refunds) is a
 * payment concern and never happens here.
 */
class ReturnOrderItem
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(OrderItem $item, int $quantity, User $actor, ?string $notes = null): OrderItem
    {
        return DB::transaction(function () use ($item, $quantity, $actor, $notes) {
            $locked = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('product', 'productVariant');

            if ($locked->fulfilled_at === null) {
                throw ValidationException::withMessages(['quantity' => 'Solo se pueden devolver artículos ya entregados.']);
            }

            $alreadyReturned = (int) ($locked->metadata['returned_quantity'] ?? 0);
            $returnable = $locked->quantity - $alreadyReturned;

            if ($quantity < 1 || $quantity > $returnable) {
                throw ValidationException::withMessages(['quantity' => "Puedes devolver hasta {$returnable} unidad(es)."]);
            }

            if ($locked->productVariant !== null && $locked->product?->tracks_inventory) {
                $this->inventory->returnStock(
                    $locked->productVariant,
                    $this->inventory->defaultLocation(),
                    $quantity,
                    OrderItem::class,
                    $locked->id,
                    $actor,
                    $notes,
                );
            }

            $locked->update([
                'metadata' => [...($locked->metadata ?? []), 'returned_quantity' => $alreadyReturned + $quantity],
            ]);

            return $locked->fresh();
        });
    }
}
