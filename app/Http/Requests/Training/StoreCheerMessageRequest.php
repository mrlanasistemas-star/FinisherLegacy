<?php

namespace App\Http\Requests\Training;

use App\Enums\CheerIcon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public cheer submit (`POST /animo/{token}`, no account). Shape checks
 * only — the audio's real type is sniffed from its bytes by MediaStorage
 * inside App\Actions\Training\SubmitCheerMessage, and its duration probed
 * there. `website` is a honeypot real people never see.
 */
class StoreCheerMessageRequest extends FormRequest
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
        $maxKb = intdiv((int) config('training.cheers.max_audio_bytes'), 1024);

        return [
            'sender_name' => ['required', 'string', 'max:60'],
            'relationship' => ['nullable', 'string', 'max:40'],
            'body' => ['nullable', 'string', 'max:'.(int) config('training.cheers.max_body', 500), 'required_without:audio'],
            'icon' => ['required', Rule::enum(CheerIcon::class)],
            'audio' => ['nullable', 'file', 'max:'.$maxKb, 'required_without:body'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:'.((int) config('training.cheers.max_audio_seconds', 180) + 1)],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sender_name.required' => 'Escribe tu nombre para que sepa quién le manda ánimo.',
            'sender_name.max' => 'Tu nombre puede tener hasta 60 caracteres.',
            'relationship.max' => 'Usa hasta 40 caracteres.',
            'body.max' => 'El texto puede tener hasta 500 caracteres.',
            'body.required_without' => 'Graba un mensaje de voz o escribe unas palabras.',
            'audio.required_without' => 'Graba un mensaje de voz o escribe unas palabras.',
            'audio.max' => 'El audio pesa más de '.intdiv((int) config('training.cheers.max_audio_bytes'), 1024 * 1024).' MB.',
            'audio.file' => 'No pudimos recibir el audio. Intenta de nuevo.',
            'icon.required' => 'Elige un ícono.',
            'icon.enum' => 'Elige uno de los íconos.',
            'duration_seconds.max' => 'El mensaje dura más de 3 minutos. Graba uno más corto.',
        ];
    }
}
