<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One transfer to a photographer, registered by an admin. It settles the
 * pending PhotoSales it covers (photo_sales.photographer_payout_id).
 */
#[Fillable([
    'uuid', 'photographer_profile_id', 'amount_minor', 'currency', 'sales_count',
    'reference', 'notes', 'paid_at', 'recorded_by',
])]
class PhotographerPayout extends Model
{
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'sales_count' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PhotographerProfile, $this> */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return HasMany<PhotoSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(PhotoSale::class);
    }
}
