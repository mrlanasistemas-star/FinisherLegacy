<?php

namespace App\Support;

/**
 * Single switch for "is a real broadcaster configured?". With
 * BROADCAST_CONNECTION=null (tests, minimal local dev) callers skip
 * dispatching broadcast events / the `broadcast` notification channel, so no
 * no-op broadcast jobs pile up in the queue. `log` still counts as enabled
 * (useful to see payloads locally). Domain events of other agents should
 * check this too before dispatching (docs/ARCHITECTURE.md §Realtime).
 */
final class Realtime
{
    public static function enabled(): bool
    {
        $default = config('broadcasting.default');

        if (! is_string($default) || $default === '' || $default === 'null') {
            return false;
        }

        // `reverb`/`pusher` copied from .env.example without credentials yet
        // → treat as off instead of failing every broadcast job.
        if (in_array($default, ['reverb', 'pusher'], true)) {
            return filled(config("broadcasting.connections.{$default}.key"));
        }

        return true;
    }
}
