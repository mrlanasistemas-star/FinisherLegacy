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
    'taxable', 'requires_shipping', 'qr_capable', 'tracks_inventory', 'active',
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
            'active' => 'boolean',
        ];
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
     * real ProductMedia always replaces it. The Legacy Plate (V3) gets its
     * full set of product renders, in the order the product page tells the
     * story: front, perspective, detail, clip, NFC, how it attaches.
     *
     * @return list<array{url: string, alt: string, srcset: string}>
     */
    public function conceptGallery(): array
    {
        if ($this->slug === 'legacy-plate') {
            return array_values(collect([
                ['front', [800, 1600], 'Frente de la Legacy Plate: nombre, fecha, tiempo, distancia y ritmo bajo resina, con panel negro FL y NFC integrado'],
                ['perspective', [600, 1000, 1600], 'Legacy Plate en perspectiva: grosor del cuerpo de Zamak niquelado y acabado en resina'],
                ['macro', [600, 900, 1400], 'Detalle del borde de Zamak niquelado y la profundidad del acabado en resina'],
                ['back', [800, 1600], 'Reverso de la Legacy Plate: metal limpio con clip de acero inoxidable tipo money clip'],
                ['nfc', [600, 900, 1400], 'Un teléfono acercándose al frente de la Legacy Plate para abrir el Legacy por NFC'],
                ['ribbon-held', [480, 800, 1200], 'Cómo se sujeta: la Legacy Plate instalada sobre el listón de la medalla'],
                ['ribbon-insert', [600, 900, 1400], 'El listón de la medalla entrando entre la placa y el clip'],
                ['clip-macro', [600, 900, 1400], 'Acercamiento al clip de acero inoxidable estampado'],
                ['profile', [800, 1600], 'Perfil lateral: cuerpo y clip, unos 6 mm de grosor total'],
                ['exploded', [800, 1200, 1800], 'Vista explotada: resina, inlay NFC, ferrita, cuerpo de Zamak niquelado y clip de acero inoxidable'],
            ])->map(fn (array $r) => [
                'url' => asset("media/brand/plate/legacy-plate-{$r[0]}-".max($r[1]).'.webp'),
                'srcset' => collect($r[1])->map(fn (int $w) => asset("media/brand/plate/legacy-plate-{$r[0]}-{$w}.webp")." {$w}w")->implode(', '),
                'alt' => $r[2],
            ])->all());
        }

        $url = $this->conceptImageUrl(1200);

        return $url === null ? [] : [[
            'url' => $url,
            'srcset' => collect([480, 800, 1200])->map(fn (int $w) => $this->conceptImageUrl($w)." {$w}w")->implode(', '),
            'alt' => (string) $this->conceptImageAlt(),
        ]];
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
