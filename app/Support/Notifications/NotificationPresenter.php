<?php

namespace App\Support\Notifications;

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
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_human' => $notification->created_at?->diffForHumans(),
        ];
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
