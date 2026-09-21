<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Order a coupon was actually applied to — the source of
 * truth usage-limit checks count against (see database migration
 * docblock). Insert-only, no `updated_at`.
 */
#[Fillable(['uuid', 'coupon_id', 'user_id', 'order_id', 'code', 'discount_minor'])]
class CouponRedemption extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
