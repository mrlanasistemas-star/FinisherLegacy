<?php

namespace App\Http\Requests\LegacyTags;

use App\Enums\StoryVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Story editor (owner only — authorised by LegacyStoryPolicy::update). */
class UpdateLegacyStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $story = $this->route('story');

        return $this->user() !== null && $story !== null && $this->user()->can('update', $story);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...StoryRaceRules::rules(required: false),
            'title' => ['nullable', 'string', 'max:120'],
            'recipient_label' => ['nullable', 'string', 'max:80'],
            'dedication' => ['nullable', 'string', 'max:600'],
            'body' => ['nullable', 'string', 'max:8000'],
            'external_url' => ['nullable', 'url:https,http', 'max:512'],
            'visibility' => ['required', Rule::enum(StoryVisibility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...StoryRaceRules::messages(),
            'external_url.url' => 'El enlace debe empezar con https://',
        ];
    }
}
