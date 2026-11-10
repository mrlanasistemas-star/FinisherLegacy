<?php

namespace App\Support\Notifications;

/**
 * Outcome of one provider request carrying up to 100 messages
 * (PushNotificationGateway::sendBatch). `retryableDeviceIds` are the devices
 * whose message hit a transient error (429 / 5xx / network /
 * MessageRateExceeded) — App\Jobs\SendPushBatchJob re-queues only those, so
 * a retry never notifies the others twice.
 */
final class PushBatchResult
{
    /**
     * @param  list<int>  $retryableDeviceIds
     */
    public function __construct(
        public readonly int $sent = 0,
        public readonly int $failed = 0,
        public readonly int $deactivated = 0,
        public readonly array $retryableDeviceIds = [],
        public readonly bool $configured = true,
    ) {}
}
