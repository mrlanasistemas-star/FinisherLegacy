<?php

namespace App\Support\Notifications;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Jobs\SendPushBatchJob;
use App\Models\PushDevice;
use App\Models\User;

/**
 * THE public API to push to people's phones (docs/renovacion/notificaciones.md
 * §API pública). Everything that pushes — customer notifications
 * (SendAthleteNotification), admin alerts (NotifyAdmins), the push-campaign
 * panel — goes through here; nobody builds an Expo request by hand.
 *
 *     app(PushSender::class)->sendToUsers([1, 2, 3], PushMessage::make('Título', 'Texto')
 *         ->withDeepLink('/store/orders/'.$order->uuid)
 *         ->withImage('https://…/foto.jpg')
 *         ->withCategory(NotificationCategory::Avisos));
 *
 * What it does, never inline in the request:
 *  1. drops users who turned push OFF for `$message->category` (when set);
 *  2. finds their ACTIVE devices (any number per user);
 *  3. queues App\Jobs\SendPushBatchJob per 100 devices (Expo's per-request
 *     limit) on the `realtime` queue. The job computes the per-user iOS
 *     badge (unread count) when `$message->badge` is null, sends, stores
 *     tickets for the receipt check, deactivates dead tokens and retries
 *     only the transient failures.
 *
 * Push is additive: this never writes the in-app notification row. For
 * "inbox + push" use App\Actions\Notifications\SendAthleteNotification
 * (one user) — it calls this with the stored notification id in `data`.
 */
class PushSender
{
    public const int BATCH_SIZE = 100;

    public function __construct(
        private readonly PushNotificationGateway $gateway,
        private readonly NotificationPreferences $preferences,
    ) {}

    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }

    /**
     * @param  iterable<int|User>  $userIds
     */
    public function sendToUsers(iterable $userIds, PushMessage $message, int $delaySeconds = 0): PushDispatchSummary
    {
        $ids = [];

        foreach ($userIds as $user) {
            $ids[] = $user instanceof User ? $user->id : (int) $user;
        }

        $ids = array_values(array_unique($ids));

        if (! $this->isConfigured()) {
            return new PushDispatchSummary(configured: false, users: count($ids));
        }

        $allowed = $message->category !== null
            ? $this->preferences->filterUsers($ids, $message->category, 'push')
            : $ids;

        $devices = 0;
        $batches = 0;

        foreach (array_chunk($allowed, 1000) as $chunk) {
            PushDevice::query()
                ->whereIn('user_id', $chunk)
                ->where('active', true)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->chunk(self::BATCH_SIZE)
                ->each(function ($deviceIds) use ($message, $delaySeconds, &$devices, &$batches) {
                    $devices += $deviceIds->count();
                    $batches++;
                    $job = SendPushBatchJob::dispatch($deviceIds->values()->all(), $message);

                    if ($delaySeconds > 0) {
                        $job->delay(now()->addSeconds($delaySeconds));
                    }
                });
        }

        return new PushDispatchSummary(
            configured: true,
            users: count($ids),
            skippedByPreference: count($ids) - count($allowed),
            devices: $devices,
            batches: $batches,
        );
    }

    public function sendToUser(User|int $user, PushMessage $message): PushDispatchSummary
    {
        return $this->sendToUsers([$user instanceof User ? $user->id : $user], $message);
    }
}
