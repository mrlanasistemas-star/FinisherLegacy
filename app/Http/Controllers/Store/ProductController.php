<?php

namespace App\Http\Controllers\Store;

use App\Enums\ProductAvailability;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductContentSection;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Queries\Commerce\GetFeaturedProducts;
use App\Queries\Store\StorefrontCatalog;
use App\Services\Commerce\PromotionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public storefront browsing (owner spec §17-§22) — store home, catalog
 * and category listings, product detail. Web Controller → query →
 * Inertia; Vue never calls the REST API. Never exposes exact stock
 * counts: availability is a boolean per variant and a coarse
 * ok/low/out level per product.
 */
class ProductController extends Controller
{
    public function index(Request $request, StorefrontCatalog $catalog): Response|RedirectResponse
    {
        // Old links used ?category=slug on the store home.
        if ($request->filled('category')) {
            $category = ProductCategory::query()->where('active', true)->where('slug', $request->string('category')->toString())->first();

            if ($category !== null) {
                return to_route('store.categories.show', $category, 301);
            }
        }

        $products = $catalog->query()->orderBy('sort_order')->orderBy('name')->limit(12)->get();

        // The Legacy Plate is the storefront's hero product when published.
        $plate = $products->firstWhere('type', ProductType::LegacyPlate)
            ?? $catalog->query()->where('type', ProductType::LegacyPlate->value)->first();

        return Inertia::render('store/Index', [
            'products' => $catalog->cards($products),
            'featured' => $plate === null ? null : [
                ...GetFeaturedProducts::summarize($plate),
                'description' => $plate->description,
                'gallery' => $plate->media->where('type', 'image')->sortByDesc('is_primary')->take(3)->values()
                    ->map(fn (ProductMedia $m) => ['url' => $m->url(), 'alt_text' => $m->alt_text]),
            ],
            'sections' => $catalog->sections(),
            'categories' => $catalog->categories(),
            'filters' => ['category' => ''],
        ]);
    }

    public function catalog(Request $request, StorefrontCatalog $catalog): Response
    {
        return $this->listing($request, $catalog, null);
    }

    public function category(Request $request, ProductCategory $category, StorefrontCatalog $catalog): Response
    {
        abort_unless($category->active, 404);

        return $this->listing($request, $catalog, $category);
    }

    public function show(Product $product, PromotionResolver $promotions): Response
    {
        abort_unless(
            $product->active && $product->status->value === 'active' && $product->type !== ProductType::DigitalPhoto,
            404,
        );

        $product->loadMissing(['category', 'variants.inventoryLevels', 'media', 'contentSections']);
        // Wire the inverse relation in memory so ProductVariant::isAvailable()
        // never lazy-loads `product` per variant (no N+1).
        $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        $related = Product::query()
            ->where('id', '!=', $product->id)
            ->where('active', true)
            ->where('status', 'active')
            ->where('type', '!=', ProductType::DigitalPhoto->value)
            ->when($product->category_id, fn ($q) => $q->orderByRaw('category_id = ? desc', [$product->category_id]))
            ->orderBy('sort_order')
            ->with(['category', 'variants.inventoryLevels', 'media'])
            ->limit(4)
            ->get();

        $merch = GetFeaturedProducts::merchandising($product);

        return Inertia::render('store/Show', [
            'product' => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'tagline' => $product->tagline,
                'type' => $product->type->value,
                'availability' => ($product->availability ?? ProductAvailability::Available)->value,
                'availability_label' => ($product->availability ?? ProductAvailability::Available)->label(),
                'is_purchasable' => $product->isPurchasable(),
                'brand' => $product->brand,
                'category' => $product->category?->name,
                'category_slug' => $product->category?->slug,
                // Kept for older clients; `is_nfc` is the storefront's word for it.
                'qr_capable' => $product->qr_capable,
                'is_nfc' => $merch['is_nfc'],
                'is_new' => $merch['is_new'],
                'stock_level' => $merch['stock_level'],
                'requires_shipping' => $product->requires_shipping,
                'image_url' => $product->primaryImageUrl(),
                'variants' => $product->variants->where('active', true)->values()->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'attributes' => $variant->attributes,
                    'base_price_minor' => $variant->base_price_minor,
                    'sale_price_minor' => ($promo = $promotions->bestFor($product, $variant->base_price_minor)) !== null
                        ? $variant->base_price_minor - $promo->discountFor($variant->base_price_minor)
                        : null,
                    'promotion_label' => $promo?->label(),
                    'currency' => $variant->currency,
                    'in_stock' => $variant->isAvailable(),
                    'low_stock' => $this->variantIsLow($variant),
                ]),
                'gallery' => $product->media->values()->map(fn (ProductMedia $m, int $i) => self::mediaItem($m, $i)),
                'concept_gallery' => $product->media->isEmpty() && ! $product->image_path ? $product->conceptGallery() : [],
                'contentSections' => $product->contentSections->map(fn (ProductContentSection $s) => [
                    'type' => $s->type->value,
                    'title' => $s->title,
                    'content' => $s->content,
                ]),
            ],
            'relatedProducts' => $related->map(fn (Product $p) => GetFeaturedProducts::summarize($p)),
        ]);
    }

    /**
     * One gallery item in the shared media contract
     * `{uuid, type, url, poster_url, alt, is_primary, position}` (types:
     * image | video | model_3d). Reads the raw type so the page keeps
     * rendering whatever new media kinds the admin learns to upload.
     *
     * @return array<string, mixed>
     */
    public static function mediaItem(ProductMedia $media, int $index): array
    {
        $alt = $media->alt_text;

        return [
            'uuid' => $media->getAttribute('uuid') ?? 'media-'.$media->id,
            'id' => $media->id,
            'type' => (string) $media->getRawOriginal('type'),
            'url' => $media->url(),
            'poster_url' => $media->posterUrl(),
            'alt' => $alt,
            'alt_text' => $alt,
            'is_primary' => (bool) $media->is_primary,
            'is_hover' => (bool) $media->is_hover,
            'position' => $media->sort_order ?? $index,
        ];
    }

    private function listing(Request $request, StorefrontCatalog $catalog, ?ProductCategory $category): Response
    {
        $filters = [
            'tipo' => $request->string('tipo')->toString() ?: null,
            'talla' => $request->string('talla')->toString() ?: null,
            'nfc' => $request->boolean('nfc'),
            'disponibles' => $request->boolean('disponibles'),
            'orden' => $request->string('orden')->toString() ?: 'destacados',
        ];

        return Inertia::render('store/Catalog', [
            'category' => $category === null ? null : [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'products' => $catalog->paginate([...$filters, 'category' => $category]),
            'categories' => $catalog->categories(),
            'sizes' => $catalog->sizeOptions($category),
            'types' => collect(ProductType::cases())
                ->reject(fn (ProductType $type) => $type === ProductType::DigitalPhoto)
                ->map(fn (ProductType $type) => ['value' => $type->value, 'label' => self::typeLabel($type)])
                ->values(),
            'filters' => $filters,
        ]);
    }

    private function variantIsLow(ProductVariant $variant): bool
    {
        if (! $variant->product->tracks_inventory || ! $variant->isAvailable()) {
            return false;
        }

        $available = $variant->inventoryLevels->sum(fn ($level) => max(0, $level->availableQuantity()));

        return $available <= GetFeaturedProducts::LOW_STOCK_UNITS;
    }

    public static function typeLabel(ProductType $type): string
    {
        return match ($type) {
            ProductType::LegacyTag => 'Finisher Legacy Tag',
            ProductType::LegacyPlate => 'Legacy Plate',
            ProductType::Apparel => 'Ropa',
            ProductType::Accessory => 'Accesorios',
            ProductType::Equipment => 'Equipo',
            ProductType::DigitalPhoto => 'Fotografía',
        };
    }
}
