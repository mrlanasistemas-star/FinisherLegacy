<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $image_path
 * @property int|null $width
 * @property int|null $height
 * @property string|null $title
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_visible
 */
#[Fillable(['image_path', 'width', 'height', 'title', 'description', 'sort_order', 'is_visible'])]
class CompanyGalleryItem extends Model
{
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @param  Builder<CompanyGalleryItem>  $query
     * @return Builder<CompanyGalleryItem>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
