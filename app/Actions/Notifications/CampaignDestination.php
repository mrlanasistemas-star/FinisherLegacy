<?php

namespace App\Actions\Notifications;

use App\Models\Collection;
use App\Models\Event;
use App\Models\Product;
use App\Models\PushCampaign;

/**
 * Where a push campaign takes the user when they tap it
 * (docs/renovacion/campanas-push.md §Destino). One whitelisted list of
 * app screens, each resolved to three things that must never drift:
 *
 *  - `deep_link`  — `finisherlegacy://…` the mobile app routes on;
 *  - `action_url` — the same screen on the web (inbox / bell click);
 *  - `target`     — the structured {type, slug?} NotificationTarget shape.
 *
 * External links are accepted only over https on finisherlegacy.com (or a
 * subdomain): a campaign can never send users to a third-party site.
 */
final class CampaignDestination
{
    /** @var array<string, string> */
    public const array TYPES = [
        'home' => 'Inicio',
        'store' => 'Tienda',
        'collection' => 'Colección',
        'product' => 'Producto',
        'training' => 'Entrenamientos',
        'memories' => 'Mis recuerdos',
        'event' => 'Evento',
        'url' => 'Enlace de finisherlegacy.com',
    ];

    /** Types that need a `link_value` (a slug or a URL). */
    public const array WITH_VALUE = ['collection', 'product', 'event', 'url'];

    public const string ALLOWED_HOST = 'finisherlegacy.com';

    public static function isAllowedUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '' || mb_strlen($url) > 500) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return $host === self::ALLOWED_HOST || str_ends_with($host, '.'.self::ALLOWED_HOST);
    }

    /**
     * Human label for the chosen destination (stored as `link_label` so
     * the history keeps reading right even if the product is renamed).
     */
    public static function label(string $type, ?string $value): ?string
    {
        return match ($type) {
            'collection' => Collection::query()->where('slug', $value)->value('name'),
            'product' => Product::query()->where('slug', $value)->value('name'),
            'event' => Event::query()->where('slug', $value)->value('name'),
            'url' => $value,
            default => self::TYPES[$type] ?? null,
        };
    }

    /** Whether the slug still points at a real record. */
    public static function exists(string $type, ?string $value): bool
    {
        return match ($type) {
            'collection' => Collection::query()->where('slug', $value)->exists(),
            'product' => Product::query()->where('slug', $value)->exists(),
            'event' => Event::query()->where('slug', $value)->exists(),
            'url' => self::isAllowedUrl($value),
            default => array_key_exists($type, self::TYPES),
        };
    }

    /**
     * @return array{deep_link: string, action_url: string, target: array<string, string>|null}
     */
    public static function resolve(PushCampaign $campaign): array
    {
        return self::resolveFor($campaign->link_type, $campaign->link_value);
    }

    /**
     * @return array{deep_link: string, action_url: string, target: array<string, string>|null}
     */
    public static function resolveFor(string $type, ?string $value): array
    {
        $scheme = (string) config('nfc.app.scheme', 'finisherlegacy');
        $slug = rawurlencode((string) $value);

        return match ($type) {
            'store' => ['deep_link' => "{$scheme}://store", 'action_url' => '/tienda', 'target' => null],
            'collection' => ['deep_link' => "{$scheme}://collections/{$slug}", 'action_url' => "/colecciones/{$slug}", 'target' => null],
            'product' => ['deep_link' => "{$scheme}://products/{$slug}", 'action_url' => "/tienda/{$slug}", 'target' => ['type' => 'product', 'slug' => (string) $value]],
            'training' => ['deep_link' => "{$scheme}://training", 'action_url' => '/mi-entrenamiento', 'target' => null],
            'memories' => ['deep_link' => "{$scheme}://my-legacy", 'action_url' => '/mi-legacy', 'target' => null],
            'event' => ['deep_link' => "{$scheme}://events/{$slug}", 'action_url' => "/events/{$slug}", 'target' => ['type' => 'event', 'slug' => (string) $value, 'event_slug' => (string) $value]],
            'url' => self::isAllowedUrl($value)
                ? ['deep_link' => (string) $value, 'action_url' => (string) $value, 'target' => null]
                : ['deep_link' => "{$scheme}://home", 'action_url' => '/', 'target' => null],
            default => ['deep_link' => "{$scheme}://home", 'action_url' => '/', 'target' => null],
        };
    }
}
