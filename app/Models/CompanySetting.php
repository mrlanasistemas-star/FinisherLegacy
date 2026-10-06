<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value company information shown on "Nosotros", "Contacto" and the
 * footer. Only the keys declared in FIELDS exist; anything not saved yet
 * is simply absent (contact data) or falls back to the neutral, editable
 * copy in DEFAULTS (narrative text) — no address, phone, email or
 * history is ever invented.
 *
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class CompanySetting extends Model
{
    private const string CACHE_KEY = 'company_settings.all';

    /**
     * Admin form definition: key => [label, group, input type].
     *
     * @var array<string, array{label: string, group: string, type: string}>
     */
    public const array FIELDS = [
        'about_intro' => ['label' => 'Quiénes somos (introducción)', 'group' => 'historia', 'type' => 'textarea'],
        'about_problem' => ['label' => 'Qué problema resolvemos', 'group' => 'historia', 'type' => 'textarea'],
        'about_origin' => ['label' => 'Por qué nace Finisher Legacy', 'group' => 'historia', 'type' => 'textarea'],
        'about_experience' => ['label' => 'Experiencia del equipo en el deporte', 'group' => 'historia', 'type' => 'textarea'],
        'about_vision' => ['label' => 'Visión a futuro', 'group' => 'historia', 'type' => 'textarea'],
        'country' => ['label' => 'País', 'group' => 'ubicacion', 'type' => 'text'],
        'city' => ['label' => 'Ciudad', 'group' => 'ubicacion', 'type' => 'text'],
        'address' => ['label' => 'Dirección comercial (opcional, se publica tal cual)', 'group' => 'ubicacion', 'type' => 'text'],
        'email' => ['label' => 'Correo de contacto', 'group' => 'contacto', 'type' => 'email'],
        'phone' => ['label' => 'Teléfono', 'group' => 'contacto', 'type' => 'text'],
        'whatsapp' => ['label' => 'WhatsApp (número con lada)', 'group' => 'contacto', 'type' => 'text'],
        'instagram_url' => ['label' => 'Instagram (URL)', 'group' => 'redes', 'type' => 'url'],
        'facebook_url' => ['label' => 'Facebook (URL)', 'group' => 'redes', 'type' => 'url'],
        'tiktok_url' => ['label' => 'TikTok (URL)', 'group' => 'redes', 'type' => 'url'],
        'youtube_url' => ['label' => 'YouTube (URL)', 'group' => 'redes', 'type' => 'url'],
        'linkedin_url' => ['label' => 'LinkedIn (URL)', 'group' => 'redes', 'type' => 'url'],
        'strava_url' => ['label' => 'Strava (URL)', 'group' => 'redes', 'type' => 'url'],
    ];

    /**
     * Neutral starting copy for the narrative blocks — describes what the
     * product does, claims nothing about the company's past. Shown until
     * an admin writes the real text. "México" is the operating country
     * confirmed by the client.
     *
     * @var array<string, string>
     */
    public const array DEFAULTS = [
        'about_intro' => 'Finisher Legacy es una plataforma deportiva que reúne en un solo lugar la historia de cada atleta: sus carreras, resultados, fotografías, medallas y los productos físicos que la acompañan.',
        'about_problem' => 'Cada meta cruzada deja recuerdos dispersos: un resultado en un sitio, fotos en otro, una medalla guardada en un cajón. Queremos que ese esfuerzo no se pierda y que cada atleta pueda conservarlo, revivirlo y compartirlo.',
        'about_origin' => 'Finisher Legacy nace desde el deporte y para el deporte, con la idea de conectar la experiencia física de terminar una competencia con una identidad digital que dure para siempre.',
        'about_experience' => '',
        'about_vision' => 'Construir el lugar donde cada atleta guarda su historia deportiva y donde organizadores, fotógrafos y marcas se conectan con la comunidad que la vive.',
        'country' => 'México',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Saved values merged over DEFAULTS, only for declared keys. Cached
     * (the footer reads this on every public page).
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        /** @var array<string, string|null> $saved */
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        $values = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $value = $saved[$key] ?? null;
            $values[$key] = filled($value) ? $value : (self::DEFAULTS[$key] ?? null);
        }

        return $values;
    }

    /**
     * Only real contact channels that were actually configured.
     *
     * @return array<string, string>
     */
    public static function contactChannels(): array
    {
        $values = self::values();
        $keys = ['email', 'phone', 'whatsapp', 'instagram_url', 'facebook_url', 'tiktok_url', 'youtube_url', 'linkedin_url', 'strava_url'];

        return array_filter(array_intersect_key($values, array_flip($keys)), fn ($value) => filled($value));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::FIELDS)) {
                continue;
            }

            static::query()->updateOrCreate(['key' => $key], ['value' => filled($value) ? trim((string) $value) : null]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
