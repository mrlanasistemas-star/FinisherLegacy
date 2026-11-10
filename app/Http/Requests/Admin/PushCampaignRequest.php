<?php

namespace App\Http\Requests\Admin;

use App\Actions\Notifications\CampaignAudience;
use App\Actions\Notifications\CampaignDestination;
use App\Models\PushCampaign;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Composer input of Notificaciones → Campañas push
 * (docs/renovacion/campanas-push.md). Plain text only (no tags), platform
 * length limits, whitelisted destination / audience / channel / category,
 * jpg/png ≤1 MB image and a future date/time in America/Mexico_City.
 *
 * `intent`: draft (save only) · test (save + send to me) · schedule ·
 * send (now). The confirmation step happens in the UI; the server
 * re-checks permission and the rate guard regardless.
 */
class PushCampaignRequest extends FormRequest
{
    public const array CHANNELS = ['avisos', 'default'];

    public const array INTENTS = ['draft', 'test', 'schedule', 'send'];

    public function authorize(): bool
    {
        return $this->user()?->can('notifications.broadcast') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_logo' => $this->boolean('include_logo', true),
            'save_to_inbox' => $this->boolean('save_to_inbox', true),
            'remove_image' => $this->boolean('remove_image'),
            'color' => $this->filled('color') ? $this->input('color') : null,
            'link_value' => $this->filled('link_value') ? trim((string) $this->input('link_value')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $noTags = 'not_regex:/<[^>]*>/';
        $maxKb = (int) config('push_campaigns.image_max_kb', 1024);

        return [
            'intent' => ['required', Rule::in(self::INTENTS)],
            'name' => ['nullable', 'string', 'max:120', $noTags],
            'title' => ['required', 'string', 'max:65', $noTags],
            'body' => ['required', 'string', 'max:240', $noTags],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', "max:{$maxKb}"],
            'remove_image' => ['boolean'],
            'include_logo' => ['boolean'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'channel' => ['required', Rule::in(self::CHANNELS)],
            'category' => ['required', Rule::in(PushCampaign::CATEGORIES)],
            'save_to_inbox' => ['boolean'],

            'link_type' => ['required', Rule::in(array_keys(CampaignDestination::TYPES))],
            'link_value' => [Rule::requiredIf(fn () => in_array($this->input('link_type'), CampaignDestination::WITH_VALUE, true)), 'nullable', 'string', 'max:500'],

            'audience_type' => ['required', Rule::in(array_keys(CampaignAudience::TYPES))],
            'audience_params' => ['nullable', 'array'],
            'audience_params.user_ids' => ['required_if:audience_type,users', 'array', 'max:500'],
            'audience_params.user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'audience_params.event_id' => ['required_if:audience_type,event', 'nullable', 'integer', Rule::exists('events', 'id')],
            'audience_params.edition_id' => ['nullable', 'integer', Rule::exists('event_editions', 'id')->where('event_id', (int) $this->input('audience_params.event_id'))],
            'audience_params.race_id' => ['nullable', 'integer', Rule::exists('event_races', 'id')],
            'audience_params.collection_id' => ['required_if:audience_type,collection', 'nullable', 'integer', Rule::exists('collections', 'id')],
            'audience_params.product_id' => ['required_if:audience_type,product', 'nullable', 'integer', Rule::exists('products', 'id')],
            'audience_params.role' => ['required_if:audience_type,role', 'nullable', 'string', Rule::exists('roles', 'name')],

            'scheduled_at' => ['required_if:intent,schedule', 'nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $type = (string) $this->input('link_type');

                if (in_array($type, CampaignDestination::WITH_VALUE, true) && ! CampaignDestination::exists($type, $this->input('link_value'))) {
                    $validator->errors()->add('link_value', $type === 'url'
                        ? 'Solo se permiten enlaces https de finisherlegacy.com.'
                        : 'Ese destino ya no existe. Elige otro.');
                }

                if (in_array($this->input('audience_type'), PushCampaign::PROMO_ONLY_AUDIENCES, true) && $this->input('category') !== 'promociones') {
                    $validator->errors()->add('category', 'A todos los usuarios o a los suscritos solo se pueden enviar novedades y promociones.');
                }

                if ($this->input('intent') === 'schedule' && $this->filled('scheduled_at') && ! $validator->errors()->has('scheduled_at')) {
                    $at = $this->scheduledAt();

                    if ($at === null || $at->lessThan(now()->addMinute())) {
                        $validator->errors()->add('scheduled_at', 'Elige una fecha y hora futura (hora del centro de México).');
                    } elseif ($at->greaterThan(now()->addMonths(6))) {
                        $validator->errors()->add('scheduled_at', 'Solo se puede programar hasta 6 meses adelante.');
                    }
                }
            },
        ];
    }

    /** The schedule input (America/Mexico_City wall time) as a UTC instant. */
    public function scheduledAt(): ?CarbonImmutable
    {
        $value = $this->input('scheduled_at');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, PushCampaign::TIMEZONE);

        return $at !== null ? $at->utc() : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'El título admite hasta 65 caracteres.',
            'body.max' => 'El mensaje admite hasta 240 caracteres.',
            '*.not_regex' => 'No se permite HTML: escribe solo texto.',
            'title.not_regex' => 'No se permite HTML: escribe solo texto.',
            'body.not_regex' => 'No se permite HTML: escribe solo texto.',
            'image.max' => 'La imagen debe pesar 1 MB o menos.',
            'image.mimes' => 'La imagen debe ser JPG o PNG.',
            'image.mimetypes' => 'La imagen debe ser JPG o PNG.',
            'color.regex' => 'Usa un color hexadecimal como #C9A45C.',
            'audience_params.user_ids.required_if' => 'Elige al menos un usuario.',
            'audience_params.event_id.required_if' => 'Elige un evento.',
            'audience_params.collection_id.required_if' => 'Elige una colección.',
            'audience_params.product_id.required_if' => 'Elige un producto.',
            'audience_params.role.required_if' => 'Elige un rol.',
            'scheduled_at.required_if' => 'Elige la fecha y hora de envío.',
            'link_value.required' => 'Elige el destino.',
        ];
    }
}
