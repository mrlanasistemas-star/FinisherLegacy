<?php

namespace App\Models;

use App\Enums\CouponRedemptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per Order a coupon was applied to — the source of truth
 * usage-limit checks count against (see database migration docblock).
 * `status` moves reserved -> redeemed (App\Actions\Commerce\
 * MarkOrderPaid) or reserved -> released (App\Actions\Commerce\
 * CancelOrder); no `updated_at` column, but `status`/`redeemed_at`/
 * `released_at` are still written via `update()`.
 */
#[Fillable(['uuid', 'coupon_id', 'user_id', 'order_id', 'code', 'discount_minor', 'status', 'expires_at', 'redeemed_at', 'released_at'])]
class CouponRedemption extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'status' => CouponRedemptionStatus::class,
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Whether this row currently counts against the coupon's usage
     * limits — redeemed always does; a reservation only does while it
     * hasn't expired (brief §37: no scheduler needed to "release" a
     * stale reservation, it just stops counting).
     */
    public function countsTowardUsage(): bool
    {
        return $this->status === CouponRedemptionStatus::Redeemed
            || ($this->status === CouponRedemptionStatus::Reserved && ($this->expires_at === null || $this->expires_at->isFuture()));
    }

    /**
     * Query-level equivalent of countsTowardUsage() — what
     * App\Actions\Commerce\ValidateCoupon counts against usage limits.
     *
     * @param  Builder<CouponRedemption>  $query
     * @return Builder<CouponRedemption>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', CouponRedemptionStatus::Redeemed)
                ->orWhere(function (Builder $q2) {
                    $q2->where('status', CouponRedemptionStatus::Reserved)
                        ->where(function (Builder $q3) {
                            $q3->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        });
                });
        });
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
