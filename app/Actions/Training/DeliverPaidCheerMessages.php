<?php

namespace App\Actions\Training;

use App\Enums\CheerChannel;
use App\Enums\CheerMessageStatus;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerSenderCredit;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Runs from MarkOrderPaid — i.e. only once the SERVER confirmed the payment
 * (OpenPay webhook or a server-side re-read of the charge, never a browser
 * redirect). For every paid cheer item of the Order: the pending message
 * becomes visible, counts on its link and the athlete is notified; a pack
 * leaves its remaining messages as credits for the same sender.
 *
 * Idempotent: the message row is locked and only a `pending_payment`
 * message is delivered (a replayed webhook changes nothing), and the pack
 * credit is unique per OrderItem.
 */
class DeliverPaidCheerMessages
{
    public function __construct(private readonly SubmitCheerMessage $submit) {}

    public function handle(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if (($item->metadata['kind'] ?? null) !== CreateCheerMessageOrder::ITEM_KIND) {
                continue;
            }

            $delivered = DB::transaction(fn () => $this->deliver($item));

            if ($delivered !== null) {
                $this->submit->notifyDelivered($delivered);
            }
        }
    }

    /** @return CheerMessage|null the message delivered now (null when already done) */
    private function deliver(OrderItem $item): ?CheerMessage
    {
        $meta = $item->metadata ?? [];
        $message = CheerMessage::query()->whereKey((int) ($meta['cheer_message_id'] ?? 0))->lockForUpdate()->first();

        if ($message === null || $message->status !== CheerMessageStatus::PendingPayment) {
            return null;
        }

        $message->forceFill([
            'status' => CheerMessageStatus::Visible,
            'paid_at' => now(),
            'payment_expires_at' => null,
        ])->save();

        CheerLink::query()->whereKey($message->cheer_link_id)->increment('messages_count');

        $pack = (int) ($meta['messages'] ?? 1);
        $senderKey = $meta['sender_key_hash'] ?? null;

        if ($pack > 1 && is_string($senderKey) && $senderKey !== '') {
            CheerSenderCredit::query()->firstOrCreate(['order_item_id' => $item->id], [
                'cheer_link_id' => $message->cheer_link_id,
                'legacy_tag_id' => $message->legacy_tag_id,
                'channel' => CheerChannel::tryFrom((string) ($meta['channel'] ?? '')) ?? CheerChannel::Link,
                'sender_key_hash' => $senderKey,
                'credits_total' => $pack - 1,
                'credits_remaining' => $pack - 1,
            ]);
        }

        activity('training')->performedOn($message)
            ->withProperties(['order' => $item->order_id, 'amount_minor' => $item->line_total_minor, 'pack' => $pack])
            ->log('cheer.paid_message_delivered');

        return $message;
    }
}
