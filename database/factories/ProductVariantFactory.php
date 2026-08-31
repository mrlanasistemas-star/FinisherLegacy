<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'uuid' => (string) Str::uuid(),
            'sku' => 'SKU-'.fake()->unique()->numberBetween(100000, 999999),
            'name' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'attributes' => ['size' => fake()->randomElement(['S', 'M', 'L', 'XL'])],
            'base_price_minor' => fake()->numberBetween(50000, 250000),
            'currency' => 'MXN',
            'cost_minor' => null,
            'weight_grams' => null,
            'active' => true,
        ];
    }
}
