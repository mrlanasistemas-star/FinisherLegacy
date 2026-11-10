<?php

namespace App\Support\Notifications;

/**
 * What App\Support\Notifications\PushSender queued for one call: how many
 * users were asked for, how many were skipped by their preferences, how many
 * active devices were found and how many queued batches (≤100 devices each,
 * the Expo per-request limit) carry them. `configured = false` means no push
 * provider is bound (PUSH_PROVIDER≠expo) and nothing was queued.
 */
final class PushDispatchSummary
{
    public function __construct(
        public readonly bool $configured,
        public readonly int $users = 0,
        public readonly int $skippedByPreference = 0,
        public readonly int $devices = 0,
        public readonly int $batches = 0,
    ) {}

    /**
     * @return array{configured: bool, users: int, skipped_by_preference: int, devices: int, batches: int}
     */
    public function toArray(): array
    {
        return [
            'configured' => $this->configured,
            'users' => $this->users,
            'skipped_by_preference' => $this->skippedByPreference,
            'devices' => $this->devices,
            'batches' => $this->batches,
        ];
    }
}
