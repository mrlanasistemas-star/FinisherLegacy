<?php

namespace App\Jobs;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\PushDevice;
use App\Support\Notifications\PushNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One device, one push — dispatched per-device by
 * App\Actions\Notifications\SendAthleteNotification so a slow/failing
 * provider call never blocks the web request that triggered it (brief
 * §54/§59: controllers stay thin, no push call inline).
 *
 * Invalid tokens are deactivated by the gateway itself (Expo:
 * DeviceNotRegistered). Transient provider errors (429/5xx/network) are
 * released back onto the queue with backoff; anything else is final —
 * the database notification already saved, push is additive.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly PushDevice $device,
        public readonly PushNotificationPayload $payload,
    ) {
        $this->onQueue((string) config('queue.names.realtime', 'default'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(PushNotificationGateway $gateway): void
    {
        if (! $this->device->active) {
            return;
        }

        $result = $gateway->send($this->device, $this->payload);

        if ($result->retryable && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 600);
        }
    }
}
