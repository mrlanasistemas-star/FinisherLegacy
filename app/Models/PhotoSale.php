<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One paid photo: the exact money split frozen at payment time, with the
 * commission terms in force then (commission_source/commission_setting_id)
 * — a later rate change never rewrites it.
 *
 *   photographer absorbs the gateway fee: gross = commission + gateway_fee + net
 *   Finisher absorbs it:                  gross = commission + net
 *
 * Aliases (read-only) for the commission vocabulary:
 * commission_rate = commission_percent, commission_minor =
 * platform_fee_minor, gateway_fee_minor = processor_fee_minor.
 *
 * Refund: `refunded_at` + `reversed_net_minor` reverse the net. A pending
 * sale is then excluded from payouts (payout_status = reversed); one
 * already paid out becomes a clawback deducted from the next payout
 * (`reversal_payout_id` once settled).
 *
 * @property string|null $commission_source config | global | override
 * @property int|null $commission_setting_id
 * @property string $gateway_fee_absorbed_by photographer | finisher
 * @property Carbon|null $refunded_at
 * @property int|null $reversed_net_minor
 * @property int|null $reversed_commission_minor
 * @property string|null $reversal_reason
 * @property int|null $reversal_payout_id
 * @property int|null $photographer_payout_id
 * @property-read float $commission_rate
 * @property-read int $commission_minor
 * @property-read int $gateway_fee_minor
 * @property int $id
 * @property string $uuid
 * @property int $event_photo_id
 * @property int $photographer_profile_id
 * @property int $order_id
 * @property int $order_item_id
 * @property int|null $buyer_user_id
 * @property int $gross_minor
 * @property int $platform_fee_minor
 * @property int $processor_fee_minor Fee frozen in the split (estimate unless reconciled at sale time)
 * @property bool $processor_fee_estimated
 * @property int|null $processor_fee_actual_minor Real fee reported by the gateway, when known
 * @property Carbon|null $processor_fee_reconciled_at
 * @property string|null $payment_provider openpay | stripe | manual
 * @property int $photographer_net_minor
 * @property string $commission_percent
 * @property string $currency
 * @property string $payout_status
 * @property Carbon|null $paid_out_at
 * @property int $download_count
 * @property Carbon $created_at
 */
#[Fillable([
    'uuid', 'event_photo_id', 'photographer_profile_id', 'order_id', 'order_item_id', 'buyer_user_id',
    'gross_minor', 'platform_fee_minor', 'processor_fee_minor', 'photographer_net_minor', 'commission_percent',
    'currency', 'payout_status', 'paid_out_at', 'photographer_payout_id', 'download_count',
    'payment_provider', 'processor_fee_estimated', 'processor_fee_actual_minor', 'processor_fee_reconciled_at',
    'commission_source', 'commission_setting_id', 'gateway_fee_absorbed_by', 'refunded_at',
    'reversed_net_minor', 'reversed_commission_minor', 'reversal_reason', 'reversal_payout_id',
])]
class PhotoSale extends Model
{
    public const string PAYOUT_PENDING = 'pending';

    public const string PAYOUT_PAID = 'paid';

    /** Refunded before it was paid out: never owed to the photographer. */
    public const string PAYOUT_REVERSED = 'reversed';

    /**
     * Sales whose net is owed and not yet transferred.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePayoutDue(Builder $query): Builder
    {
        return $query->where('payout_status', self::PAYOUT_PENDING)->whereNull('refunded_at');
    }

    /**
     * Refunded after being paid out: to deduct from the next payout.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeClawbackDue(Builder $query): Builder
    {
        return $query->where('payout_status', self::PAYOUT_PAID)
            ->whereNotNull('refunded_at')
            ->whereNull('reversal_payout_id');
    }

    /**
     * Sales that still grant the buyer the photo. A refunded sale (the money
     * went back) no longer does: it stops unlocking the full-resolution
     * download and the photo can be bought again.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeGrantingAccess(Builder $query): Builder
    {
        return $query->whereNull('refunded_at');
    }

    /** @return Attribute<float, never> */
    protected function commissionRate(): Attribute
    {
        return Attribute::get(fn () => (float) $this->commission_percent);
    }

    /** @return Attribute<int, never> */
    protected function commissionMinor(): Attribute
    {
        return Attribute::get(fn () => (int) $this->platform_fee_minor);
    }

    /** @return Attribute<int, never> */
    protected function gatewayFeeMinor(): Attribute
    {
        return Attribute::get(fn () => (int) $this->processor_fee_minor);
    }

    public function isReversed(): bool
    {
        return $this->refunded_at !== null;
    }

    protected function casts(): array
    {
        return [
            'refunded_at' => 'datetime',
            'reversed_net_minor' => 'integer',
            'reversed_commission_minor' => 'integer',
            'gross_minor' => 'integer',
            'platform_fee_minor' => 'integer',
            'processor_fee_minor' => 'integer',
            'photographer_net_minor' => 'integer',
            'paid_out_at' => 'datetime',
            'processor_fee_estimated' => 'boolean',
            'processor_fee_actual_minor' => 'integer',
            'processor_fee_reconciled_at' => 'datetime',
        ];
    }

    /**
     * Records the gateway's real processing fee next to the estimate.
     * Deliberately does NOT rewrite processor_fee_minor nor the
     * photographer's net: the split the photographer was shown stays
     * frozen. The difference (actual − estimate) is Finisher's to absorb
     * or settle explicitly — it is returned so a report/admin action can
     * surface it, never applied silently.
     */
    public function reconcileProcessorFee(int $actualFeeMinor): int
    {
        $this->update([
            'processor_fee_actual_minor' => $actualFeeMinor,
            'processor_fee_reconciled_at' => now(),
        ]);

        return $actualFeeMinor - $this->processor_fee_minor;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<EventPhoto, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(EventPhoto::class, 'event_photo_id');
    }

    /** @return BelongsTo<PhotographerProfile, $this> */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_profile_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
