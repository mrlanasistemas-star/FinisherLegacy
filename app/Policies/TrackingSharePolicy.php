<?php

namespace App\Policies;

use App\Models\TrackingShare;
use App\Models\User;

class TrackingSharePolicy
{
    public function delete(User $user, TrackingShare $share): bool
    {
        $participant = $share->eventParticipant()->first(['id', 'athlete_id']);

        return $participant !== null && TrackingSessionPolicy::ownsParticipant($user, $participant);
    }
}
