<?php

namespace App\Models;

use App\Enums\ProductMediaType;
use Database\Factories\ProductMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One gallery item of a product: image, video or 3D model (GLB/GLTF,
 * spec §22-§23). `is_primary` is the "principal" role; everything else is
 * gallery. Video and 3D can carry a `poster_path` (still shown before the
 * heavy file loads).
 */
#[Fillable([
    'uuid', 'product_id', 'product_variant_id', 'type', 'disk', 'path', 'mime', 'size',
    'sort_order', 'is_primary', 'is_hover', 'alt_text', 'poster_path',
])]
class ProductMedia extends Model
{
    /** @use HasFactory<ProductMediaFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductMedia $media) {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ProductMediaType::class,
            'is_primary' => 'boolean',
            'is_hover' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path !== null ? Storage::disk($this->disk)->url($this->poster_path) : null;
    }

    /**
     * The one serialized shape of a gallery item, shared by the store
     * pages, the admin and `/api/v1` (spec §23).
     *
     * @return array{uuid: string|null, type: string, url: string, poster_url: string|null, alt: string|null, is_primary: bool, position: int}
     */
    public function toMediaArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'url' => $this->url(),
            'poster_url' => $this->posterUrl(),
            'alt' => $this->alt_text,
            'is_primary' => (bool) $this->is_primary,
            'position' => (int) $this->sort_order,
        ];
    }
}
