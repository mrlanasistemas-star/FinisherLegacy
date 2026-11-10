<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\PushDevice;
use App\Support\Notifications\PushBatchResult;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushNotificationPayload;
use App\Support\Notifications\PushSendResult;

/**
 * The binding while PUSH_PROVIDER≠expo — never fails the request, never
 * invents a fake "sent". The in-app notification already saved regardless
 * of this result; push is purely additive.
 */
class NullPushNotificationGateway implements PushNotificationGateway
{
    public function send(PushDevice $device, PushNotificationPayload $payload): PushSendResult
    {
        return PushSendResult::notConfigured();
    }

    public function sendBatch(array $devices, PushMessage $message, array $badgesByUser = []): PushBatchResult
    {
        return new PushBatchResult(configured: false);
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
