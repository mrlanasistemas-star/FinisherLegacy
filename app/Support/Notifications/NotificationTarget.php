<?php

namespace App\Support\Notifications;

/**
 * The structured deep-link target the mobile app routes on:
 * `{type, id|uuid, slug?, event_slug?}` (push `data.target` and the realtime
 * `notification.created` payload). Built from a notification's `metadata`:
 *
 *  - preferred: `metadata.target = {type, id?, uuid?, slug?, event_slug?}`
 *    (new senders should pass this directly);
 *  - legacy: `metadata.kind` + username / moment_uuid / order_uuid /
 *    event_slug (what NotifySocialActivity & co. store today).
 *
 * Only whitelisted keys ever leave the server — never the rest of metadata.
 */
final class NotificationTarget
{
    private const array TYPES = ['athlete', 'moment', 'order', 'event', 'participant', 'legacy_plate', 'product', 'tracking', 'support'];

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, int|string>|null
     */
    public static function fromMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (is_array($metadata['target'] ?? null)) {
            return self::clean($metadata['target']);
        }

        return match ($metadata['kind'] ?? null) {
            'athlete' => self::clean(['type' => 'athlete', 'slug' => $metadata['username'] ?? null]),
            'moment' => self::clean(['type' => 'moment', 'uuid' => $metadata['moment_uuid'] ?? null]),
            'order' => self::clean(['type' => 'order', 'uuid' => $metadata['order_uuid'] ?? null]),
            'event' => self::clean([
                'type' => 'event',
                'slug' => $metadata['event_slug'] ?? null,
                'event_slug' => $metadata['event_slug'] ?? null,
            ]),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, int|string>|null
     */
    private static function clean(array $target): ?array
    {
        if (! in_array($target['type'] ?? null, self::TYPES, true)) {
            return null;
        }

        $clean = ['type' => $target['type']];

        if (is_int($target['id'] ?? null) || (is_string($target['id'] ?? null) && ctype_digit($target['id']))) {
            $clean['id'] = (int) $target['id'];
        }

        foreach (['uuid', 'slug', 'event_slug'] as $key) {
            if (is_string($target[$key] ?? null) && $target[$key] !== '' && mb_strlen($target[$key]) <= 191) {
                $clean[$key] = $target[$key];
            }
        }

        return $clean;
    }
}
