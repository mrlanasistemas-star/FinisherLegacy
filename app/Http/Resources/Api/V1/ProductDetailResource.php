<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductContentSection;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Wire the inverse relation in memory so
        // ProductVariant::isAvailable() never lazy-loads `product` per
        // variant (no N+1, consolidation brief §69).
        $this->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $this->resource));

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type->value,
            'brand' => $this->brand,
            'category' => $this->category?->name,
            'qr_capable' => $this->qr_capable,
            'requires_shipping' => $this->requires_shipping,
            'image_url' => $this->primaryImageUrl(),
            'gallery' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'type' => $m->type->value,
                'url' => $m->url(),
                'poster_url' => $m->posterUrl(),
                'alt_text' => $m->alt_text,
                'is_primary' => $m->is_primary,
            ])),
            'variants' => ProductVariantResource::collection($this->variants->where('active', true)->values()),
            // Additive (mobile store, spec §20-§23).
            'short_description' => $this->tagline,
            'nfc_enabled' => (bool) $this->qr_capable,
            'is_featured' => (bool) $this->is_featured,
            'is_new' => $this->created_at !== null && $this->created_at->gt(now()->subDays(30)),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn (ProductMedia $m) => $m->toMediaArray())->values()),
            'content_sections' => $this->whenLoaded('contentSections', fn () => $this->contentSections->map(fn (ProductContentSection $s) => [
                'type' => $s->type->value,
                'title' => $s->title,
                'content' => $s->content,
                'position' => (int) $s->sort_order,
            ])->values()),
        ];
    }
}
