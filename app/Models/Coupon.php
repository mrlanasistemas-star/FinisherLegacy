<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Validity/eligibility rules live in App\Actions\Commerce\ValidateCoupon,
 * never here — this model is data, not a rule engine (brief §32/§35).
 */
#[Fillable([
    'uuid', 'code', 'name', 'description', 'type', 'value', 'currency',
    'starts_at', 'ends_at', 'usage_limit_total', 'usage_limit_per_user',
    'minimum_order_minor', 'active', 'metadata', 'created_by',
])]
class Coupon extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit_total' => 'integer',
            'usage_limit_per_user' => 'integer',
            'minimum_order_minor' => 'integer',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Codes are always stored/compared uppercase — "spring10" and
     * "SPRING10" are the same coupon, never two different rows.
     *
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtoupper(trim($value)));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
