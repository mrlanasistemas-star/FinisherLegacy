<?php

namespace Database\Seeders;

use App\Models\LegacyPlateModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The three Legacy Plate layouts (the product offers exactly three). Each
 * is printed front + back and carries an NFC chip — no laser, no printed
 * QR. Names are provisional and editable from Administración → Legacy
 * Plates → Layouts; positions are only a starting point for the visual
 * editor. Idempotent by slug, and never adds a fourth layout.
 */
class LegacyPlateModelSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedModel(1, 'nucleo-reveal', 'Núcleo Reveal', 'LPM-NUCLEO-REVEAL', 'Nombre protagonista al frente; evento y fecha al reverso.', 90, 36);
        $this->seedModel(2, 'dial-de-distancia', 'Dial de Distancia', 'LPM-DIAL-DISTANCIA', 'La distancia como protagonista, con tiempo y ritmo al frente.', 90, 40);
        $this->seedModel(3, 'trayecto', 'Trayecto', 'LPM-TRAYECTO', 'Composición limpia: nombre y tiempo al frente, ficha completa del evento al reverso.', 90, 36);
    }

    private function seedModel(int $slot, string $slug, string $name, string $sku, string $description, float $w, float $h): void
    {
        $existing = LegacyPlateModel::query()->where('slug', $slug)->first();

        // Never create a fourth layout, even if someone renamed/replaced one.
        if ($existing === null && LegacyPlateModel::query()->count() >= LegacyPlateModel::MAX_LAYOUTS) {
            return;
        }

        $slotTaken = LegacyPlateModel::query()->where('layout_slot', $slot)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists();

        $model = LegacyPlateModel::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'uuid' => $existing->uuid ?? (string) Str::uuid(),
                'layout_slot' => $slotTaken ? $existing?->layout_slot : $slot,
                'name' => $existing->name ?? $name,
                'sku' => $existing->sku ?? $sku,
                'description' => $existing->description ?? $description,
                'width_mm' => $w,
                'height_mm' => $h,
                'engraving_area' => ['x' => 4, 'y' => 4, 'width' => $w - 8, 'height' => $h - 8],
                'back_area' => ['x' => 4, 'y' => 4, 'width' => $w - 8, 'height' => $h - 8],
                'active' => $existing->active ?? true,
            ],
        );

        $half = ($w - 16) / 2;
        $fields = [
            // Front
            ['athlete_name', 'front', 8, $h * 0.18, $w - 16, $h * 0.24, 5.5, 'center', true],
            ['race_label', 'front', 8, $h * 0.5, $half, $h * 0.16, 3.2, 'left', true],
            ['official_time', 'front', 8, $h * 0.68, $half, $h * 0.18, 4.2, 'left', true],
            ['pace', 'front', 8 + $half, $h * 0.68, $half, $h * 0.18, 3.2, 'right', true],
            // Back
            ['event_name', 'back', 8, $h * 0.16, $w - 16, $h * 0.2, 4.5, 'center', true],
            ['event_date', 'back', 8, $h * 0.4, $w - 16, $h * 0.14, 3.2, 'center', true],
            ['distance', 'back', 8, $h * 0.58, $w - 16, $h * 0.16, 4, 'center', true],
            ['overall_position', 'back', 8, $h * 0.78, $half, $h * 0.12, 3, 'left', false],
            ['bib_number', 'back', 8 + $half, $h * 0.78, $half, $h * 0.12, 3, 'right', false],
        ];

        $model->fields()->where('field_key', 'qr')->delete();

        foreach ($fields as $sort => [$key, $face, $x, $y, $width, $height, $size, $align, $visible]) {
            // Only create missing rows — an admin's edits in the visual
            // editor are never overwritten by a re-seed.
            $model->fields()->firstOrCreate(
                ['field_key' => $key],
                [
                    'face' => $face,
                    'x' => round($x, 2), 'y' => round($y, 2),
                    'width' => round($width, 2), 'height' => round($height, 2),
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
