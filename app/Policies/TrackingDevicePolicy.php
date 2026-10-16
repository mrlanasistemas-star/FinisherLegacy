<?php

namespace App\Policies;

use App\Models\TrackingDevice;
use App\Models\User;

class TrackingDevicePolicy
{
    public function delete(User $user, TrackingDevice $device): bool
    {
        $athleteId = $user->athlete()->value('id');

        return $athleteId !== null && $device->athlete_id === $athleteId;
    }
}
