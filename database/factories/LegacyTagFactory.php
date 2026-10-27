<?php

namespace Database\Factories;

use App\Enums\LegacyTagStatus;
use App\Models\LegacyTag;
use App\Models\ProductVariant;
use App\Support\LegacyTags\TagCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegacyTag>
 */
class LegacyTagFactory extends Factory
{
    protected $model = LegacyTag::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'serial' => TagCode::serial(fake()->unique()->numberBetween(1, 999999)),
            'public_code' => TagCode::generate(),
            'product_variant_id' => ProductVariant::factory(),
            'status' => LegacyTagStatus::InStock,
            'received_at' => now(),
        ];
    }
}
