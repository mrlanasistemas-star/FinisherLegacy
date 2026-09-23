<?php

namespace App\Models;

use App\Enums\MomentType;
use App\Enums\MomentVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A shared piece of an athlete's sporting story — see
 * docs/SOCIAL_ARCHITECTURE.md. Who may see it is decided only by
 * App\Services\Social\SocialVisibility (never by the client).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property MomentType $type
 * @property string|null $caption
 * @property MomentVisibility $visibility
 * @property int|null $event_participant_id
 * @property int|null $medal_id
 * @property int|null $athlete_owned_product_id
 * @property array<string, mixed>|null $metrics
 * @property Carbon $created_at
 * @property-read int|null $likes_count Set by MomentQuery::withCardData()
 * @property-read int|null $cheers_count Set by MomentQuery::withCardData()
 * @property-read int|null $comments_count Set by MomentQuery::withCardData()
 */
#[Fillable([
    'uuid', 'user_id', 'type', 'caption', 'visibility', 'event_participant_id',
    'medal_id', 'athlete_owned_product_id', 'metrics',
])]
class LegacyMoment extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (LegacyMoment $moment) {
            $moment->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => MomentType::class,
            'visibility' => MomentVisibility::class,
            'metrics' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    /** @return BelongsTo<Medal, $this> */
    public function medal(): BelongsTo
    {
        return $this->belongsTo(Medal::class);
    }

    /** @return BelongsTo<AthleteOwnedProduct, $this> */
    public function gear(): BelongsTo
    {
        return $this->belongsTo(AthleteOwnedProduct::class, 'athlete_owned_product_id');
    }

    /** @return HasMany<LegacyMomentMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(LegacyMomentMedia::class)->orderBy('sort_order');
    }

    /** @return HasMany<LegacyMomentReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(LegacyMomentReaction::class);
    }

    /**
     * Eager-loaded constrained to the current viewer (see
     * App\Queries\Social\MomentQuery) — "did I already react?".
     *
     * @return HasMany<LegacyMomentReaction, $this>
     */
    public function viewerReactions(): HasMany
    {
        return $this->hasMany(LegacyMomentReaction::class);
    }

    /** @return HasMany<LegacyMomentComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(LegacyMomentComment::class);
    }
}
