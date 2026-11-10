<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An extra Finisher Legacy Tag front for a collection (e.g. Cozumel's reef
 * and raccoon designs). The collection's own `tag_*` columns are its
 * primary design; a tag product may point at one of these instead.
 */
#[Fillable(['collection_id', 'slug', 'name', 'front_art_path', 'accent', 'sort_order'])]
class CollectionDesign extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Collection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
