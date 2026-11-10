<?php

namespace App\Support\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One notification row for the web (inbox page + bell dropdown) — same keys
 * as GET /api/v1/me/notifications plus a human `created_human`, so the web
 * bell can merge realtime `notification.created` payloads and server rows.
 * Never exposes admin-side metadata or the sender.
 */
final class NotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function row(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'type' => $data['type'] ?? null,
            'action_url' => self::safeUrl($data['action_url'] ?? null),
            'category' => self::category($data),
            'image_url' => self::safeUrl($data['image_url'] ?? null),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_human' => $notification->created_at?->diffForHumans(),
        ];
    }

    /**
     * One row of GET /api/v1/me/notifications — the same fields as the web
     * row plus the structured deep-link `target`. `target` carries the
     * canonical `{type, id|uuid, slug?, event_slug?}` keys (same as push
     * `data.target`) AND the legacy `{kind, username?, moment_uuid?,
     * order_uuid?}` keys older app builds route on.
     *
     * @return array<string, mixed>
     */
    public static function apiRow(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $row = self::row($notification);
        unset($row['created_human']);
        // The app maps web URLs itself; keep the stored value (absolute URLs included).
        $row['action_url'] = $data['action_url'] ?? null;
        $row['target'] = self::apiTarget(is_array($data['metadata'] ?? null) ? $data['metadata'] : null);
        $row['created_at'] = $notification->created_at?->toIso8601String();

        return $row;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, int|string>|null
     */
    public static function apiTarget(?array $metadata): ?array
    {
        $target = NotificationTarget::fromMetadata($metadata);

        if ($target === null) {
            return null;
        }

        $legacy = match ($target['type']) {
            'athlete' => ['kind' => 'athlete', 'username' => $target['slug'] ?? null],
            'moment' => ['kind' => 'moment', 'moment_uuid' => $target['uuid'] ?? null],
            'order' => ['kind' => 'order', 'order_uuid' => $target['uuid'] ?? null],
            'event' => ['kind' => 'event', 'event_slug' => $target['event_slug'] ?? ($target['slug'] ?? null)],
            'training' => ['kind' => 'training'],
            default => [],
        };

        return array_filter([...$target, ...$legacy], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function category(array $data): ?string
    {
        $category = $data['metadata']['category'] ?? null;

        if (is_string($category)) {
            return $category;
        }

        $type = is_string($data['type'] ?? null) ? NotificationType::tryFrom($data['type']) : null;

        return $type?->category()->value;
    }

    /**
     * Only same-site relative paths or http(s) URLs — a stored `javascript:`
     * URL must never become a clickable href.
     */
    public static function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // Our own absolute URLs (social notifications store APP_URL/...) →
        // relative path, so the web can follow them as Inertia visits.
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($appHost !== null && ($parts['host'] ?? null) === $appHost) {
            return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return $url;
    }
}
