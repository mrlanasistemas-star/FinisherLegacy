<?php

namespace App\Actions\Photos;

use App\Models\Order;
use App\Models\PhotoSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a refunded photo sale: the photographer's net and Finisher's
 * commission are marked reversed (`refunded_at`, `reversed_net_minor`,
 * `reversed_commission_minor`). The original snapshot is never edited.
 *
 *  - still pending payout → payout_status `reversed`: never owed;
 *  - already paid out     → stays `paid` and becomes a clawback that the
 *    next PhotographerPayout deducts (reversal_payout_id).
 *
 * Idempotent: a sale already reversed is left untouched. Called for every
 * photo of a fully refunded Order (App\Listeners\PhotoSaleRefundReversal)
 * and by an admin for a single sale (partial refunds).
 */
class ReversePhotoSale
{
    public function handle(PhotoSale $sale, string $reason, ?User $by = null): PhotoSale
    {
        return DB::transaction(function () use ($sale, $reason, $by) {
            $locked = PhotoSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->refunded_at !== null) {
                return $locked;
            }

            $locked->update([
                'refunded_at' => now(),
                'reversed_net_minor' => $locked->photographer_net_minor,
                'reversed_commission_minor' => $locked->platform_fee_minor,
                'reversal_reason' => mb_substr($reason, 0, 255),
                'payout_status' => $locked->payout_status === PhotoSale::PAYOUT_PENDING
                    ? PhotoSale::PAYOUT_REVERSED
                    : $locked->payout_status,
            ]);

            activity('commissions')
                ->causedBy($by)
                ->performedOn($locked)
                ->event('updated')
                ->withProperties([
                    'reason' => $locked->reversal_reason,
                    'reversed_net_minor' => $locked->reversed_net_minor,
                    'reversed_commission_minor' => $locked->reversed_commission_minor,
                    'clawback' => $locked->payout_status === PhotoSale::PAYOUT_PAID,
                ])
                ->log('Revirtió una venta de foto por reembolso');

            return $locked;
        });
    }

    /**
     * @return int Sales reversed by this call.
     */
    public function forOrder(Order $order, string $reason, ?User $by = null): int
    {
        $count = 0;

        PhotoSale::query()
            ->where('order_id', $order->id)
            ->whereNull('refunded_at')
            ->get()
            ->each(function (PhotoSale $sale) use ($reason, $by, &$count) {
                $this->handle($sale, $reason, $by);
                $count++;
            });

        return $count;
    }
}
