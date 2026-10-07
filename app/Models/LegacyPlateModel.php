<?php

namespace App\Models;

use Database\Factories\LegacyPlateModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One of the (at most three) Legacy Plate layouts — Legacy Plate V3: a
 * 70 × 45 mm Zamak niquelado plate whose FRONT carries the whole design
 * (resin-protected, NFC inlay under the FL panel). The BACK is functional:
 * a stamped stainless money clip, never printed. `engraving_area` is the
 * printable area of the front; the clip/depth columns describe the
 * physical piece and are editable in case the supplier adjusts them.
 * The back_* columns survive only for historical plates.
 * See docs/architecture/legacy-plate-v3.md.
 */
#[Fillable([
    'uuid', 'name', 'slug', 'layout_slot', 'layout_style', 'sku', 'description', 'width_mm', 'height_mm',
    'engraving_area', 'back_area', 'active', 'preview_image_path', 'metadata',
    'front_artwork_path', 'back_artwork_path', 'front_background', 'back_background',
    'front_text_color', 'back_text_color', 'spec_version', 'clip_length_mm', 'clip_height_mm',
    'clip_thickness_mm', 'total_depth_mm', 'body_material', 'clip_material',
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
            'clip_length_mm' => 'decimal:2',
            'clip_height_mm' => 'decimal:2',
            'clip_thickness_mm' => 'decimal:2',
            'total_depth_mm' => 'decimal:2',
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
     * Physical specification of the piece (shared shape with
     * resources/js/lib/plate-art.ts `PlateSpec`).
     *
     * @return array<string, mixed>
     */
    public function specArray(): array
    {
        return [
            'spec_version' => $this->spec_version ?? 'v3',
            'width_mm' => (float) $this->width_mm,
            'height_mm' => (float) $this->height_mm,
            'clip_length_mm' => (float) ($this->clip_length_mm ?? 52),
            'clip_height_mm' => (float) ($this->clip_height_mm ?? 20),
            'clip_thickness_mm' => (float) ($this->clip_thickness_mm ?? 1),
            'total_depth_mm' => (float) ($this->total_depth_mm ?? 6),
            'body_material' => $this->body_material ?? 'Zamak niquelado',
            'clip_material' => $this->clip_material ?? 'Acero inoxidable estampado',
        ];
    }

    /**
     * The exact shape LegacyPlateViewer.vue's `LegacyPlateModelData` expects
     * — kept on the model so every consumer (Mi Legado, admin plate detail)
     * builds it the same way. V3 is front-only: only front fields are sent.
     * Assumes `fields` is already eager-loaded.
     *
     * @return array<string, mixed>
     */
    public function toViewerArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'spec_version' => $this->spec_version ?? 'v3',
            'width_mm' => (float) $this->width_mm,
            'height_mm' => (float) $this->height_mm,
            'engraving_area' => $this->engraving_area,
            'spec' => $this->specArray(),
            'preview_image_url' => $this->preview_image_path ? Storage::disk('public')->url($this->preview_image_path) : null,
            'front_artwork_url' => $this->front_artwork_path ? Storage::disk('public')->url($this->front_artwork_path) : null,
            'front_background' => $this->front_background ?? '#F3F2EE',
            'layout_style' => $this->layout_style ?? 'nucleo',
            'front_text_color' => $this->front_text_color ?? '#171714',
            'fields' => $this->fields
                ->filter(fn (LegacyPlateModelField $field) => ($field->face ?? 'front') === 'front')
                ->map(fn (LegacyPlateModelField $field) => [
                    'field_key' => $field->field_key->value,
                    'face' => 'front',
                    'x' => (float) $field->x,
                    'y' => (float) $field->y,
                    'width' => (float) $field->width,
                    'height' => (float) $field->height,
                    'font_size' => $field->font_size !== null ? (float) $field->font_size : null,
                    'alignment' => $field->alignment->value,
                    'visible' => (bool) $field->getAttribute('visible'),
                ])->values(),
        ];
    }

    /**
     * Frozen copy stored on a Plate when it is generated, so later layout
     * edits never change an order already in production.
     *
     * @return array<string, mixed>
     */
    public function toSnapshotArray(): array
    {
        $viewer = $this->toViewerArray();
        $viewer['fields'] = $viewer['fields']->all();
        unset($viewer['preview_image_url']);
        $viewer['front_artwork_path'] = $this->front_artwork_path;
        unset($viewer['front_artwork_url']);

        return $viewer;
    }
}
