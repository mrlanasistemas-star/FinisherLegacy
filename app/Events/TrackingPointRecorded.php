<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * New position for the family live page (SPEC §83). Broadcast on the
 * public channel `live.{channel_key}` of every usable share of the
 * participation. `channel_key` is a per-share random value distinct from
 * the access token (which is only stored hashed), so a leaked channel name
 * never unlocks the live page, its history or the support messages.
 *
 * Payload is the API contract `{lat,lng,recorded_at,distance_m,battery}`
 * — no names, no ids.
 */
class TrackingPointRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<string>  $channelKeys
     */
    public function __construct(
        public readonly array $channelKeys,
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $recordedAt,
        public readonly int $distanceM,
        public readonly ?int $battery,
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return array_map(fn (string $key) => new Channel('live.'.$key), $this->channelKeys);
    }

    public function broadcastAs(): string
    {
        return 'tracking.point';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'lat' => round($this->lat, 6),
            'lng' => round($this->lng, 6),
            'recorded_at' => $this->recordedAt,
            'distance_m' => $this->distanceM,
            'battery' => $this->battery,
        ];
    }
}
