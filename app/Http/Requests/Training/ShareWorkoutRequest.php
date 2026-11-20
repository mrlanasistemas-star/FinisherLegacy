<?php

namespace App\Http\Requests\Training;

use App\Enums\MomentVisibility;
use App\Rules\NoObjectionableContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /api/v1/training/workouts/{uuid}/share` — the athlete's explicit
 * "Compartir como Legacy Moment". Visibility defaults to private: sharing
 * further is always the athlete's choice.
 */
class ShareWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visibility' => ['nullable', Rule::enum(MomentVisibility::class)],
            'caption' => ['nullable', 'string', 'max:'.config('finisher.social.moment_caption_max'), new NoObjectionableContent],
        ];
    }

    public function visibility(): MomentVisibility
    {
        return MomentVisibility::tryFrom((string) $this->validated('visibility')) ?? MomentVisibility::Private;
    }
}
