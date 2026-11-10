<?php

namespace App\Contracts\Notifications;

use App\Models\PushDevice;
use App\Support\Notifications\PushBatchResult;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushNotificationPayload;
use App\Support\Notifications\PushSendResult;

/**
 * A push provider. Bound in AppServiceProvider from PUSH_PROVIDER:
 * `expo` → App\Services\Notifications\ExpoPushNotificationGateway (Expo Push
 * Service, which relays to FCM v1 / APNs with the credentials uploaded to
 * EAS); anything else → NullPushNotificationGateway (never fails, never
 * pretends it sent). Callers go through App\Support\Notifications\PushSender,
 * never through a provider SDK (docs/renovacion/notificaciones.md).
 */
interface PushNotificationGateway
{
    /**
     * One device, one message — kept for the admin "Enviar también push"
     * single-recipient flows and older callers.
     */
    public function send(PushDevice $device, PushNotificationPayload $payload): PushSendResult;

    /**
     * Up to 100 devices in one provider request.
     *
     * @param  array<int, PushDevice>  $devices
     * @param  array<int, int>  $badgesByUser  user id => iOS badge (used when `$message->badge` is null)
     */
    public function sendBatch(array $devices, PushMessage $message, array $badgesByUser = []): PushBatchResult;

    /**
     * Whether this gateway can actually deliver a push right now — the
     * single source of truth the UI checks before offering push.
     */
    public function isConfigured(): bool;
}
