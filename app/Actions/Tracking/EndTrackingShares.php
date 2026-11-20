<?php

namespace App\Actions\Tracking;

use App\Events\TrackingShareEnded;
use App\Models\TrackingShare;
use App\Support\Realtime;
use Illuminate\Database\Eloquent\Builder;

/**
 * Closes many live-tracking shares at once and tells any open live page.
 *
 *  - `revoke()` — the athlete took access back ("Dejar de compartir",
 *    removing a family member): sets `revoked_at` → 404 from then on.
 *  - `end()` — the session stopped and the share was "until it ends":
 *    sets `ended_at` → 410 from then on.
 *
 * Only usable shares are touched (idempotent, keeps the first timestamp).
 * The access rule itself lives in TrackingShare::usable()/isUsable(), so
 * the snapshot, the family API and the realtime fan-out all honour it.
 */
class EndTrackingShares
{
    /** @param  Builder<TrackingShare>  $query */
    public function revoke(Builder $query): int
    {
        return $this->close($query, 'revoked_at', 'revoked');
    }

    /** @param  Builder<TrackingShare>  $query */
    public function end(Builder $query): int
    {
        return $this->close($query, 'ended_at', 'ended');
    }

    /**
     * @param  Builder<TrackingShare>  $query
     * @param  'revoked'|'ended'  $reason
     */
    private function close(Builder $query, string $column, string $reason): int
    {
        $shares = (clone $query)->usable()->get(['id', 'channel_key']);

        if ($shares->isEmpty()) {
            return 0;
        }

        TrackingShare::query()->whereKey($shares->modelKeys())->update([$column => now(), 'updated_at' => now()]);

        if (Realtime::enabled()) {
            TrackingShareEnded::dispatch(array_values($shares->pluck('channel_key')->all()), $reason);
        }

        return $shares->count();
    }
}
