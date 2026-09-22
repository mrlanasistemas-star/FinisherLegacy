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

    /**
     * Whether this gateway can actually deliver a push right now — the
     * single source of truth the UI checks before ever offering an
     * "Enviar también push" option (consolidation brief §31-§33). Never a
     * separate config flag that could drift from what's actually bound:
     * asking the gateway itself can't lie.
     */
    public function isConfigured(): bool;
}
