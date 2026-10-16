<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Support\Notifications\NotificationTarget;
use App\Support\Realtime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The one notification class every admin-to-athlete message goes through
 * (product UX consolidation brief §36-§38, §52-§56) — templates just
 * pre-fill title/message before this is constructed, they don't branch
 * into separate Notification classes.
 *
 * Channels: `database` always (the inbox is the source of truth) plus
 * `broadcast` when a real broadcaster is configured (Reverb) — the web bell
 * and the app get a `notification.created` event on the private
 * `App.Models.User.{id}` channel (docs/ARCHITECTURE.md §Realtime).
 */
class AthleteAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly NotificationType $type,
        public readonly ?string $actionUrl = null,
        public readonly array $metadata = [],
        public readonly ?User $sentBy = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return Realtime::enabled() ? ['database', 'broadcast'] : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type->value,
            'action_url' => $this->actionUrl,
            'metadata' => $this->metadata,
            'sent_by_user_id' => $this->sentBy?->id,
            'sent_by_name' => $this->sentBy?->name,
        ];
    }

    /**
     * Same shape as one row of GET /api/v1/me/notifications (minus `target`
     * whitelisting, which only carries routing keys). Never the admin-side
     * metadata or the sender id.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->broadcastPayload()))
            ->onQueue((string) config('queue.names.realtime', 'default'));
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->broadcastPayload();
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastType(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    private function broadcastPayload(): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type->value,
            'action_url' => $this->actionUrl,
            // Same {type, id|uuid, slug?, event_slug?} shape as push data.
            'target' => NotificationTarget::fromMetadata($this->metadata),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
