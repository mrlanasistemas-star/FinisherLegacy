<?php

namespace Database\Seeders;

use App\Models\LegacyPlateModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The two initial Legacy Plate v2 references (brief §4/§19) — names are
 * provisional and fully editable from catalog, never hardcoded into
 * production rules (brief §19).
 */
class LegacyPlateModelSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedModel(
            slug: 'nucleo-reveal',
            name: 'Núcleo Reveal',
            sku: 'LPM-NUCLEO-REVEAL',
            description: 'Legacy Plate con zona de grabado central, acabado metálico mate.',
            widthMm: 90,
            heightMm: 36,
        );

        $this->seedModel(
            slug: 'dial-de-distancia',
            name: 'Dial de Distancia',
            sku: 'LPM-DIAL-DISTANCIA',
            description: 'Legacy Plate con relieve tipo dial que enmarca la distancia de la carrera.',
            widthMm: 90,
            heightMm: 40,
        );
    }

    private function seedModel(string $slug, string $name, string $sku, string $description, float $widthMm, float $heightMm): void
    {
        $model = LegacyPlateModel::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'sku' => $sku,
                'description' => $description,
                'width_mm' => $widthMm,
                'height_mm' => $heightMm,
                'engraving_area' => ['x' => 8, 'y' => 5, 'width' => $widthMm - 16, 'height' => $heightMm - 10],
                'active' => true,
            ],
        );

        $fields = [
            ['field_key' => 'athlete_name', 'x' => 8, 'y' => 6, 'width' => $widthMm - 16, 'height' => 8, 'font_size' => 5.5, 'alignment' => 'center', 'max_chars' => 26, 'required' => true, 'sort_order' => 0],
            ['field_key' => 'race_label', 'x' => 8, 'y' => 16, 'width' => ($widthMm - 16) / 2, 'height' => 6, 'font_size' => 3.5, 'alignment' => 'left', 'max_chars' => 16, 'required' => true, 'sort_order' => 1],
            ['field_key' => 'official_time', 'x' => 8, 'y' => 24, 'width' => ($widthMm - 16) / 2, 'height' => 6, 'font_size' => 4, 'alignment' => 'left', 'max_chars' => 10, 'required' => true, 'sort_order' => 2],
            ['field_key' => 'pace', 'x' => 8 + ($widthMm - 16) / 2, 'y' => 24, 'width' => ($widthMm - 16) / 2, 'height' => 6, 'font_size' => 3.5, 'alignment' => 'right', 'max_chars' => 10, 'required' => false, 'sort_order' => 3],
            ['field_key' => 'qr', 'x' => $widthMm - 22, 'y' => 6, 'width' => 14, 'height' => 14, 'font_size' => null, 'alignment' => 'center', 'max_chars' => null, 'required' => true, 'sort_order' => 4],
        ];

        foreach ($fields as $field) {
            $model->fields()->updateOrCreate(
                ['field_key' => $field['field_key']],
                [...$field, 'visible' => true],
            );
        }
    }
}
