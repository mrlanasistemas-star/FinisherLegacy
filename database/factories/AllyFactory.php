<?php

namespace Database\Factories;

use App\Enums\AllyStatus;
use App\Enums\AllyType;
use App\Models\Ally;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ally>
 */
class AllyFactory extends Factory
{
    protected $model = Ally::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'type' => AllyType::Gym,
            'status' => AllyStatus::Active,
        ];
    }
}
