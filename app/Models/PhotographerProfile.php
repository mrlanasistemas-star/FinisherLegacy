<?php

namespace App\Models;

use App\Enums\PhotographerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A photographer selling event photos on Finisher Legacy. Uploading is
 * free; Finisher keeps a commission per sale (config finisher.photos).
 * Payout data (CLABE) is encrypted at rest.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $display_name
 * @property string $slug
 * @property PhotographerStatus $status
 * @property int|null $default_price_minor
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'uuid', 'user_id', 'display_name', 'slug', 'bio', 'city', 'phone', 'instagram_url', 'portfolio_url',
    'status', 'approved_at', 'approved_by', 'default_price_minor', 'payout_holder', 'payout_bank', 'payout_clabe',
])]
#[Hidden(['payout_clabe'])]
class PhotographerProfile extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PhotographerStatus::class,
            'approved_at' => 'datetime',
            'payout_clabe' => 'encrypted',
            'default_price_minor' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isApproved(): bool
    {
        return $this->status === PhotographerStatus::Approved;
    }

    public function maskedClabe(): ?string
    {
        return $this->payout_clabe ? '•••• '.substr((string) $this->payout_clabe, -4) : null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EventPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class);
    }

    /** @return HasMany<PhotoSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(PhotoSale::class);
    }
}
