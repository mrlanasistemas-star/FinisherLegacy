<?php

namespace App\Models;

use App\Enums\ProductContentSectionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'type', 'title', 'content', 'sort_order'])]
class ProductContentSection extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ProductContentSectionType::class,
            'content' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
