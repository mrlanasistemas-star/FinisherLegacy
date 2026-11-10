<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Enums\BillingPeriod;
use App\Enums\ProductType;
use App\Enums\StoryQuotaKind;
use App\Http\Controllers\Controller;
use App\Models\MediaEntitlement;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoExpansionPriceStep;
use App\Models\PhotoQuotaRule;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Services\Media\StoryMediaQuota;
use App\Services\Media\StoryPhotoQuota;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Límites y tarifas: what a story includes per media
 * kind (photos, videos, voice messages — count, max seconds, hard cap)
 * for every scope (global → product type → product), the expansion packs
 * of each kind with their billing period, price timeline and price steps
 * ("banderas"), and the capacity sold or granted by hand. Everything here
 * is configuration — no content is ever deleted by changing it
 * (docs/renovacion/cuotas-y-precios.md).
 */
class QuotaController extends Controller
{
    public function index(): Response
    {
        $rules = PhotoQuotaRule::query()->get()->keyBy('scope_key');
        $default = (int) config('legacy.stories.max_photos', 12);
        $global = $rules->get(PhotoQuotaRule::GLOBAL_KEY);

        /** @var array<string, int|null> $configDefaults */
        $configDefaults = [];
        foreach (StoryQuotaKind::cases() as $kind) {
            $configDefaults[$kind->includedColumn()] = $kind->configDefault();
            $configDefaults[$kind->capColumn()] = null;
            if ($kind->maxSecondsColumn() !== null) {
                $configDefaults[$kind->maxSecondsColumn()] = $kind->configMaxSeconds();
            }
        }

        $types = collect(ProductType::cases())
            ->reject(fn (ProductType $type) => $type === ProductType::DigitalPhoto)
            ->values();

        $products = Product::query()
            ->where('type', '!=', ProductType::DigitalPhoto->value)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'active']);

        $now = now();

        $packs = PhotoExpansionPack::query()
            ->with(['product:id,name', 'prices' => fn ($q) => $q->orderByDesc('starts_at')->orderByDesc('id'), 'prices.steps'])
            ->withCount(['entitlements as sold_count'])
            ->orderByRaw('archived_at is not null')
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $usedScheduleIds = array_values(MediaEntitlement::query()
            ->whereNotNull('product_price_schedule_id')
            ->distinct()
            ->pluck('product_price_schedule_id')
            ->map(fn ($id) => (int) $id)
            ->all());

        $entitlements = MediaEntitlement::query()
            ->whereIn('source', StoryMediaQuota::CAPACITY_SOURCES)
            ->with([
                'legacyStory:id,public_id,title', 'user:id,name,email', 'photoExpansionPack:id,name', 'grantedBy:id,name',
                'orderItem:id,order_id,unit_price_minor,currency,metadata', 'orderItem.order:id,uuid,order_number',
            ])
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('admin/quotas/Index', [
            'defaultLimit' => $default,
            // Last-resort values (config) and the editable global rule.
            'configDefaults' => $configDefaults,
            'globalRule' => $this->rulePayload($global),
            'limitColumns' => PhotoQuotaRule::LIMIT_COLUMNS,
            'kinds' => array_map(fn (StoryQuotaKind $kind) => [
                'value' => $kind->value,
                'label' => $kind->label(),
                'noun' => $kind->noun(),
                'included_column' => $kind->includedColumn(),
                'cap_column' => $kind->capColumn(),
                'max_seconds_column' => $kind->maxSecondsColumn(),
            ], StoryQuotaKind::cases()),
            'billingPeriods' => array_map(fn (BillingPeriod $period) => [
                'value' => $period->value,
                'label' => $period->label(),
                'months' => $period->months(),
            ], BillingPeriod::cases()),
            'types' => $types->map(fn (ProductType $type) => [
                'value' => $type->value,
                'label' => self::typeLabel($type),
                'rule' => $this->rulePayload($rules->get(PhotoQuotaRule::typeKey($type))),
                'effective_limits' => $this->effective([$rules->get(PhotoQuotaRule::typeKey($type)), $global], ['type', 'global'], $configDefaults),
            ])->all(),
            'products' => $products->map(function (Product $product) use ($rules, $default, $global, $configDefaults) {
                $own = $rules->get(PhotoQuotaRule::productKey($product->id));
                $byType = $rules->get(PhotoQuotaRule::typeKey($product->type));
                $limits = $this->effective([$own, $byType, $global], ['product', 'type', 'global'], $configDefaults);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'type' => $product->type->value,
                    'type_label' => self::typeLabel($product->type),
                    'active' => (bool) $product->active,
                    'rule' => $this->rulePayload($own),
                    // Photos only (kept for the first version of the page).
                    'effective' => $limits['included_photos']['value'] ?? $default,
                    'source' => $limits['included_photos']['source'],
                    'effective_limits' => $limits,
                ];
            })->all(),
            'packs' => $packs->map(function (PhotoExpansionPack $pack) use ($now, $usedScheduleIds) {
                $current = $pack->priceAt($now);

                return [
                    'id' => $pack->id,
                    'kind' => $pack->kind->value,
                    'kind_label' => $pack->kind->label(),
                    'billing_period' => $pack->billing_period->value,
                    'billing_label' => $pack->billing_period->label(),
                    'name' => $pack->name,
                    'description' => $pack->description,
                    'photos' => $pack->photos,
                    'units' => $pack->units(),
                    'unit_noun' => $pack->kind->noun($pack->units()),
                    'product_id' => $pack->product_id,
                    'product_name' => $pack->product?->name,
                    'product_type' => $pack->product_type?->value,
                    'product_type_label' => $pack->product_type instanceof ProductType ? self::typeLabel($pack->product_type) : null,
                    'active' => $pack->active,
                    'archived' => $pack->isArchived(),
                    'sort_order' => $pack->sort_order,
                    'apple_product_id' => $pack->apple_product_id,
                    'google_product_id' => $pack->google_product_id,
                    'sold_count' => (int) $pack->getAttribute('sold_count'),
                    'current_price' => $current !== null ? $this->pricePayload($current, $now, $usedScheduleIds) : null,
                    'prices' => $pack->prices->map(fn (ProductPriceSchedule $p) => $this->pricePayload($p, $now, $usedScheduleIds))->values()->all(),
                    'deletable' => $pack->prices->isEmpty() && (int) $pack->getAttribute('sold_count') === 0,
                ];
            })->all(),
            'entitlements' => $entitlements->map(function (MediaEntitlement $e) use ($now) {
                $kind = $e->extra_videos > 0 ? StoryQuotaKind::Videos : ($e->extra_audio > 0 ? StoryQuotaKind::Audio : StoryQuotaKind::Photos);
                $units = (int) $e->getAttribute($kind->entitlementColumn());
                $step = $e->orderItem !== null ? ($e->orderItem->metadata ?? []) : [];

                return [
                    'id' => $e->id,
                    'source' => $e->isManualGrant() ? 'manual' : 'purchase',
                    'story' => $e->legacyStory?->public_id,
                    'story_title' => $e->legacyStory?->title,
                    // A grant without a story counts on every story of the user.
                    'account_wide' => $e->legacy_story_id === null,
                    'user' => $e->user?->name,
                    'email' => $e->user?->email,
                    'pack' => $e->photoExpansionPack?->name,
                    'kind' => $kind->value,
                    'kind_label' => $kind->label(),
                    'units' => $units,
                    'unit_noun' => $kind->noun($units),
                    'photos' => $e->extra_images,
                    'billing_period' => $e->billing_period->value,
                    'billing_label' => $e->billing_period->label(),
                    'starts_at' => $e->starts_at?->toIso8601String(),
                    'expires_at' => $e->expires_at?->toIso8601String(),
                    'expired' => $e->isExpired($now),
                    'price' => $e->orderItem !== null ? StoryPhotoQuota::formatMinor((int) $e->orderItem->unit_price_minor) : null,
                    'currency' => $e->orderItem?->currency,
                    'step_from' => isset($step['price_step_id']) ? ($step['step_from_units'] ?? null) : null,
                    'held_units' => $step['held_units'] ?? null,
                    'order_number' => $e->orderItem?->order?->order_number,
                    'granted_at' => $e->created_at?->toIso8601String(),
                    'granted_by' => $e->grantedBy?->name,
                    'grant_reason' => $e->grant_reason,
                    'revoked' => $e->revoked_at !== null,
                    'revoked_at' => $e->revoked_at?->toIso8601String(),
                    'revocation_reason' => $e->revocation_reason,
                    'revocable' => $e->isManualGrant() && $e->revoked_at === null,
                ];
            })->all(),
            'stats' => [
                'sellable_packs' => $packs->filter(fn (PhotoExpansionPack $p) => $p->active && ! $p->isArchived() && $p->priceAt($now) !== null)->count(),
                'rights_sold' => MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->inForce()->count(),
                'photos_sold' => (int) MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->inForce()->sum('extra_images'),
                'videos_sold' => (int) MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->inForce()->sum('extra_videos'),
                'audio_sold' => (int) MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->inForce()->sum('extra_audio'),
                'manual_grants' => MediaEntitlement::query()->where('source', MediaEntitlement::SOURCE_MANUAL_GRANT)->inForce()->count(),
                'expiring_soon' => MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->inForce()
                    ->whereNotNull('expires_at')->where('expires_at', '<=', $now->copy()->addDays(StoryMediaQuota::RENEWAL_WINDOW_DAYS))->count(),
            ],
            'currencies' => array_values(array_unique([(string) config('finisher.photos.currency', 'MXN'), 'MXN', 'USD'])),
        ]);
    }

    public static function typeLabel(ProductType $type): string
    {
        return match ($type) {
            ProductType::LegacyTag => 'Finisher Legacy Tag',
            ProductType::LegacyPlate => 'Legacy Plate (histórico)',
            ProductType::Apparel => 'Ropa',
            ProductType::Accessory => 'Accesorio',
            ProductType::Equipment => 'Equipo',
            ProductType::DigitalPhoto => 'Digital interno',
        };
    }

    /**
     * Every limit column of a rule (null = inherits).
     *
     * @return array<string, mixed>|null
     */
    private function rulePayload(?PhotoQuotaRule $rule): ?array
    {
        if ($rule === null) {
            return null;
        }

        $payload = ['id' => $rule->id, 'notes' => $rule->notes];

        foreach (PhotoQuotaRule::LIMIT_COLUMNS as $column) {
            $value = $rule->getAttribute($column);
            $payload[$column] = $value !== null ? (int) $value : null;
        }

        return $payload;
    }

    /**
     * The value in force for each limit column and where it comes from
     * (`product` / `type` / `global` / `default` = config; cap columns
     * with no rule are `none`).
     *
     * @param  array<int, PhotoQuotaRule|null>  $chain  most specific first
     * @param  array<int, string>  $sources  same order as $chain
     * @param  array<string, int|null>  $configDefaults
     * @return array<string, array{value: int|null, source: string}>
     */
    private function effective(array $chain, array $sources, array $configDefaults): array
    {
        $out = [];

        foreach (PhotoQuotaRule::LIMIT_COLUMNS as $column) {
            $out[$column] = ['value' => $configDefaults[$column] ?? null, 'source' => ($configDefaults[$column] ?? null) !== null ? 'default' : 'none'];

            foreach ($chain as $index => $rule) {
                $value = $rule?->getAttribute($column);

                if ($value !== null) {
                    $out[$column] = ['value' => (int) $value, 'source' => $sources[$index]];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $usedScheduleIds
     * @return array<string, mixed>
     */
    private function pricePayload(ProductPriceSchedule $price, \DateTimeInterface $now, array $usedScheduleIds): array
    {
        $started = $price->starts_at === null || $price->starts_at <= $now;
        $ended = $price->ends_at !== null && $price->ends_at <= $now;

        return [
            'id' => $price->id,
            'amount' => StoryPhotoQuota::formatMinor((int) $price->amount_minor),
            'amount_minor' => (int) $price->amount_minor,
            'currency' => $price->currency,
            'tax_rate' => $price->tax_rate !== null ? round((float) $price->tax_rate * 100, 2) : null,
            'tax_included' => (bool) $price->tax_included,
            'starts_at' => $price->starts_at?->toIso8601String(),
            'ends_at' => $price->ends_at?->toIso8601String(),
            'active' => $price->active,
            'state' => ! $price->active ? 'cancelled' : ($ended ? 'ended' : ($started ? 'current' : 'scheduled')),
            'used' => in_array($price->id, $usedScheduleIds, true),
            // "Banderas": from N units already held, the pack costs `amount`.
            'steps' => $price->steps->map(fn (PhotoExpansionPriceStep $step) => [
                'id' => $step->id,
                'from_units' => $step->from_units,
                'amount' => StoryPhotoQuota::formatMinor((int) $step->amount_minor),
                'apple_product_id' => $step->apple_product_id,
                'google_product_id' => $step->google_product_id,
            ])->values()->all(),
        ];
    }
}
