<?php

namespace Database\Factories;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    protected $model = ProductMedia::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => ProductMediaType::Image,
            'disk' => 'product_media',
            'path' => 'products/'.fake()->uuid().'.jpg',
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 500_000),
            'sort_order' => 0,
            'is_primary' => false,
            'alt_text' => null,
        ];
    }
}
