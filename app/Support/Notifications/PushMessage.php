<?php

namespace App\Support\Notifications;

use App\Enums\NotificationCategory;

/**
 * One push message, provider-agnostic — the public input of
 * App\Support\Notifications\PushSender (docs/renovacion/notificaciones.md
 * §API pública). Immutable; use the `with*()` helpers to derive variants.
 *
 *  - `title` / `body`: what the phone shows (Expo caps the whole payload at 4 KB).
 *  - `data`: routing data the app reads on tap. The keys
 *    `notification_id`, `type`, `target`, `action_url` are the routing
 *    contract (App\Support\Notifications\NotificationTarget); any other key
 *    is passed through as-is (keep it small, never secrets).
 *  - `deepLink`: path or URL opened on tap (`/store/orders/{uuid}`,
 *    `finisherlegacy://training`, an https URL). Sent as `data.action_url`.
 *  - `imageUrl`: absolute https image → Android big picture (Expo
 *    `richContent.image`); iOS shows it only with a Notification Service
 *    Extension (see the docs).
 *  - `color`: Android accent (#RRGGBB); null = config('services.expo.color').
 *  - `channel`: Android channel id (default|pedidos|comunidad|entrenamientos|avisos).
 *    Null = the category's channel, else `default`.
 *  - `badge`: iOS app-icon badge. Null = the recipient's real unread count.
 *  - `category`: when set, users who turned push OFF for it are skipped.
 */
final class PushMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly ?string $deepLink = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $color = null,
        public readonly ?string $channel = null,
        public readonly ?int $badge = null,
        public readonly ?NotificationCategory $category = null,
        public readonly ?string $sound = 'default',
        public readonly ?int $ttlSeconds = null,
    ) {}

    public static function make(string $title, string $body): self
    {
        return new self($title, $body);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function withData(array $data): self
    {
        return $this->copy(['data' => [...$this->data, ...$data]]);
    }

    public function withDeepLink(?string $deepLink): self
    {
        return $this->copy(['deepLink' => $deepLink]);
    }

    public function withImage(?string $imageUrl): self
    {
        return $this->copy(['imageUrl' => $imageUrl]);
    }

    public function withCategory(?NotificationCategory $category): self
    {
        return $this->copy(['category' => $category]);
    }

    public function withChannel(?string $channel): self
    {
        return $this->copy(['channel' => $channel]);
    }

    public function withColor(?string $color): self
    {
        return $this->copy(['color' => $color]);
    }

    public function withBadge(?int $badge): self
    {
        return $this->copy(['badge' => $badge]);
    }

    public function resolvedChannel(): string
    {
        return $this->channel ?? $this->category?->androidChannel() ?? 'default';
    }

    /**
     * Routing data as the app expects it: the four contract keys first (null
     * when absent, so the app always sees a stable shape), then any extra
     * caller keys.
     *
     * @return array<string, mixed>
     */
    public function routingData(): array
    {
        $contract = [
            'notification_id' => $this->data['notification_id'] ?? null,
            'type' => $this->data['type'] ?? null,
            'target' => $this->data['target'] ?? null,
            'action_url' => $this->deepLink ?? ($this->data['action_url'] ?? null),
        ];

        $extra = array_diff_key($this->data, $contract);

        return [...$contract, ...$extra];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function copy(array $changes): self
    {
        $values = [
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'deepLink' => $this->deepLink,
            'imageUrl' => $this->imageUrl,
            'color' => $this->color,
            'channel' => $this->channel,
            'badge' => $this->badge,
            'category' => $this->category,
            'sound' => $this->sound,
            'ttlSeconds' => $this->ttlSeconds,
            ...$changes,
        ];

        return new self(...$values);
    }
}
