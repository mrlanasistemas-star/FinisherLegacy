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
            'width_mm' => 90,
            'height_mm' => 36,
            'engraving_area' => ['x' => 8, 'y' => 6, 'width' => 74, 'height' => 24],
            'active' => true,
            'preview_image_path' => null,
            'metadata' => null,
        ];
    }
}
