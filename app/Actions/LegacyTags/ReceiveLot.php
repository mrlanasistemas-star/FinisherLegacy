<?php

namespace App\Actions\LegacyTags;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryLotStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\LegacyTag;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The lot arrived: its manufactured tags become sellable stock at a
 * location. Pieces that arrived damaged are disabled (never counted as
 * stock) and noted on the lot. Aggregate stock (inventory_levels) moves by
 * exactly the number of good pieces, through InventoryService.
 */
class ReceiveLot
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly TagLifecycle $lifecycle,
    ) {}

    /**
     * @param  list<string>  $defectiveCodes  public codes or serials that arrived damaged
     */
    public function handle(InventoryLot $lot, InventoryLocation $location, User $actor, array $defectiveCodes = [], ?string $issues = null): InventoryLot
    {
        return DB::transaction(function () use ($lot, $location, $actor, $defectiveCodes, $issues) {
            /** @var InventoryLot $lot */
            $lot = InventoryLot::query()->lockForUpdate()->findOrFail($lot->id);

            if ($lot->status !== InventoryLotStatus::Ordered) {
                throw new InvalidArgumentException('Este lote ya fue recibido.');
            }

            $tags = LegacyTag::query()->where('inventory_lot_id', $lot->id)
                ->where('status', LegacyTagStatus::Manufactured->value)
                ->lockForUpdate()->get();

            $good = 0;

            foreach ($tags as $tag) {
                $defective = in_array($tag->public_code, $defectiveCodes, true) || in_array($tag->serial, $defectiveCodes, true);

                if ($defective) {
                    $this->lifecycle->transition($tag, LegacyTagEventType::Disabled, LegacyTagStatus::Disabled, [
                        'inventory_location_id' => $location->id,
                        'disabled_reason' => 'defect_on_receipt',
                    ], $actor, 'Llegó con defecto de fábrica');

                    continue;
                }

                $this->lifecycle->transition($tag, LegacyTagEventType::Received, LegacyTagStatus::InStock, [
                    'inventory_location_id' => $location->id,
                    'received_at' => now(),
                ], $actor);
                $good++;
            }

            if ($good > 0) {
                $lot->loadMissing('productVariant.product');
                $this->inventory->receive($lot->productVariant, $location, $good, "Lote {$lot->code}", $actor, InventoryAdjustmentReason::Receipt->value);
            }

            $lot->update([
                'status' => InventoryLotStatus::Received,
                'inventory_location_id' => $location->id,
                'quantity_received' => $good,
                'received_at' => now(),
                'issues' => $issues ?? $lot->issues,
            ]);

            activity()->causedBy($actor)->performedOn($lot)
                ->withProperties(['received' => $good, 'defective' => $tags->count() - $good, 'location' => $location->slug])
                ->log('tags.lot.received');

            return $lot->fresh();
        });
    }
}
