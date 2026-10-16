<?php

namespace App\Actions\Tracking;

use App\Enums\TrackingDeviceStatus;
use App\Models\TrackingDevice;
use Illuminate\Support\Str;

/**
 * Device side of pairing (gps_band contract, SPEC §76/§78 — no firmware
 * here). The band submits the code the athlete generated and receives a
 * device token exactly once (stored as SHA-256). From then on it sends
 * `X-Tracking-Device-Token: <token>` to start a session for its
 * participation and to post points.
 *
 * Returns null for an unknown / expired / already used code.
 */
class PairTrackingDevice
{
    /**
     * @return array{device: TrackingDevice, token: string}|null
     */
    public function handle(string $code): ?array
    {
        $device = TrackingDevice::query()
            ->where('pairing_code_hash', hash('sha256', strtoupper(trim($code))))
            ->where('status', TrackingDeviceStatus::Pending)
            ->where('pairing_expires_at', '>', now())
            ->first();

        if ($device === null) {
            return null;
        }

        $token = Str::random(64);

        $device->update([
            'status' => TrackingDeviceStatus::Paired,
            'token_hash' => hash('sha256', $token),
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'paired_at' => now(),
            'last_seen_at' => now(),
        ]);

        return ['device' => $device, 'token' => $token];
    }
}
