<?php

namespace App\Enums;

/**
 * A CouponRedemption's lifecycle (consolidation brief §33-§37) —
 * `reserved` is created at checkout time, before payment; it only
 * becomes `redeemed` once App\Actions\Commerce\MarkOrderPaid actually
 * confirms payment. A `reserved` row past its `expires_at` is treated as
 * inactive by App\Actions\Commerce\ValidateCoupon's usage count without
 * ever being written to `released` — only an explicit Order cancellation
 * does that (brief §36).
 */
enum CouponRedemptionStatus: string
{
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Released = 'released';
}
