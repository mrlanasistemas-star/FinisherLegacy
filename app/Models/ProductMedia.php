<?php

namespace App\Models;

use App\Enums\ProductMediaType;
use Database\Factories\ProductMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'product_id', 'product_variant_id', 'type', 'disk', 'path', 'mime', 'size',
    'sort_order', 'is_primary', 'alt_text', 'poster_path',
])]
class ProductMedia extends Model
{
    /** @use HasFactory<ProductMediaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ProductMediaType::class,
            'is_primary' => 'boolean',
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
}
