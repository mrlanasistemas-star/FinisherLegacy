<?php

namespace App\Enums;

/**
 * Where a tracking session's points come from (SPEC §76). Phase 1 is the
 * athlete's phone; `gps_band` is the contract for a future paired band
 * (device-token auth, see App\Actions\Tracking\PairTrackingDevice) — no
 * firmware lives in this repo.
 */
enum TrackingSource: string
{
    case MobileGps = 'mobile_gps';
    case GpsBand = 'gps_band';

    public function label(): string
    {
        return match ($this) {
            self::MobileGps => 'Teléfono',
            self::GpsBand => 'Dispositivo de seguimiento',
        };
    }
}
