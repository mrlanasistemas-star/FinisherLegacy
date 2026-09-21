<?php

namespace App\Support\Notifications;

/**
 * Provider-agnostic push content — the same shape whatever
 * App\Contracts\Notifications\PushNotificationGateway implementation
 * eventually reads it (Expo/FCM/APNs, brief §52).
 */
final class PushNotificationPayload
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
        public readonly array $data = [],
    ) {}
}
