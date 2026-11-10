<?php

namespace App\Jobs;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\PushDevice;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\UnreadCounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Up to 100 devices, one Expo request (queued by
 * App\Support\Notifications\PushSender). Holds device ids, never tokens, so
 * the queue payload never carries a push token.
 *
 * Transient failures (429 / 5xx / network / MessageRateExceeded) re-queue a
 * NEW job holding only the failed device ids, with backoff — the devices
 * that already got the message are never notified twice. Dead tokens
 * (DeviceNotRegistered) are deactivated by the gateway.
 */
class SendPushBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const int MAX_ATTEMPTS = 4;

    /** @var list<int> */
    public const array BACKOFF = [30, 120, 600];

    public int $tries = 1;

    /**
     * @param  array<int, int>  $deviceIds
     */
    public function __construct(
        public readonly array $deviceIds,
        public readonly PushMessage $message,
        public readonly int $attempt = 1,
    ) {
        $this->onQueue((string) config('queue.names.realtime', 'default'));
    }

    public function handle(PushNotificationGateway $gateway, UnreadCounter $unread): void
    {
        $devices = PushDevice::query()
            ->whereIn('id', $this->deviceIds)
            ->where('active', true)
            ->get()
            ->all();

        if ($devices === []) {
            return;
        }

        $badges = $this->message->badge === null
            ? $unread->forUsers(array_map(fn (PushDevice $d) => (int) $d->user_id, $devices))
            : [];

        $result = $gateway->sendBatch($devices, $this->message, $badges);

        if ($result->retryableDeviceIds !== [] && $this->attempt < self::MAX_ATTEMPTS) {
            self::dispatch($result->retryableDeviceIds, $this->message, $this->attempt + 1)
                ->delay(now()->addSeconds(self::BACKOFF[$this->attempt - 1] ?? 600));
        }
    }
}
