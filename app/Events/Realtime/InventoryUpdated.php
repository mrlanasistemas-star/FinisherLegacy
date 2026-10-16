<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * `inventory.updated` — dispatched by App\Services\Commerce\InventoryService
 * after every stock movement. Staff only (admin.commerce): exact stock
 * counts never go to a public or customer channel (brief §176-§177).
 */
class InventoryUpdated extends RealtimeEvent
{
    public const string NAME = 'inventory.updated';

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
        return [$this->adminCommerceChannel()];
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
