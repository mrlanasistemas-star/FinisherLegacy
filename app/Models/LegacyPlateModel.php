<?php

namespace App\Models;

use Database\Factories\LegacyPlateModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One of the (at most three) Legacy Plate layouts. A plate is PRINTED on
 * its front and back — no laser — and carries an NFC chip programmed with
 * its Legacy Code (no printed QR). `engraving_area` is the printable area
 * of the front, `back_area` the one of the back; artwork + colors per
 * face define the print. See docs/architecture/legacy-plate-v2.md.
 */
#[Fillable([
    'uuid', 'name', 'slug', 'layout_slot', 'sku', 'description', 'width_mm', 'height_mm',
    'engraving_area', 'back_area', 'active', 'preview_image_path', 'metadata',
    'front_artwork_path', 'back_artwork_path', 'front_background', 'back_background',
    'front_text_color', 'back_text_color',
])]
class LegacyPlateModel extends Model
{
    /** The product offers exactly three layouts — never more. */
    public const int MAX_LAYOUTS = 3;

    /** @use HasFactory<LegacyPlateModelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'engraving_area' => 'array',
            'back_area' => 'array',
            'layout_slot' => 'integer',
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
            'back_area' => $this->back_area ?? $this->engraving_area,
            'preview_image_url' => $this->preview_image_path ? Storage::disk('public')->url($this->preview_image_path) : null,
            'front_artwork_url' => $this->front_artwork_path ? Storage::disk('public')->url($this->front_artwork_path) : null,
            'back_artwork_url' => $this->back_artwork_path ? Storage::disk('public')->url($this->back_artwork_path) : null,
            'front_background' => $this->front_background ?? '#F3F2EE',
            'back_background' => $this->back_background ?? '#171714',
            'front_text_color' => $this->front_text_color ?? '#171714',
            'back_text_color' => $this->back_text_color ?? '#F4EEDF',
            'fields' => $this->fields->map(fn (LegacyPlateModelField $field) => [
                'field_key' => $field->field_key->value,
                'face' => $field->face ?? 'front',
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
