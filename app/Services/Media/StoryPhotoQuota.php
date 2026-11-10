<?php

namespace App\Services\Media;

use App\Enums\ProductType;
use App\Enums\StoryMediaRole;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoQuotaRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How many photos a Legacy story may hold — the single source for web,
 * API and admin (docs/renovacion/cuotas-y-precios.md):
 *
 *   limit = base included + every active (payment-confirmed) expansion
 *   base  = PhotoQuotaRule for the story's product
 *           → PhotoQuotaRule for that product's type
 *           → config('legacy.stories.max_photos')
 *
 * Purely a gate for NEW photos: lowering a rule or revoking an expansion
 * never deletes anything — a story above its limit keeps showing every
 * photo and simply cannot add more. Callers that add photos must hold a
 * lock on the story row (ManageStoryMedia does) for assertCanAdd to be
 * race-free.
 */
final class StoryPhotoQuota
{
    public function limitFor(LegacyStory $story): int
    {
        return $this->baseFor($story) + $this->purchasedFor($story);
    }

    public function usedFor(LegacyStory $story): int
    {
        return DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->where('role', StoryMediaRole::Photo->value)
            ->count();
    }

    /**
     * @throws PhotoQuotaExceededException
     */
    public function assertCanAdd(LegacyStory $story, int $count = 1): void
    {
        $limit = $this->limitFor($story);
        $used = $this->usedFor($story);

        if ($used + max(1, $count) > $limit) {
            throw new PhotoQuotaExceededException($limit, $used, $this->expansionOffer($story));
        }
    }

    /**
     * @return array{limit:int, used:int, remaining:int, can_expand:bool, expansion: null|array{id:int,name:string,photos:int,price:string,currency:string}}
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
        ];
    }

    /** Photos included without any purchase. */
    public function baseFor(LegacyStory $story): int
    {
        $product = $this->productFor($story);

        $rules = PhotoQuotaRule::query()
            ->whereIn('scope_key', array_values(array_filter([
                $product !== null ? PhotoQuotaRule::productKey($product->id) : null,
                $product?->type instanceof ProductType ? PhotoQuotaRule::typeKey($product->type) : null,
            ])))
            ->get()
            ->keyBy('scope_key');

        $rule = ($product !== null ? $rules->get(PhotoQuotaRule::productKey($product->id)) : null)
            ?? ($product?->type instanceof ProductType ? $rules->get(PhotoQuotaRule::typeKey($product->type)) : null);

        return $rule !== null ? $rule->included_photos : (int) config('legacy.stories.max_photos', 12);
    }

    /** Photos added by confirmed, non-revoked expansions. */
    public function purchasedFor(LegacyStory $story): int
    {
        return (int) MediaEntitlement::query()
            ->where('legacy_story_id', $story->id)
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->active()
            ->sum('extra_images');
    }

    /**
     * The expansion this story can buy right now — most specific scope
     * first (its product, then its product type, then global), only packs
     * with a price in force. Null when nothing is on sale.
     */
    public function expansionPackFor(LegacyStory $story): ?PhotoExpansionPack
    {
        foreach ($this->applicablePacks($story) as $pack) {
            if ($pack->priceAt() !== null) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * Sellable packs whose scope covers this story, most specific first.
     *
     * @return Collection<int, PhotoExpansionPack>
     */
    public function applicablePacks(LegacyStory $story): Collection
    {
        $product = $this->productFor($story);
        $type = $product?->type instanceof ProductType ? $product->type->value : null;

        return PhotoExpansionPack::query()
            ->sellable()
            ->where(function (Builder $q) use ($product, $type) {
                $q->where(fn (Builder $global) => $global->whereNull('product_id')->whereNull('product_type'));
                if ($product !== null) {
                    $q->orWhere('product_id', $product->id);
                }
                if ($type !== null) {
                    $q->orWhere(fn (Builder $byType) => $byType->whereNull('product_id')->where('product_type', $type));
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (PhotoExpansionPack $pack) => $pack->product_id !== null ? 0 : ($pack->product_type !== null ? 1 : 2))
            ->values();
    }

    /**
     * Whether the pack's scope (global / product / product type) covers
     * this story, regardless of whether it is still on sale — used to
     * honour a store purchase that was paid before the pack was paused.
     */
    public function packCovers(LegacyStory $story, PhotoExpansionPack $pack): bool
    {
        if ($pack->product_id === null && $pack->product_type === null) {
            return true;
        }

        $product = $this->productFor($story);

        if ($pack->product_id !== null) {
            return $product !== null && $product->id === $pack->product_id;
        }

        return $product?->type instanceof ProductType && $product->type === $pack->product_type;
    }

    /**
     * @return null|array{id:int,name:string,photos:int,price:string,currency:string}
     */
    public function expansionOffer(LegacyStory $story): ?array
    {
        $pack = $this->expansionPackFor($story);
        $price = $pack?->priceAt();

        if ($pack === null || $price === null) {
            return null;
        }

        return [
            'id' => $pack->id,
            'name' => $pack->name,
            'photos' => $pack->photos,
            'price' => self::formatMinor((int) $price->amount_minor),
            'currency' => $price->currency,
        ];
    }

    public static function formatMinor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    private function productFor(LegacyStory $story): ?Product
    {
        if ($story->legacy_tag_id === null) {
            return null;
        }

        $story->loadMissing('tag.productVariant.product');

        return $story->tag?->productVariant?->product;
    }
}
