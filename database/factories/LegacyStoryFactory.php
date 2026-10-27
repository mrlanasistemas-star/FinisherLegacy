<?php

namespace Database\Factories;

use App\Actions\LegacyTags\ActivateTag;
use App\Enums\StoryVisibility;
use App\Models\Athlete;
use App\Models\LegacyStory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegacyStory>
 */
class LegacyStoryFactory extends Factory
{
    protected $model = LegacyStory::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'public_id' => ActivateTag::newPublicId(),
            'athlete_id' => Athlete::factory(),
            'title' => fake()->sentence(3),
            'visibility' => StoryVisibility::Unlisted,
        ];
    }
}
