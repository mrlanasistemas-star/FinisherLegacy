<?php

namespace Database\Factories;

use App\Enums\NfcTagStatus;
use App\Models\NfcTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NfcTag>
 */
class NfcTagFactory extends Factory
{
    protected $model = NfcTag::class;

    public function definition(): array
    {
        return [
            'token' => Str::random(32),
            'status' => NfcTagStatus::Available,
            'batch_code' => null,
            'tap_count' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => NfcTagStatus::Disabled]);
    }
}
