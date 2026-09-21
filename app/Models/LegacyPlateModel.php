<?php

namespace App\Models;

use Database\Factories\LegacyPlateModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    /**
     * The exact shape LegacyPlateViewer.vue's `LegacyPlateModelData` expects
     * — kept on the model so every consumer (Mi Legado, admin plate detail)
     * builds it the same way. Assumes `fields` is already eager-loaded.
     *
     * @return array<string, mixed>
     */
    public function toViewerArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'width_mm' => (float) $this->width_mm,
            'height_mm' => (float) $this->height_mm,
            'engraving_area' => $this->engraving_area,
            'preview_image_url' => $this->preview_image_path ? Storage::disk('public')->url($this->preview_image_path) : null,
            'fields' => $this->fields->map(fn (LegacyPlateModelField $field) => [
                'field_key' => $field->field_key->value,
                'x' => (float) $field->x,
                'y' => (float) $field->y,
                'width' => (float) $field->width,
                'height' => (float) $field->height,
                'font_size' => $field->font_size !== null ? (float) $field->font_size : null,
                'alignment' => $field->alignment->value,
                'visible' => $field->visible,
            ])->values(),
        ];
    }
}
