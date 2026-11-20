<?php

namespace App\Actions\Training;

use App\Actions\Commerce\CancelOrder;
use App\Actions\Commerce\ExpirePendingOrder;
use App\Enums\CheerMessageStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CheerMessage;
use App\Models\Order;
use App\Services\Commerce\InventoryService;
use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paid cheer messages whose payment never arrived (scheduled:
 * `training:purge-unpaid-cheers`, every 10 minutes). Past its payment window:
 *
 *   order paid       → deliver it after all (a missed webhook, safety net)
 *   order pending    → expire the Order first (ExpirePendingOrder → it can
 *                      never be marked paid afterwards), then purge
 *   order cancelled  → purge
 *
 * Purge = the audio file (all renditions) is deleted, the text is cleared
 * and the row stays `expired` with its order for the books. The sender is
 * never charged for a purged message: a cancelled Order is never marked paid
 * (MarkOrderPaid), and an OpenPay charge that still lands on it is the
 * manual-refund signal admins already see on the order page.
 */
class PurgeUnpaidCheerMessages
{
    public function __construct(
        private readonly ExpirePendingOrder $expireOrder,
        private readonly DeliverPaidCheerMessages $deliver,
        private readonly MediaStorage $storage,
    ) {}

    /** @return int how many messages were purged */
    public function handle(): int
    {
        $purged = 0;

        CheerMessage::query()
            ->where('status', CheerMessageStatus::PendingPayment->value)
            ->where('payment_expires_at', '<', now())
            ->with('order')
            ->orderBy('id')
            ->chunkById(100, function ($messages) use (&$purged) {
                foreach ($messages as $message) {
                    try {
                        $purged += $this->settle($message) ? 1 : 0;
                    } catch (Throwable $e) {
                        Log::warning('training.cheer_purge_failed', ['message' => $message->uuid, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $purged;
    }

    /**
     * The sender cancelled on the payment page: the order is cancelled
     * right away (only when no charge attempt is in flight) and the message
     * purged. Returns false when it cannot be cancelled now.
     */
    public function cancelBySender(CheerMessage $message, CancelOrder $cancelOrder, InventoryService $inventory): bool
    {
        $order = $message->order;

        if ($order === null || $message->status !== CheerMessageStatus::PendingPayment) {
            return false;
        }

        $inFlight = $order->payments()->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Authorized->value])->exists();

        if ($inFlight || $order->payment_status === OrderPaymentStatus::Paid) {
            return false;
        }

        if ($order->status === OrderStatus::Pending) {
            DB::transaction(function () use ($order, $cancelOrder, $inventory) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                if ($locked !== null && $locked->status === OrderStatus::Pending && $locked->payment_status !== OrderPaymentStatus::Paid) {
                    $cancelOrder->handle($locked, $inventory->defaultLocation());
                }
            });
        }

        return $this->settle($message->refresh());
    }

    private function settle(CheerMessage $message): bool
    {
        $order = $message->order;

        if ($order !== null && $order->payment_status === OrderPaymentStatus::Paid) {
            $this->deliver->handle($order);

            return false;
        }

        if ($order !== null && $order->status === OrderStatus::Pending) {
            // Cancels it (or not, if it is not old enough yet — then wait).
            if ($this->expireOrder->handle($order) === null && $order->refresh()->status !== OrderStatus::Cancelled) {
                return false;
            }
        }

        $audio = DB::transaction(function () use ($message) {
            $locked = CheerMessage::query()->whereKey($message->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== CheerMessageStatus::PendingPayment) {
                return false;
            }

            $audio = $locked->audio;
            $locked->forceFill([
                'status' => CheerMessageStatus::Expired,
                'audio_media_file_id' => null,
                'body' => null,
                'payment_expires_at' => null,
            ])->save();

            return $audio ?? true;
        });

        if ($audio === false) {
            return false;
        }

        if ($audio !== true) {
            $this->storage->delete($audio);
        }

        return true;
    }
}
