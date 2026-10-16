<?php

namespace App\Events;

use App\Models\AthleteSupportMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A support message whose start/distance/finish condition was just crossed
 * by live tracking (SPEC §84). Goes to the athlete's private user channel
 * so the app can play it; same shape as
 * `GET me/support-sessions/{id}/triggered` items plus the trigger.
 *
 * The payload is built eagerly (a plain array) so the queued broadcast
 * never re-queries or carries a whole model.
 */
class SupportMessageTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @var array<string, mixed> */
    public array $payload;

    public function __construct(
        public int $userId,
        AthleteSupportMessage $message,
        string $trackingSessionUuid,
    ) {
        $this->payload = [
            'id' => $message->id,
            'support_session_id' => $message->athlete_support_session_id,
            'tracking_session_uuid' => $trackingSessionUuid,
            'type' => $message->type->value,
            'trigger_type' => $message->trigger_type->value,
            'trigger_distance_meters' => $message->trigger_distance_meters,
            'message_text' => $message->message_text,
            'audio_url' => $message->signedAudioUrl(),
            'contributor_name' => $message->contributor?->display_name,
            'is_surprise' => $message->is_surprise,
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'support.message.triggered';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
