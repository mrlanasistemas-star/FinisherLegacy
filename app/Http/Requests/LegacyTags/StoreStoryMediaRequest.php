<?php

namespace App\Http\Requests\LegacyTags;

use App\Enums\StoryMediaRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the outer shape is validated here (a file, a role, a size ceiling
 * so huge bodies fail fast). The real type check is MediaStorage sniffing
 * the bytes — the extension and the client MIME are never trusted.
 */
class StoreStoryMediaRequest extends FormRequest
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
        $maxKb = (int) ceil(max(
            (int) config('legacy.stories.max_photo_bytes'),
            (int) config('legacy.stories.max_audio_bytes'),
            (int) config('legacy.stories.max_certificate_bytes'),
        ) / 1024);

        return [
            'file' => ['required', 'file', 'max:'.$maxKb],
            'role' => ['required', Rule::enum(StoryMediaRole::class)],
            'caption' => ['nullable', 'string', 'max:280'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Elige un archivo.',
            'file.max' => 'El archivo es demasiado pesado. Prueba con uno más ligero.',
            'file.uploaded' => 'No pudimos recibir el archivo. Intenta de nuevo.',
        ];
    }
}
