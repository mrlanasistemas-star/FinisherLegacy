<?php

namespace Tests\Feature\Admin\PushCampaigns;

use App\Models\PushDevice;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\PushDispatchSummary;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushSender;

/**
 * Test double for App\Support\Notifications\PushSender used by the
 * push-campaign tests: records every call instead of queueing provider
 * batches, applies the same category preference filter as the real one
 * and counts the active devices it would have queued.
 */
class FakePushSender extends PushSender
{
    /** @var list<array{users: list<int>, message: PushMessage}> */
    public array $calls = [];

    public bool $configured = true;

    public function __construct() {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function sendToUsers(iterable $userIds, PushMessage $message, int $delaySeconds = 0): PushDispatchSummary
    {
        $ids = [];

        foreach ($userIds as $user) {
            $ids[] = is_int($user) ? $user : (int) $user->id;
        }

        $allowed = $message->category !== null
            ? app(NotificationPreferences::class)->filterUsers($ids, $message->category, 'push')
            : $ids;

        $this->calls[] = ['users' => array_values($allowed), 'message' => $message];

        $devices = PushDevice::query()->whereIn('user_id', $allowed)->where('active', true)->count();

        return new PushDispatchSummary(
            configured: $this->configured,
            users: count($ids),
            skippedByPreference: count($ids) - count($allowed),
            devices: $this->configured ? $devices : 0,
            batches: $devices > 0 ? 1 : 0,
        );
    }

    /** @return list<int> */
    public function pushedUserIds(): array
    {
        return array_values(array_unique(array_merge([], ...array_map(fn ($call) => $call['users'], $this->calls))));
    }
}
