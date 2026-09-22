<?php

namespace App\Actions\Commerce;

use App\Enums\CouponRedemptionStatus;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Shared by RegisterManualPayment and ProcessPaymentWebhook so "what
 * happens once an Order is fully paid" is defined in exactly one place
 * (brief §83: compose, no God Action) — flips payment_status, confirms the
 * Order if it's still pending, moves any Legacy Plate entitlement this
 * Order funded from pending_payment to paid (brief §81/§100: paid enables
 * production, it never triggers it), and — if this Order used a coupon —
 * promotes its CouponRedemption from `reserved` to `redeemed` (brief
 * §35: a coupon is only actually spent once the Order is really paid).
 */
class MarkOrderPaid
{
    public function __construct(private readonly ConfirmOrder $confirmOrder) {}

    public function handle(Order $order): Order
    {
        // A late webhook/manual entry for an Order App\Actions\Commerce\
        // ExpirePendingOrder already cancelled — its reserved inventory
        // and coupon slot were already released back for someone else, so
        // never silently resurrect it (consolidation brief §23/§26). The
        // caller (ProcessPaymentWebhook/RegisterManualPayment) still
        // records the Payment row itself before/regardless of this call —
        // the money is never lost, it just surfaces as a paid Payment on
        // a cancelled Order, which is the manual-review signal an admin
        // sees on that Order's page.
        if ($order->status === OrderStatus::Cancelled) {
            return $order;
        }

        $order->update(['payment_status' => OrderPaymentStatus::Paid]);
        $order = $order->fresh();

        if ($order->status === OrderStatus::Pending) {
            $order = $this->confirmOrder->handle($order);
        }

        $order->loadMissing('items.legacyPlateEntitlement');

        foreach ($order->items as $item) {
            $entitlement = $item->legacyPlateEntitlement;

            if ($entitlement === null || $entitlement->status !== LegacyPlateEntitlementStatus::PendingPayment) {
                continue;
            }

            $entitlement->update([
                'status' => $entitlement->event_participant_id !== null
                    ? LegacyPlateEntitlementStatus::Linked
                    : LegacyPlateEntitlementStatus::Paid,
                'paid_at' => now(),
            ]);
        }

        $this->redeemCoupon($order);

        return $order->fresh();
    }

    /**
     * Re-validates the coupon's usage limit under a row lock at the exact
     * moment of promotion — not just at checkout time (consolidation
     * brief §14/§24-§25/§60). Closes the race where: Order A reserves the
     * last use, A's reservation expires, Order B reserves+pays the same
     * slot, and then A's payment webhook arrives late — A's reservation
     * must never also become `redeemed` once the limit is already spent
     * by B, or the coupon is used twice past its limit.
     */
    private function redeemCoupon(Order $order): void
    {
        $redemption = CouponRedemption::query()
            ->where('order_id', $order->id)
            ->where('status', CouponRedemptionStatus::Reserved)
            ->first();

        if ($redemption === null) {
            return;
        }

        DB::transaction(function () use ($redemption) {
            $coupon = Coupon::query()->whereKey($redemption->coupon_id)->lockForUpdate()->first();

            if ($coupon === null) {
                return;
            }

            if ($coupon->usage_limit_total !== null) {
                $activeExcludingThis = $coupon->redemptions()
                    ->active()
                    ->where('id', '!=', $redemption->id)
                    ->count();

                if ($activeExcludingThis >= $coupon->usage_limit_total) {
                    // The Order still keeps its paid status (real money
                    // was captured) — only this reservation can never
                    // become a second redemption past the limit.
                    $redemption->update(['status' => CouponRedemptionStatus::Released, 'released_at' => now()]);

                    return;
                }
            }

            $redemption->update(['status' => CouponRedemptionStatus::Redeemed, 'redeemed_at' => now()]);
        });
    }
}
