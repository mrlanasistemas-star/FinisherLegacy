<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Notifications\AthleteAlert;
use App\Support\Notifications\NotificationTarget;
use App\Support\Notifications\PushNotificationPayload;
use Illuminate\Support\Str;

/**
 * The one Action Central "send a user a notification" goes through
 * (consolidation brief §36-§38, §49, §52) — Web (participant profile,
 * Athlete profile) and any future admin/API caller all call this instead
 * of constructing App\Notifications\AthleteAlert by hand or dispatching
 * push directly. `$push = true` additionally queues one
 * App\Jobs\SendPushNotificationJob per active PushDevice the recipient
 * has (brief §54) — the database notification always saves regardless of
 * whether any push actually gets delivered (brief §51).
 */
class SendAthleteNotification
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        User $recipient,
        string $title,
        string $message,
        NotificationType $type = NotificationType::Custom,
        ?string $actionUrl = null,
        array $metadata = [],
        ?User $sentBy = null,
        bool $push = false,
    ): void {
        $alert = new AthleteAlert($title, $message, $type, $actionUrl, $metadata, $sentBy);
        // Fixed id up front: the database row, the realtime event and the
        // push `data.notification_id` must all carry the same one (a queued
        // notification otherwise only gets its id on an internal clone).
        $alert->id = (string) Str::uuid();

        $recipient->notify($alert);

        // An admin-sent notification is audited here once, regardless of
        // whether the caller was Web or API (consolidation brief §42) —
        // never the message body itself (could be considered sensitive),
        // never a push token.
        if ($sentBy !== null) {
            activity()
                ->causedBy($sentBy)
                ->performedOn($recipient)
                ->withProperties(['type' => $type->value, 'push_requested' => $push])
                ->log('notification_sent');
        }

        if (! $push) {
            return;
        }

        // Mobile push-tap routing contract: exactly
        // {notification_id, type, target, action_url} — target is
        // {type, id|uuid, slug?, event_slug?} (NotificationTarget).
        $payload = new PushNotificationPayload($title, $message, $actionUrl, [
            'notification_id' => $alert->id,
            'type' => $type->value,
            'target' => NotificationTarget::fromMetadata($metadata),
            'action_url' => $actionUrl,
        ]);

        foreach ($recipient->pushDevices()->where('active', true)->get() as $device) {
            SendPushNotificationJob::dispatch($device, $payload);
        }
    }
}
