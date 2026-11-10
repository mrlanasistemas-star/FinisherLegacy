<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\MediaEntitlement;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoQuotaRule;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use App\Services\Media\StoryPhotoQuota;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Límites y tarifas: base photos per product / product
 * type, photo expansion packs with their price timeline, and the latest
 * purchased rights. Everything here is configuration — no content is
 * ever deleted by changing it (docs/renovacion/cuotas-y-precios.md).
 */
class QuotaController extends Controller
{
    public function index(): Response
    {
        $rules = PhotoQuotaRule::query()->get()->keyBy('scope_key');
        $default = (int) config('legacy.stories.max_photos', 12);

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
            ->with(['product:id,name', 'prices' => fn ($q) => $q->orderByDesc('starts_at')->orderByDesc('id')])
            ->withCount(['entitlements as sold_count'])
            ->orderByRaw('archived_at is not null')
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
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->with(['legacyStory:id,public_id,title', 'user:id,name,email', 'photoExpansionPack:id,name', 'orderItem:id,order_id,unit_price_minor,currency', 'orderItem.order:id,uuid,order_number'])
            ->latest('id')
            ->limit(25)
            ->get();

        return Inertia::render('admin/quotas/Index', [
            'defaultLimit' => $default,
            'types' => $types->map(fn (ProductType $type) => [
                'value' => $type->value,
                'label' => self::typeLabel($type),
                'rule' => $this->rulePayload($rules->get(PhotoQuotaRule::typeKey($type))),
            ])->all(),
            'products' => $products->map(function (Product $product) use ($rules, $default) {
                $own = $rules->get(PhotoQuotaRule::productKey($product->id));
                $byType = $rules->get(PhotoQuotaRule::typeKey($product->type));

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'type' => $product->type->value,
                    'type_label' => self::typeLabel($product->type),
                    'active' => (bool) $product->active,
                    'rule' => $this->rulePayload($own),
                    'effective' => $own !== null ? $own->included_photos : ($byType !== null ? $byType->included_photos : $default),
                    'source' => $own !== null ? 'product' : ($byType !== null ? 'type' : 'default'),
                ];
            })->all(),
            'packs' => $packs->map(function (PhotoExpansionPack $pack) use ($now, $usedScheduleIds) {
                $current = $pack->priceAt($now);

                return [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'description' => $pack->description,
                    'photos' => $pack->photos,
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
            'entitlements' => $entitlements->map(fn (MediaEntitlement $e) => [
                'id' => $e->id,
                'story' => $e->legacyStory?->public_id,
                'story_title' => $e->legacyStory?->title,
                'user' => $e->user?->name,
                'email' => $e->user?->email,
                'pack' => $e->photoExpansionPack?->name,
                'photos' => $e->extra_images,
                'price' => $e->orderItem !== null ? StoryPhotoQuota::formatMinor((int) $e->orderItem->unit_price_minor) : null,
                'currency' => $e->orderItem?->currency,
                'order_number' => $e->orderItem?->order?->order_number,
                'granted_at' => $e->created_at?->toIso8601String(),
                'revoked' => $e->revoked_at !== null,
            ])->all(),
            'stats' => [
                'sellable_packs' => $packs->filter(fn (PhotoExpansionPack $p) => $p->active && ! $p->isArchived() && $p->priceAt($now) !== null)->count(),
                'rights_sold' => MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->active()->count(),
                'photos_sold' => (int) MediaEntitlement::query()->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)->active()->sum('extra_images'),
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

    /** @return array{id:int, included_photos:int, notes:string|null}|null */
    private function rulePayload(?PhotoQuotaRule $rule): ?array
    {
        return $rule === null ? null : ['id' => $rule->id, 'included_photos' => $rule->included_photos, 'notes' => $rule->notes];
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
        ];
    }
}
