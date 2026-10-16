<?php

namespace App\Actions\Tracking;

use App\Models\TrackingShare;

/** Idempotent — revoking twice keeps the first revocation time. */
class RevokeTrackingShare
{
    public function handle(TrackingShare $share): TrackingShare
    {
        if ($share->revoked_at === null) {
            $share->update(['revoked_at' => now()]);
        }

        return $share;
    }
}
