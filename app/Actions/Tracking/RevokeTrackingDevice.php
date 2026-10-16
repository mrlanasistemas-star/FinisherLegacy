<?php

namespace App\Actions\Tracking;

use App\Enums\TrackingDeviceStatus;
use App\Models\TrackingDevice;

/** Disconnects a device: its token stops working immediately. */
class RevokeTrackingDevice
{
    public function handle(TrackingDevice $device): TrackingDevice
    {
        $device->update([
            'status' => TrackingDeviceStatus::Revoked,
            'token_hash' => null,
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
        ]);

        return $device;
    }
}
