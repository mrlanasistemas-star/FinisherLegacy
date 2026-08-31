<?php

namespace App\Models;

use Database\Factories\LegacyPlateModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pre-manufactured Legacy Plate reference (brief §3-§5): shape,
 * mechanism, relief and decoration already fixed by the supplier —
 * `engraving_area` is the only region Finisher Legacy ever writes into. See
 * docs/architecture/legacy-plate-v2.md.
 */
#[Fillable([
    'uuid', 'name', 'slug', 'sku', 'description', 'width_mm', 'height_mm',
    'engraving_area', 'active', 'preview_image_path', 'metadata',
])]
class LegacyPlateModel extends Model
{
    /** @use HasFactory<LegacyPlateModelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'engraving_area' => 'array',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<LegacyPlateModelField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(LegacyPlateModelField::class);
    }

    /** @return HasMany<Plate, $this> */
    public function plates(): HasMany
    {
        return $this->hasMany(Plate::class);
    }

    /** @return HasMany<LegacyPlateEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(LegacyPlateEntitlement::class);
    }
}
