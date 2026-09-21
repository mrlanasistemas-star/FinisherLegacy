<?php

namespace App\Contracts\Notifications;

use App\Models\PushDevice;
use App\Support\Notifications\PushNotificationPayload;
use App\Support\Notifications\PushSendResult;

/**
 * A push provider (Expo/FCM/APNs — none implemented yet, brief §50/§52) —
 * App\Actions\Notifications\SendAthleteNotification and the queued job
 * that calls this never talk to a provider SDK directly, only through
 * this contract, the same shape as App\Contracts\Commerce\PaymentGateway.
 * Bound to App\Services\Notifications\NullPushNotificationGateway by
 * default (see AppServiceProvider) until a real provider exists.
 */
interface PushNotificationGateway
{
    public function send(PushDevice $device, PushNotificationPayload $payload): PushSendResult;
}
