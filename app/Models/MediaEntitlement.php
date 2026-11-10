<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Extra event media for one participation (Memory Pack foundation) — see
 * App\Services\Media\ResolveMediaEntitlement — or, with legacy_story_id and
 * source `photo_expansion`, extra photo capacity bought for a Legacy story
 * (App\Services\Media\StoryPhotoQuota). Story rows are only created by
 * App\Actions\Quotas\GrantPhotoExpansionEntitlements once the backend
 * confirmed the payment (extra_images / extra_videos / extra_audio by the
 * pack's kind) or, with source `manual_grant`, by an admin
 * (App\Actions\Quotas\GrantStoryCapacity — one story when
 * legacy_story_id is set, every story of user_id when it is null).
 *
 * A term entitlement (billing_period other than one_time) only counts
 * between starts_at and expires_at; both and the period are frozen at
 * payment time.
 *
 * @property int $extra_images
 * @property int $extra_videos
 * @property int $extra_audio
 * @property BillingPeriod $billing_period
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property array<int, string>|null $expiry_reminders
 * @property Carbon|null $revoked_at
 * @property string|null $grant_reason
 * @property string|null $revocation_reason
 */
#[Fillable([
    'uuid', 'user_id', 'event_participant_id', 'legacy_story_id', 'photo_expansion_pack_id', 'product_price_schedule_id',
    'order_item_id', 'extra_images', 'extra_videos', 'extra_audio', 'source', 'billing_period', 'starts_at', 'expires_at',
    'expiry_reminders', 'granted_by', 'grant_reason', 'revoked_at', 'revoked_by', 'revocation_reason',
])]
class MediaEntitlement extends Model
{
    /** Capacity an admin granted by hand ("liberarle más"). */
    public const string SOURCE_MANUAL_GRANT = 'manual_grant';

    /** @var array<string, mixed> */
    protected $attributes = ['billing_period' => 'one_time'];

    protected static function booted(): void
    {
        static::creating(function (MediaEntitlement $entitlement) {
            $entitlement->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'extra_images' => 'integer',
            'extra_videos' => 'integer',
            'extra_audio' => 'integer',
            'billing_period' => BillingPeriod::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_reminders' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<MediaEntitlement>  $query
     * @return Builder<MediaEntitlement>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Not revoked AND inside its term right now — the only rows that add
     * capacity. One-time rows have no dates and always pass.
     *
     * @param  Builder<MediaEntitlement>  $query
     * @return Builder<MediaEntitlement>
     */
    public function scopeInForce(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at ??= now();

        return $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $at));
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        return $this->expires_at !== null && $this->expires_at <= ($at ?? now());
    }

    public function isManualGrant(): bool
    {
        return $this->source === self::SOURCE_MANUAL_GRANT;
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    /** @return BelongsTo<LegacyStory, $this> */
    public function legacyStory(): BelongsTo
    {
        return $this->belongsTo(LegacyStory::class);
    }

    /** @return BelongsTo<PhotoExpansionPack, $this> */
    public function photoExpansionPack(): BelongsTo
    {
        return $this->belongsTo(PhotoExpansionPack::class);
    }

    /** @return BelongsTo<ProductPriceSchedule, $this> */
    public function priceSchedule(): BelongsTo
    {
        return $this->belongsTo(ProductPriceSchedule::class, 'product_price_schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
