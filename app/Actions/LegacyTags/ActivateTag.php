<?php

namespace App\Actions\LegacyTags;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\AthleteOwnedProductStatus;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Enums\StoryVisibility;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Models\AthleteOwnedProduct;
use App\Models\LegacyStory;
use App\Models\LegacyTag;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use App\Services\LegacyTags\TagLifecycle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TAG → resolve → check state → (login) → claim → Legacy story
 * (docs/architecture/commerce-os.md §Activación). Same action for NFC, the
 * typed code and the API — only `$source` differs, for analytics.
 *
 * Exactly one owner, even under concurrent requests: a Cache lock per tag
 * plus `lockForUpdate()` on the row inside the transaction; the loser sees
 * TAG_ALREADY_ACTIVATED (or gets the same story back if it was the owner —
 * activation is idempotent for its owner, so a double tap never fails).
 */
class ActivateTag
{
    public function __construct(
        private readonly EnsureAthleteForUser $ensureAthlete,
        private readonly TagLifecycle $lifecycle,
        private readonly InventoryService $inventory,
        private readonly RecordTagScan $scans,
    ) {}

    public function handle(LegacyTag $tag, User $user, TagAccessSource $source = TagAccessSource::Link): LegacyStory
    {
        return Cache::lock("legacy-tag-activate:{$tag->id}", 10)->block(5, function () use ($tag, $user, $source) {
            $story = DB::transaction(function () use ($tag, $user, $source) {
                /** @var LegacyTag $tag */
                $tag = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);
                $athlete = $this->ensureAthlete->handle($user, 'legacy_tag_activation');

                if ($tag->status === LegacyTagStatus::Activated) {
                    if ($tag->owner_athlete_id === $athlete->id) {
                        return $tag->story ?? $this->createStory($tag, $athlete->id);
                    }

                    throw new TagAlreadyActivatedException;
                }

                match ($tag->status) {
                    LegacyTagStatus::Disabled => throw new TagNotActivatableException('disabled'),
                    LegacyTagStatus::Replaced => throw new TagNotActivatableException('replaced'),
                    LegacyTagStatus::Returned => throw new TagNotActivatableException('returned'),
                    LegacyTagStatus::InStock => $this->inferFieldSale($tag, $user),
                    LegacyTagStatus::Sold, LegacyTagStatus::Delivered => null,
                    default => throw new TagNotActivatableException('not_sold'),
                };

                $tag->loadMissing('productVariant');

                $owned = AthleteOwnedProduct::create([
                    'uuid' => (string) Str::uuid(),
                    'athlete_id' => $athlete->id,
                    'order_item_id' => $tag->order_item_id,
                    'product_id' => $tag->productVariant->product_id,
                    'product_variant_id' => $tag->product_variant_id,
                    'serial_code' => $tag->serial,
                    'status' => AthleteOwnedProductStatus::Active,
                    'acquired_at' => $tag->sold_at ?? now(),
                    'activated_at' => now(),
                    'metadata' => ['source' => 'legacy_tag', 'legacy_tag_uuid' => $tag->uuid],
                ]);

                $this->lifecycle->transition($tag, LegacyTagEventType::Activated, LegacyTagStatus::Activated, [
                    'owner_athlete_id' => $athlete->id,
                    'athlete_owned_product_id' => $owned->id,
                    'activated_at' => now(),
                    'delivered_at' => $tag->delivered_at ?? now(),
                ], $user, null, ['source' => $source->value]);

                activity()->causedBy($user)->performedOn($tag)
                    ->withProperties(['serial' => $tag->serial, 'source' => $source->value])
                    ->log('tags.activated');

                return $this->createStory($tag, $athlete->id);
            });

            $this->scans->handle($tag->fresh(), TagScanEvent::ActivationCompleted, $source, $user, null, true);

            return $story;
        });
    }

    /**
     * Sold at an ally/event/stand but never registered: the activation is
     * the proof of sale. Recorded as a sale (flagged) so the partner can be
     * reconciled; warehouse / transit stock never activates.
     */
    private function inferFieldSale(LegacyTag $tag, User $user): void
    {
        $tag->loadMissing('location', 'productVariant.product');
        $location = $tag->location;

        if ($location === null || ! $location->isFieldLocation() || ! config('legacy.tags.activate_unreported_field_sales', true)) {
            throw new TagNotActivatableException('not_sold');
        }

        $this->inventory->sellDirect($tag->productVariant, $location, 1, LegacyTag::class, $tag->id, null, 'unreported_sale');

        $this->lifecycle->transition($tag, LegacyTagEventType::Sold, LegacyTagStatus::Delivered, [
            'sales_channel' => RecordInPersonSale::channelFor($location),
            'ally_id' => $location->ally_id,
            'sold_at' => now(),
            'delivered_at' => now(),
            'metadata' => [...($tag->metadata ?? []), 'unreported_sale' => true],
        ], $user, 'Venta inferida por activación (no registrada en punto de venta)', ['unreported_sale' => true]);
    }

    private function createStory(LegacyTag $tag, int $athleteId): LegacyStory
    {
        $tag->loadMissing('productVariant.product.collection');

        return LegacyStory::create([
            'uuid' => (string) Str::uuid(),
            'public_id' => self::newPublicId(),
            'athlete_id' => $athleteId,
            'legacy_tag_id' => $tag->id,
            'title' => $tag->productVariant->product->collection?->name,
            'visibility' => StoryVisibility::Unlisted,
        ]);
    }

    public static function newPublicId(): string
    {
        do {
            $id = Str::lower(Str::random(10));
        } while (LegacyStory::withTrashed()->where('public_id', $id)->exists());

        return $id;
    }
}
