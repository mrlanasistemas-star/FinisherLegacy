<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The one notification class every admin-to-athlete message goes through
 * (product UX consolidation brief §36-§38, §52-§56) — templates just
 * pre-fill title/message before this is constructed, they don't branch
 * into separate Notification classes. `via()` is database-only for now;
 * adding 'mail' or a push channel later is a one-line change here, not a
 * new class per channel.
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
        return ['database'];
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
}
