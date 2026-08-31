<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'active'])]
class InventoryLocation extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<InventoryLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }
}
