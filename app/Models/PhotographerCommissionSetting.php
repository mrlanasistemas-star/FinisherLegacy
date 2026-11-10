<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One append-only row of photographer commission terms (the table is the
 * history). `global` rows carry the default commission % and the gateway
 * fee terms; `photographer` rows override the commission % for one
 * photographer (null % = override removed). Resolved by
 * App\Services\Commissions\CommissionResolver — never read directly to
 * compute money.
 *
 * @property int $id
 * @property string $uuid
 * @property string $scope global | photographer
 * @property int|null $photographer_profile_id
 * @property string|null $commission_percent
 * @property string|null $gateway_fee_percent
 * @property int|null $gateway_fee_fixed_minor
 * @property string|null $gateway_fee_absorbed_by photographer | finisher
 * @property Carbon $effective_from
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property Carbon $created_at
 */
#[Fillable([
    'uuid', 'scope', 'photographer_profile_id', 'commission_percent', 'gateway_fee_percent',
    'gateway_fee_fixed_minor', 'gateway_fee_absorbed_by', 'effective_from', 'notes', 'created_by',
    'cancelled_at', 'cancelled_by',
])]
class PhotographerCommissionSetting extends Model
{
    public const string SCOPE_GLOBAL = 'global';

    public const string SCOPE_PHOTOGRAPHER = 'photographer';

    public const string ABSORBED_BY_PHOTOGRAPHER = 'photographer';

    public const string ABSORBED_BY_FINISHER = 'finisher';

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'cancelled_at' => 'datetime',
            'gateway_fee_fixed_minor' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isPending(): bool
    {
        return $this->cancelled_at === null && $this->effective_from->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /** @return BelongsTo<PhotographerProfile, $this> */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
