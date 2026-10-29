<?php

namespace App\Models;

use App\Enums\ProductAvailability;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Finisher Legacy's ecosystem catalog (brief §45-§48): Legacy Plate is the
 * entry point, not the whole product — Trisuit, FAST T1 Socks, Chill Band
 * and Racepack are first-class products too.
 */
#[Fillable([
    'uuid', 'name', 'slug', 'description', 'tagline', 'type', 'category_id', 'brand', 'image_path', 'status',
    'availability', 'sort_order',
    'taxable', 'requires_shipping', 'qr_capable', 'tracks_inventory', 'active', 'minimum_stock', 'is_featured',
    'collection_id', 'tracks_units', 'material', 'strap_type', 'dimensions', 'instructions', 'seo_title', 'seo_description',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'availability' => ProductAvailability::class,
            'taxable' => 'boolean',
            'requires_shipping' => 'boolean',
            'qr_capable' => 'boolean',
            'tracks_inventory' => 'boolean',
            'tracks_units' => 'boolean',
            'active' => 'boolean',
            'is_featured' => 'boolean',
            'minimum_stock' => 'integer',
        ];
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductPriceSchedule, $this> */
    public function priceSchedules(): HasMany
    {
        return $this->hasMany(ProductPriceSchedule::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<ProductMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductContentSection, $this> */
    public function contentSections(): HasMany
    {
        return $this->hasMany(ProductContentSection::class)->orderBy('sort_order');
    }

    /**
     * The gallery's primary image wins over the legacy `image_path` when
     * both exist (brief §107/§28) — `image_path` is a fallback, not the
     * source of truth once a gallery is configured. Callers must eager
     * load `media` first; this never queries on its own (brief §60: no
     * N+1 from a card/cart row loop).
     */
    public function primaryImageUrl(): ?string
    {
        if ($this->relationLoaded('media')) {
            $primary = $this->media->where('type', 'image')->sortByDesc('is_primary')->first();

            if ($primary !== null) {
                return $primary->url();
            }
        }

        return $this->image_path ? Storage::disk('public')->url($this->image_path) : $this->conceptImageUrl();
    }

    /**
     * 'upload' when an admin-uploaded image is in use, 'concept' when the
     * store falls back to the conceptual render, null when neither exists.
     */
    public function imageSource(): ?string
    {
        $hasUpload = ($this->relationLoaded('media') && $this->media->where('type', 'image')->isNotEmpty())
            || $this->image_path;

        return $hasUpload ? 'upload' : ($this->conceptImageUrl() !== null ? 'concept' : null);
    }

    /** Conceptual render for this slug (config finisher.product_concepts), if any. */
    public function conceptImageUrl(int $width = 800): ?string
    {
        $concept = config("finisher.product_concepts.{$this->slug}");

        return $concept === null ? null : asset("media/products/concepts/{$concept['key']}-{$width}.webp");
    }

    /**
     * Conceptual gallery shown while no real photo has been uploaded — a
     * real ProductMedia always replaces it. The hero render comes first,
     * then any extra studio angles listed under the concept's 'angles'
     * (files {key}-{angle}-{w}.webp), in config order.
     *
     * @return list<array{url: string, alt: string, srcset: string}>
     */
    public function conceptGallery(): array
    {
        $concept = config("finisher.product_concepts.{$this->slug}");

        if ($concept === null) {
            return [];
        }

        $item = fn (string $name, string $alt): array => [
            'url' => asset("media/products/concepts/{$name}-1200.webp"),
            'srcset' => collect([480, 800, 1200])->map(fn (int $w) => asset("media/products/concepts/{$name}-{$w}.webp")." {$w}w")->implode(', '),
            'alt' => $alt,
        ];

        $gallery = [$item($concept['key'], (string) $concept['alt'])];

        foreach ($concept['angles'] ?? [] as $angle => $alt) {
            $gallery[] = $item("{$concept['key']}-{$angle}", (string) $alt);
        }

        return $gallery;
    }

    public function conceptImageAlt(): ?string
    {
        return config("finisher.product_concepts.{$this->slug}.alt");
    }

    /**
     * Published AND commercially available — "Próximamente"/"Concepto"
     * products are catalog-only (see ProductAvailability).
     */
    public function isPurchasable(): bool
    {
        return $this->active
            && $this->status === ProductStatus::Active
            && ($this->availability ?? ProductAvailability::Available)->isPurchasable();
    }
}
