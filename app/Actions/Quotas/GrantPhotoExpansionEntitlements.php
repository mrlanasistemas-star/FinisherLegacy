<?php

namespace App\Actions\Quotas;

use App\Enums\FulfillmentStatus;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PhotoExpansionPack;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Called by App\Actions\Commerce\MarkOrderPaid — i.e. only after the
 * backend confirmed the money (verified OpenPay/Stripe webhook, the
 * server-to-server status sync, or an admin-registered manual payment).
 * A client "success" screen can never reach this. Every photo-expansion
 * OrderItem becomes one MediaEntitlement for its story with the photo
 * count frozen at purchase time. Idempotent per order item (unique index
 * + existence check): a replayed confirmation never grants twice.
 */
class GrantPhotoExpansionEntitlements
{
    public const string ITEM_KIND = 'photo_expansion';

    public function handle(Order $order): void
    {
        $order->loadMissing('items');

        $items = $order->items->filter(fn (OrderItem $item) => ($item->metadata['kind'] ?? null) === self::ITEM_KIND);

        if ($items->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            $this->grant($order, $item);
        }

        // Digital delivery: an order made only of fulfilled items is done.
        if ($order->items()->whereNull('fulfilled_at')->doesntExist()) {
            $order->update(['fulfillment_status' => FulfillmentStatus::Fulfilled]);
        }
    }

    private function grant(Order $order, OrderItem $item): void
    {
        if (MediaEntitlement::query()->where('order_item_id', $item->id)->exists()) {
            return;
        }

        $meta = $item->metadata;
        $storyId = (int) ($meta['legacy_story_id'] ?? 0);
        $photos = (int) ($meta['photos'] ?? 0) * max(1, (int) $item->quantity);

        // Soft-deleted stories still get the right they paid for (restorable).
        if ($photos <= 0 || ! LegacyStory::withTrashed()->whereKey($storyId)->exists() || $order->user_id === null) {
            return;
        }

        try {
            MediaEntitlement::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $order->user_id,
                'legacy_story_id' => $storyId,
                'photo_expansion_pack_id' => $meta['photo_expansion_pack_id'] ?? null,
                'product_price_schedule_id' => $meta['product_price_schedule_id'] ?? null,
                'order_item_id' => $item->id,
                'extra_images' => $photos,
                'extra_videos' => 0,
                'source' => PhotoExpansionPack::ENTITLEMENT_SOURCE,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent confirmation already granted this item.
            return;
        }

        if ($item->fulfilled_at === null) {
            $item->update(['fulfilled_at' => now()]);
        }
    }
}
