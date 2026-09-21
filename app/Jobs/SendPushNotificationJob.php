<?php

namespace App\Jobs;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\PushDevice;
use App\Support\Notifications\PushNotificationPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One device, one push — dispatched per-device by
 * App\Actions\Notifications\SendAthleteNotification so a slow/failing
 * provider call never blocks the web request that triggered it (brief
 * §54/§59: controllers stay thin, no push call inline).
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly PushDevice $device,
        public readonly PushNotificationPayload $payload,
    ) {}

    public function handle(PushNotificationGateway $gateway): void
    {
        // Deactivating a device on an invalid-token failure is a real
        // provider's job to signal (brief §55) — the Null gateway never
        // fails, so there's nothing to react to here yet.
        $gateway->send($this->device, $this->payload);
    }
}
