<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base for every realtime "something changed" hint (docs/ARCHITECTURE.md
 * §Realtime). Conventions every subclass follows:
 *
 *  - `NAME` is the dotted broadcastAs() name (order.updated, …). Clients
 *    listen with a leading dot: `.listen('.order.updated', …)`.
 *  - Payload is small, contains ids/uuids + statuses only, never PII, never
 *    money secrets. Clients re-fetch REST/Inertia data when they need more.
 *  - Dispatched after the DB transaction commits, and queued (ShouldBroadcast),
 *    so a slow/down Reverb never blocks or rolls back a business write.
 *  - Works with BROADCAST_CONNECTION=null|log (no-op / logged).
 */
abstract class RealtimeEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const string NAME = '';

    public function broadcastAs(): string
    {
        return static::NAME;
    }

    public function broadcastQueue(): string
    {
        return (string) config('queue.names.realtime', 'default');
    }

    /**
     * Private channel of one user — the same one Laravel notifications use.
     */
    protected function userChannel(int|string $userId): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$userId);
    }

    protected function adminCommerceChannel(): PrivateChannel
    {
        return new PrivateChannel('admin.commerce');
    }
}
