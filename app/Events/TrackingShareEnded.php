<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Last message ever sent on a share's public channel `live.{channel_key}`
 * (docs/renovacion/familia.md): the share was revoked, or ended with its
 * session. Open live pages drop the map and reload — the server answers
 * 404/410 from then on, and IngestTrackingPoints no longer fans out to it
 * (it only reads usable shares). Carries no data at all.
 */
class TrackingShareEnded implements ShouldBroadcast
{
    use Dispatchable;

    /**
     * @param  list<string>  $channelKeys
     * @param  'revoked'|'ended'  $reason
     */
    public function __construct(
        public readonly array $channelKeys,
        public readonly string $reason,
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return array_map(fn (string $key) => new Channel('live.'.$key), $this->channelKeys);
    }

    public function broadcastAs(): string
    {
        return 'tracking.ended';
    }

    /** @return array{reason: string} */
    public function broadcastWith(): array
    {
        return ['reason' => $this->reason];
    }
}
