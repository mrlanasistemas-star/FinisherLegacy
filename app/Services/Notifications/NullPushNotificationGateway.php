<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\PushDevice;
use App\Support\Notifications\PushNotificationPayload;
use App\Support\Notifications\PushSendResult;

/**
 * The default binding until a real provider (Expo/FCM/APNs) exists
 * (brief §51) — never fails the request, never invents a fake "sent".
 * The Laravel database notification this backs already saved regardless
 * of this result; push is purely additive.
 */
class NullPushNotificationGateway implements PushNotificationGateway
{
    public function send(PushDevice $device, PushNotificationPayload $payload): PushSendResult
    {
        return PushSendResult::notConfigured();
    }
}
