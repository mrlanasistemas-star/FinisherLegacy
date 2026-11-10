<?php

namespace App\Services\Media;

use App\Enums\BillingPeriod;
use App\Enums\ProductType;
use App\Enums\StoryQuotaKind;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoQuotaRule;
use App\Models\Product;
use App\Support\Quotas\ResolvedExpansionPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How much a Legacy story may hold of each media kind (photos, videos,
 * voice messages) — the single source for web, API, admin and the media
 * pipeline (docs/renovacion/cuotas-y-precios.md):
 *
 *   limit = min( base + extra , hard cap? )
 *   base  = PhotoQuotaRule of the story's product
 *           → rule of that product's type → `global` rule
 *           → config('legacy.stories.*')          (each value on its own)
 *   extra = entitlements IN FORCE: paid expansions and admin grants of the
 *           story, plus admin grants made to its owner (all their stories).
 *           Revoked or expired (prepaid term over) entitlements add nothing.
 *
 * Purely a gate for NEW content: lowering a rule, revoking a grant or a
 * term running out never deletes anything — a story above its limit keeps
 * showing everything and simply cannot add more. Callers that add content
 * must hold a lock on the story row (ManageStoryMedia does) for
 * assertCanAdd to be race-free.
 *
 * Price steps ("banderas"): the price of a pack depends on how much the
 * story already holds of that kind — `held` = max(current limit, items
 * stored) — resolved here, server-side, at purchase time.
 */
final class StoryMediaQuota
{
    /** Sources whose entitlements add story capacity. */
    public const array CAPACITY_SOURCES = [PhotoExpansionPack::ENTITLEMENT_SOURCE, MediaEntitlement::SOURCE_MANUAL_GRANT];

    /** A term expiring within this many days is offered for renewal (and a renewal extends it). */
    public const int RENEWAL_WINDOW_DAYS = 30;

    public function limitFor(LegacyStory $story, StoryQuotaKind $kind): int
    {
        $limit = $this->baseFor($story, $kind) + $this->extraFor($story, $kind);
        $cap = $this->capFor($story, $kind);

        return $cap !== null ? min($limit, $cap) : $limit;
    }

    public function usedFor(LegacyStory $story, StoryQuotaKind $kind): int
    {
        return DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->where('role', $kind->role()->value)
            ->count();
    }

    /**
     * @throws PhotoQuotaExceededException
     */
    public function assertCanAdd(LegacyStory $story, StoryQuotaKind $kind, int $count = 1): void
    {
        $limit = $this->limitFor($story, $kind);
        $used = $this->usedFor($story, $kind);

        if ($used + max(1, $count) > $limit) {
            throw new PhotoQuotaExceededException($limit, $used, $this->legacyOffer($story, $kind), kind: $kind, plan: $this->planFor($story, $kind));
        }
    }

    /** Included without any purchase. */
    public function baseFor(LegacyStory $story, StoryQuotaKind $kind): int
    {
        return $this->ruleValue($story, $kind->includedColumn()) ?? $kind->configDefault();
    }

    /** Optional hard ceiling per story (null = none). */
    public function capFor(LegacyStory $story, StoryQuotaKind $kind): ?int
    {
        return $this->ruleValue($story, $kind->capColumn());
    }

    /** Max seconds of ONE video / voice message (null for photos). */
    public function maxSecondsFor(LegacyStory $story, StoryQuotaKind $kind): ?int
    {
        $column = $kind->maxSecondsColumn();

        return $column === null ? null : ($this->ruleValue($story, $column) ?? $kind->configMaxSeconds());
    }

    /** Capacity added by entitlements in force (paid + granted). */
    public function extraFor(LegacyStory $story, StoryQuotaKind $kind): int
    {
        $ownerId = $this->ownerUserId($story);

        return (int) MediaEntitlement::query()
            ->whereIn('source', self::CAPACITY_SOURCES)
            ->where(function (Builder $q) use ($story, $ownerId) {
                $q->where('legacy_story_id', $story->id);

                if ($ownerId !== null) {
                    // Account-wide admin grant: every story of that user.
                    $q->orWhere(fn (Builder $wide) => $wide
                        ->whereNull('legacy_story_id')
                        ->whereNull('event_participant_id')
                        ->where('source', MediaEntitlement::SOURCE_MANUAL_GRANT)
                        ->where('user_id', $ownerId));
                }
            })
            ->inForce()
            ->sum($kind->entitlementColumn());
    }

    /**
     * Sellable packs of a kind whose scope covers this story, most
     * specific first (its product, its product type, global).
     *
     * @return Collection<int, PhotoExpansionPack>
     */
    public function applicablePacks(LegacyStory $story, StoryQuotaKind $kind): Collection
    {
        $product = $this->productFor($story);
        $type = $product?->type instanceof ProductType ? $product->type->value : null;

        return PhotoExpansionPack::query()
            ->sellable()
            ->where('kind', $kind->value)
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
     * Whether the pack's scope covers this story, regardless of whether it
     * is still on sale — used to honour a store purchase that was paid
     * before the pack was paused.
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
     * Every pack of the kind this story can buy right now (priced, and
     * fitting under the hard cap), most specific scope first.
     *
     * @return list<array{pack: PhotoExpansionPack, price: ResolvedExpansionPrice}>
     */
    public function purchasable(LegacyStory $story, StoryQuotaKind $kind): array
    {
        $out = [];

        foreach ($this->applicablePacks($story, $kind) as $pack) {
            $price = $this->resolvePrice($pack, $story);

            if ($price !== null && $this->fitsCap($story, $pack)) {
                $out[] = ['pack' => $pack, 'price' => $price];
            }
        }

        return $out;
    }

    /** The first purchasable pack (what "Ampliar" offers by default). */
    public function expansionPackFor(LegacyStory $story, StoryQuotaKind $kind): ?PhotoExpansionPack
    {
        return $this->purchasable($story, $kind)[0]['pack'] ?? null;
    }

    /** False when buying the pack would push the story past its hard cap. */
    public function fitsCap(LegacyStory $story, PhotoExpansionPack $pack): bool
    {
        $cap = $this->capFor($story, $pack->kind);

        return $cap === null
            || $this->baseFor($story, $pack->kind) + $this->extraFor($story, $pack->kind) + $pack->units() <= $cap;
    }

    /** What the story already holds of a kind, for price steps. */
    public function heldFor(LegacyStory $story, StoryQuotaKind $kind): int
    {
        return max($this->limitFor($story, $kind), $this->usedFor($story, $kind));
    }

    /**
     * The price of a pack for THIS story right now: the schedule row in
     * force and, when it has steps, the one matching what the story
     * already holds. Null when the pack has no price in force.
     */
    public function resolvePrice(PhotoExpansionPack $pack, LegacyStory $story, ?\DateTimeInterface $at = null): ?ResolvedExpansionPrice
    {
        $schedule = $pack->priceAt($at);

        if ($schedule === null) {
            return null;
        }

        return ResolvedExpansionPrice::for($schedule, $this->heldFor($story, $pack->kind));
    }

    /**
     * The default offer of a kind, ready for a client.
     *
     * @return null|array<string, mixed>
     */
    public function offer(LegacyStory $story, StoryQuotaKind $kind): ?array
    {
        $first = $this->purchasable($story, $kind)[0] ?? null;

        return $first === null ? null : $this->offerPayload($first['pack'], $first['price']);
    }

    /**
     * Every purchasable pack of a kind, ready for a client.
     *
     * @return list<array<string, mixed>>
     */
    public function offers(LegacyStory $story, StoryQuotaKind $kind): array
    {
        return array_map(fn (array $row) => $this->offerPayload($row['pack'], $row['price']), $this->purchasable($story, $kind));
    }

    /**
     * @return array<string, mixed>
     */
    public function offerPayload(PhotoExpansionPack $pack, ResolvedExpansionPrice $price): array
    {
        $amount = StoryPhotoQuota::formatMinor($price->amountMinor);

        return [
            'id' => $pack->id,
            'kind' => $pack->kind->value,
            'name' => tr($pack, 'name'),
            'description' => tr($pack, 'description'),
            'units' => $pack->units(),
            'price' => $amount,
            'currency' => $price->schedule->currency,
            'billing_period' => $pack->billing_period->value,
            'billing_label' => $pack->billing_period->label(),
            'months' => $pack->billing_period->months(),
            // "$149.00 MXN por 12 meses" / "$49.00 MXN · pago único"
            'price_label' => $pack->billing_period->priceLabel('$'.$amount.' '.$price->schedule->currency),
            'step' => $price->stepPayload(),
        ];
    }

    /**
     * The prepaid term of a kind on this story, if it ever had one: the
     * term that ends first among those running (the one to renew), else
     * the last one that ran out. `expired` means no term is running any
     * more — its capacity stopped counting, nothing was deleted.
     *
     * @return null|array{billing_period: string, expires_at: ?string, expired: bool, renewable: bool, expires_in_days: ?int, units: int, pack_id: ?int, pack_name: ?string, renew_price_label: ?string}
     */
    public function planFor(LegacyStory $story, StoryQuotaKind $kind): ?array
    {
        $column = $kind->entitlementColumn();
        $now = now();

        $terms = MediaEntitlement::query()
            ->where('legacy_story_id', $story->id)
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->where('billing_period', '!=', BillingPeriod::OneTime->value)
            ->whereNotNull('expires_at')
            ->whereNull('revoked_at')
            ->where($column, '>', 0)
            ->with('photoExpansionPack')
            ->orderBy('expires_at')
            ->get();

        if ($terms->isEmpty()) {
            return null;
        }

        // A term already followed by its renewal is not the one to show.
        $open = $terms->reject(fn (MediaEntitlement $term) => $terms->contains(
            fn (MediaEntitlement $next) => $next->id !== $term->id
                && $next->photo_expansion_pack_id === $term->getAttribute('photo_expansion_pack_id')
                && $next->starts_at !== null && $term->expires_at !== null
                && $next->starts_at->equalTo($term->expires_at),
        ));

        $running = $open->filter(fn (MediaEntitlement $term) => ! $term->isExpired($now));
        /** @var MediaEntitlement $current */
        $current = $running->first() ?? $open->last() ?? $terms->last();

        $expired = $running->isEmpty() && $terms->every(fn (MediaEntitlement $term) => $term->isExpired($now));
        $pack = $current->photoExpansionPack;
        $price = $pack !== null && $pack->active && ! $pack->isArchived() && $this->packCovers($story, $pack)
            ? $this->resolvePrice($pack, $story)
            : null;
        $days = $current->expires_at !== null && ! $current->isExpired($now)
            ? (int) ceil($now->diffInSeconds($current->expires_at) / 86400)
            : null;

        return [
            'billing_period' => $current->billing_period->value,
            'expires_at' => $current->expires_at?->toIso8601String(),
            'expired' => $expired,
            'renewable' => $price !== null && $pack !== null && ($expired || ($days !== null && $days <= self::RENEWAL_WINDOW_DAYS)),
            'expires_in_days' => $days,
            'units' => (int) $current->getAttribute($column),
            'pack_id' => $pack?->id,
            'pack_name' => $pack?->name,
            'renew_price_label' => $price !== null && $pack !== null ? $this->offerPayload($pack, $price)['price_label'] : null,
        ];
    }

    /**
     * @return array{limit:int, used:int, remaining:int, can_expand:bool, max_seconds:?int, cap:?int, expansion:?array<string, mixed>, expansions:list<array<string, mixed>>, plan:?array<string, mixed>}
     */
    public function summary(LegacyStory $story, StoryQuotaKind $kind): array
    {
        $limit = $this->limitFor($story, $kind);
        $used = $this->usedFor($story, $kind);
        $offers = $this->offers($story, $kind);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'can_expand' => $offers !== [],
            'max_seconds' => $this->maxSecondsFor($story, $kind),
            'cap' => $this->capFor($story, $kind),
            'expansion' => $offers[0] ?? null,
            'expansions' => $offers,
            'plan' => $this->planFor($story, $kind),
        ];
    }

    /**
     * Every kind at once — the `quota` block of the editor and the API.
     *
     * @return array<string, array<string, mixed>>
     */
    public function summaryAll(LegacyStory $story): array
    {
        $all = [];

        foreach (StoryQuotaKind::cases() as $kind) {
            $all[$kind->value] = $this->summary($story, $kind);
        }

        return $all;
    }

    /**
     * The pre-kinds offer shape (`photos` = units) kept for
     * StoryPhotoQuota and the MEDIA_LIMIT_REACHED details.
     *
     * @return null|array{id:int,name:string,photos:int,price:string,currency:string}
     */
    public function legacyOffer(LegacyStory $story, StoryQuotaKind $kind): ?array
    {
        $offer = $this->offer($story, $kind);

        return $offer === null ? null : [
            'id' => (int) $offer['id'],
            'name' => (string) $offer['name'],
            'photos' => (int) $offer['units'],
            'price' => (string) $offer['price'],
            'currency' => (string) $offer['currency'],
        ];
    }

    public function productFor(LegacyStory $story): ?Product
    {
        if ($story->legacy_tag_id === null) {
            return null;
        }

        $story->loadMissing('tag.productVariant.product');

        return $story->tag?->productVariant?->product;
    }

    private function ownerUserId(LegacyStory $story): ?int
    {
        $story->loadMissing('athlete');
        $id = $story->athlete?->user_id;

        return $id !== null ? (int) $id : null;
    }

    /** First non-null value of a rule column: product → type → global. */
    private function ruleValue(LegacyStory $story, string $column): ?int
    {
        $product = $this->productFor($story);
        $keys = array_values(array_filter([
            $product !== null ? PhotoQuotaRule::productKey($product->id) : null,
            $product?->type instanceof ProductType ? PhotoQuotaRule::typeKey($product->type) : null,
            PhotoQuotaRule::GLOBAL_KEY,
        ]));

        $rules = PhotoQuotaRule::query()->whereIn('scope_key', $keys)->get()->keyBy('scope_key');

        foreach ($keys as $key) {
            $value = $rules->get($key)?->getAttribute($column);

            if ($value !== null) {
                return (int) $value;
            }
        }

        return null;
    }
}
