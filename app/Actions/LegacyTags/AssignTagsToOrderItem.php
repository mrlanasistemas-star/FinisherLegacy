<?php

namespace App\Actions\LegacyTags;

use App\Enums\DeliveryMethod;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Enums\SalesChannel;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Online / shipped orders: when an item of a serialized product
 * (`products.tracks_units`) is fulfilled, the actual pieces leaving the
 * shelf are bound to it — SKU → unit → order → buyer. Runs inside
 * FulfillOrderItem's transaction (aggregate stock is committed there).
 * Shipped pieces are `sold` until delivery is confirmed; picked-up /
 * in-person ones are `delivered` at once.
 */
class AssignTagsToOrderItem
{
    public function __construct(private readonly TagLifecycle $lifecycle) {}

    /**
     * @param  list<string>  $codes  optional: exact pieces scanned while packing
     * @return Collection<int, LegacyTag>
     */
    public function handle(OrderItem $item, InventoryLocation $location, ?User $actor = null, array $codes = []): Collection
    {
        $item->loadMissing('order');
        $order = $item->order;

        $query = LegacyTag::query()
            ->where('product_variant_id', $item->product_variant_id)
            ->where('inventory_location_id', $location->id)
            ->where('status', LegacyTagStatus::InStock->value);

        if ($codes !== []) {
            $query->whereIn('public_code', $codes);
        }

        $tags = $query->orderBy('received_at')->orderBy('id')->limit($item->quantity)->lockForUpdate()->get();

        if ($tags->count() < $item->quantity) {
            throw new InvalidArgumentException("No hay suficientes piezas registradas de {$item->sku} en {$location->name} para surtir este pedido.");
        }

        $delivered = $order->delivery_method !== DeliveryMethod::Shipping;

        foreach ($tags as $tag) {
            $this->lifecycle->transition($tag, LegacyTagEventType::Sold, $delivered ? LegacyTagStatus::Delivered : LegacyTagStatus::Sold, [
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'sales_channel' => $order->sales_channel ?? SalesChannel::Online,
                'ally_id' => $order->ally_id,
                'sold_at' => now(),
                'delivered_at' => $delivered ? now() : null,
            ], $actor, null, ['order' => $order->order_number]);
        }

        return $tags;
    }

    /** Shipping confirmed delivered: the pieces are now in the buyer's hands. */
    public function markDelivered(OrderItem $item, ?User $actor = null): void
    {
        foreach ($item->legacyTags()->where('status', LegacyTagStatus::Sold->value)->lockForUpdate()->get() as $tag) {
            $this->lifecycle->transition($tag, LegacyTagEventType::Delivered, LegacyTagStatus::Delivered, ['delivered_at' => now()], $actor);
        }
    }
}
