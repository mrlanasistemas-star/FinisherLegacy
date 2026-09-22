<?php

namespace App\Console\Commands;

use App\Actions\Commerce\ExpirePendingOrder;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Runs on the schedule (routes/console.php) — a pending, unpaid Order
 * older than `finisher.commerce.order_payment_expiry_minutes` stops
 * holding its inventory/coupon reservation forever (consolidation brief
 * §15-§21). Chunked by id, never loads the whole pending-order table into
 * memory at once (brief §18).
 */
class ExpirePendingOrders extends Command
{
    protected $signature = 'finisher:expire-pending-orders';

    protected $description = 'Cancel pending, unpaid Orders older than the configured payment window and release what they reserved.';

    public function handle(ExpirePendingOrder $expireOrder): int
    {
        $minutes = (int) config('finisher.commerce.order_payment_expiry_minutes', 60);
        $cutoff = now()->subMinutes($minutes);
        $expired = 0;

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('payment_status', '!=', OrderPaymentStatus::Paid)
            ->where('created_at', '<=', $cutoff)
            ->chunkById(100, function ($orders) use ($expireOrder, &$expired) {
                foreach ($orders as $order) {
                    if ($expireOrder->handle($order) !== null) {
                        $expired++;
                    }
                }
            });

        $this->info("Pedidos expirados: {$expired}");

        return self::SUCCESS;
    }
}
