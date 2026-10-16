<?php

namespace App\Events\Commerce;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * `inventory.updated` (spec §72) — admin commerce only. Exact stock counts
 * never go to a public channel (brief §176-§177).
 */
class InventoryUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $productVariantId,
        public int $inventoryLocationId,
        public int $quantityOnHand,
        public int $quantityReserved,
        public string $movementType,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('commerce.admin')];
    }

    public function broadcastAs(): string
    {
        return 'inventory.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_variant_id' => $this->productVariantId,
            'inventory_location_id' => $this->inventoryLocationId,
            'quantity_on_hand' => $this->quantityOnHand,
            'quantity_reserved' => $this->quantityReserved,
            'available' => $this->quantityOnHand - $this->quantityReserved,
            'movement' => $this->movementType,
        ];
    }
}
