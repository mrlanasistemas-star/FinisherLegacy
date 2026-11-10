<?php

namespace App\Services\Media;

use App\Enums\StoryQuotaKind;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Models\LegacyStory;
use App\Models\PhotoExpansionPack;
use Illuminate\Database\Eloquent\Collection;

/**
 * The PHOTO view of a story's quota — the API this class had before
 * limits covered every media kind, kept working for its callers. All the
 * rules now live in StoryMediaQuota (per kind: photos, videos, audio);
 * this is a thin delegate fixed on StoryQuotaKind::Photos
 * (docs/renovacion/cuotas-y-precios.md).
 */
final class StoryPhotoQuota
{
    public function __construct(private readonly StoryMediaQuota $quota) {}

    public function limitFor(LegacyStory $story): int
    {
        return $this->quota->limitFor($story, StoryQuotaKind::Photos);
    }

    public function usedFor(LegacyStory $story): int
    {
        return $this->quota->usedFor($story, StoryQuotaKind::Photos);
    }

    /**
     * @throws PhotoQuotaExceededException
     */
    public function assertCanAdd(LegacyStory $story, int $count = 1): void
    {
        $this->quota->assertCanAdd($story, StoryQuotaKind::Photos, $count);
    }

    /**
     * @return array{limit:int, used:int, remaining:int, can_expand:bool, expansion: null|array{id:int,name:string,photos:int,price:string,currency:string}, plan: null|array<string, mixed>}
     */
    public function summary(LegacyStory $story): array
    {
        $limit = $this->limitFor($story);
        $used = $this->usedFor($story);
        $expansion = $this->expansionOffer($story);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'can_expand' => $expansion !== null,
            'expansion' => $expansion,
            'plan' => $this->quota->planFor($story, StoryQuotaKind::Photos),
        ];
    }

    /** Photos included without any purchase. */
    public function baseFor(LegacyStory $story): int
    {
        return $this->quota->baseFor($story, StoryQuotaKind::Photos);
    }

    /** Photos added by entitlements in force (paid expansions and admin grants). */
    public function purchasedFor(LegacyStory $story): int
    {
        return $this->quota->extraFor($story, StoryQuotaKind::Photos);
    }

    /** The photo expansion this story can buy right now, or null. */
    public function expansionPackFor(LegacyStory $story): ?PhotoExpansionPack
    {
        return $this->quota->expansionPackFor($story, StoryQuotaKind::Photos);
    }

    /**
     * Sellable photo packs whose scope covers this story, most specific first.
     *
     * @return Collection<int, PhotoExpansionPack>
     */
    public function applicablePacks(LegacyStory $story): Collection
    {
        return $this->quota->applicablePacks($story, StoryQuotaKind::Photos);
    }

    public function packCovers(LegacyStory $story, PhotoExpansionPack $pack): bool
    {
        return $this->quota->packCovers($story, $pack);
    }

    /**
     * @return null|array{id:int,name:string,photos:int,price:string,currency:string}
     */
    public function expansionOffer(LegacyStory $story): ?array
    {
        return $this->quota->legacyOffer($story, StoryQuotaKind::Photos);
    }

    public static function formatMinor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
