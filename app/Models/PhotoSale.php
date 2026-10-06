<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One paid photo: the exact money split frozen at payment time —
 * gross = platform_fee + processor_fee + photographer_net.
 *
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
    'currency', 'payout_status', 'paid_out_at', 'download_count',
    'payment_provider', 'processor_fee_estimated', 'processor_fee_actual_minor', 'processor_fee_reconciled_at',
])]
class PhotoSale extends Model
{
    protected function casts(): array
    {
        return [
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
