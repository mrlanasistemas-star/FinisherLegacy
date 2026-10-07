<?php

namespace Database\Seeders;

use App\Models\LegacyPlateModel;
use App\Support\LegacyPlateLayouts;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The three Legacy Plate layouts (the product offers exactly three), each
 * with its own FRONT composition — see App\Support\LegacyPlateLayouts::v3().
 * Legacy Plate V3: 70 × 45 mm, design on the front only, NFC under the
 * front FL panel, stainless money clip on the (unprinted) back.
 * Idempotent by slug, never adds a fourth layout and never overwrites what
 * an admin edited in the visual editor (only missing rows are created).
 */
class LegacyPlateModelSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (LegacyPlateLayouts::v3() as $slug => $definition) {
            $this->seedModel($slug, $definition);
        }
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function seedModel(string $slug, array $d): void
    {
        $existing = LegacyPlateModel::query()->where('slug', $slug)->first();

        if ($existing === null && LegacyPlateModel::query()->count() >= LegacyPlateModel::MAX_LAYOUTS) {
            return;
        }

        $slotTaken = LegacyPlateModel::query()->where('layout_slot', $d['slot'])->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists();
        [$w, $h] = [$d['width'], $d['height']];

        $model = $existing ?? LegacyPlateModel::query()->create([
            'uuid' => (string) Str::uuid(),
            'slug' => $slug,
            'layout_slot' => $slotTaken ? null : $d['slot'],
            'layout_style' => $d['style'],
            'name' => $d['name'],
            'sku' => $d['sku'],
            'description' => $d['description'],
            'width_mm' => $w,
            'height_mm' => $h,
            'engraving_area' => ['x' => 3, 'y' => 3, 'width' => $w - 6, 'height' => $h - 6],
            'front_background' => $d['front_background'],
            'front_text_color' => $d['front_text_color'],
            'spec_version' => LegacyPlateLayouts::SPEC_VERSION,
            'clip_length_mm' => LegacyPlateLayouts::SPEC['clip_length_mm'],
            'clip_height_mm' => LegacyPlateLayouts::SPEC['clip_height_mm'],
            'clip_thickness_mm' => LegacyPlateLayouts::SPEC['clip_thickness_mm'],
            'total_depth_mm' => LegacyPlateLayouts::SPEC['total_depth_mm'],
            'active' => true,
        ]);

        if ($model->layout_style === null) {
            $model->update(['layout_style' => $d['style']]);
        }

        $model->fields()->where('field_key', 'qr')->delete();

        foreach ($d['fields'] as $sort => [$key, $face, $x, $y, $width, $height, $size, $align, $visible]) {
            $model->fields()->firstOrCreate(
                ['field_key' => $key],
                [
                    'face' => $face,
                    'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height,
                    'font_size' => $size, 'alignment' => $align,
                    'max_chars' => $key === 'athlete_name' ? 26 : null,
                    'required' => $key === 'athlete_name',
                    'visible' => $visible,
                    'sort_order' => $sort,
                ],
            );
        }
    }
}
