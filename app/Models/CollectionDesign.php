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
 * An archived design (`archived_at`) is hidden from the store but keeps
 * its product references.
 */
#[Fillable(['collection_id', 'slug', 'name', 'front_art_path', 'accent', 'sort_order', 'archived_at'])]
class CollectionDesign extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
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
