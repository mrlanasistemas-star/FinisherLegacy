<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word().' '.fake()->word();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'type' => ProductType::Apparel,
            'category_id' => null,
            'brand' => 'Finisher Legacy',
            'status' => ProductStatus::Active,
            'taxable' => false,
            'requires_shipping' => true,
            'qr_capable' => false,
            'tracks_inventory' => true,
            'active' => true,
        ];
    }

    public function legacyPlate(): static
    {
        return $this->state(fn () => [
            'type' => ProductType::LegacyPlate,
            'qr_capable' => true,
            'requires_shipping' => false,
            'tracks_inventory' => false,
        ]);
    }
}
