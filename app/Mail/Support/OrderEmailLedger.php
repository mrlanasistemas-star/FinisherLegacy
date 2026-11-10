<?php

namespace App\Mail\Support;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * "Send once" guard for customer order emails (table
 * `order_email_deliveries`, unique on order + kind + reference). A listener
 * claims its row before queueing the Mailable; a webhook retry, a status
 * sync racing the webhook or a second click finds the row already there and
 * sends nothing. insertOrIgnore makes the claim atomic under concurrency.
 */
final class OrderEmailLedger
{
    public const string PAID = 'order_paid';

    public const string REFUNDED = 'order_refunded';

    public const string SHIPPED = 'order_shipped';

    public const string DELIVERED = 'order_delivered';

    /** True only for the first caller; every later call for the same key is false. */
    public static function claim(Order $order, string $kind, string $recipient, string $reference = ''): bool
    {
        return DB::table('order_email_deliveries')->insertOrIgnore([
            'order_id' => $order->id,
            'kind' => $kind,
            'reference' => $reference,
            'recipient' => $recipient,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
