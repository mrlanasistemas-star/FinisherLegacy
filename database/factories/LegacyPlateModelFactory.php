<?php

namespace Database\Factories;

use App\Models\LegacyPlateModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegacyPlateModel>
 */
class LegacyPlateModelFactory extends Factory
{
    protected $model = LegacyPlateModel::class;

    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'sku' => 'LPM-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->sentence(),
            // Legacy Plate V3 default piece (70 × 45 mm).
            'width_mm' => 70,
            'height_mm' => 45,
            'engraving_area' => ['x' => 3, 'y' => 3, 'width' => 64, 'height' => 39],
            'spec_version' => 'v3',
            'active' => true,
            'preview_image_path' => null,
            'metadata' => null,
        ];
    }
}
