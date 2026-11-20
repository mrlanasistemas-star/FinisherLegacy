<?php

namespace App\Actions\Tracking;

use App\Models\TrackingShare;

/**
 * Idempotent — revoking twice keeps the first revocation time. Goes through
 * EndTrackingShares so an open live page is told right away.
 */
class RevokeTrackingShare
{
    public function __construct(private readonly EndTrackingShares $end) {}

    public function handle(TrackingShare $share): TrackingShare
    {
        if ($share->revoked_at === null) {
            $this->end->revoke(TrackingShare::query()->whereKey($share->id));
            $share->refresh();
        }

        return $share;
    }
}
