<?php

namespace App\Actions\Photos;

use App\Enums\FulfillmentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PhotoSale;
use App\Services\Photos\PhotoFeeCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Called by MarkOrderPaid: every paid photo OrderItem becomes a PhotoSale
 * with the money split frozen (Finisher commission, card processor fee,
 * photographer net) and the item is fulfilled immediately (digital
 * delivery). Idempotent per order item — a late or repeated webhook never
 * creates a second sale.
 */
class RecordPhotoSales
{
    public function __construct(private readonly PhotoFeeCalculator $fees) {}

    public function handle(Order $order): void
    {
        $order->loadMissing('items.eventPhoto');
        $photoItems = $order->items->filter(fn (OrderItem $item) => $item->event_photo_id !== null);

        if ($photoItems->isEmpty()) {
            return;
        }

        $itemsInOrder = $order->items->count();

        foreach ($photoItems as $item) {
            $photo = $item->eventPhoto;

            if ($photo === null || PhotoSale::query()->where('order_item_id', $item->id)->exists()) {
                continue;
            }

            $split = $this->fees->split((int) $item->line_total_minor, $itemsInOrder);

            try {
                PhotoSale::create([
                    'uuid' => (string) Str::uuid(),
                    'event_photo_id' => $photo->id,
                    'photographer_profile_id' => $photo->photographer_profile_id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'buyer_user_id' => $order->user_id,
                    'gross_minor' => $split['gross_minor'],
                    'platform_fee_minor' => $split['platform_fee_minor'],
                    'processor_fee_minor' => $split['processor_fee_minor'],
                    'photographer_net_minor' => $split['photographer_net_minor'],
                    'commission_percent' => $split['commission_percent'],
                    'currency' => $item->currency,
                    'payout_status' => 'pending',
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $item->update(['fulfilled_at' => now()]);
        }

        if ($order->items()->whereNull('fulfilled_at')->doesntExist()) {
            $order->update(['fulfillment_status' => FulfillmentStatus::Fulfilled]);
        }
    }
}
