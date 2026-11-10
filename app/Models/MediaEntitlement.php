<?php

namespace App\Models;

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
 * confirmed the payment.
 *
 * @property int $extra_images
 * @property int $extra_videos
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'uuid', 'user_id', 'event_participant_id', 'legacy_story_id', 'photo_expansion_pack_id', 'product_price_schedule_id',
    'order_item_id', 'extra_images', 'extra_videos', 'source', 'revoked_at',
])]
class MediaEntitlement extends Model
{
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
