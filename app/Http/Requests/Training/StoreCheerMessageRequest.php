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
            'sender_name.required' => __('Escribe tu nombre para que sepa quién le manda ánimo.'),
            'sender_name.max' => __('Tu nombre puede tener hasta 60 caracteres.'),
            'relationship.max' => __('Usa hasta 40 caracteres.'),
            'body.max' => __('El texto puede tener hasta 500 caracteres.'),
            'body.required_without' => __('Graba un mensaje de voz o escribe unas palabras.'),
            'audio.required_without' => __('Graba un mensaje de voz o escribe unas palabras.'),
            'audio.max' => __('El audio pesa más de :size MB.', ['size' => intdiv((int) config('training.cheers.max_audio_bytes'), 1024 * 1024)]),
            'audio.file' => __('No pudimos recibir el audio. Intenta de nuevo.'),
            'icon.required' => __('Elige un ícono.'),
            'icon.enum' => __('Elige uno de los íconos.'),
            'duration_seconds.max' => __('El mensaje dura más de 3 minutos. Graba uno más corto.'),
        ];
    }
}
