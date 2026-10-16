<?php

namespace App\Actions\Tracking;

use App\Enums\TrackingDeviceStatus;
use App\Enums\TrackingSource;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\TrackingDevice;
use App\Support\CodeGenerator;
use Illuminate\Support\Str;

/**
 * "[Conectar dispositivo]" (SPEC §78), athlete side: generates a short
 * pairing code the athlete types into the band's companion flow. Only the
 * code's SHA-256 is stored; it expires after `pairing_code_minutes`. A
 * participation keeps at most one pending code — asking again replaces it.
 */
class CreateTrackingDevicePairing
{
    /**
     * @return array{device: TrackingDevice, code: string}
     */
    public function handle(Athlete $athlete, EventParticipant $participant, ?string $publicName = null): array
    {
        TrackingDevice::query()
            ->where('event_participant_id', $participant->id)
            ->where('status', TrackingDeviceStatus::Pending)
            ->delete();

        $code = CodeGenerator::unique(
            '',
            fn (string $candidate) => TrackingDevice::query()->where('pairing_code_hash', hash('sha256', $candidate))->exists(),
            length: 8,
        );

        $device = TrackingDevice::create([
            'uuid' => (string) Str::uuid(),
            'type' => TrackingSource::GpsBand,
            'public_name' => $publicName !== null && trim($publicName) !== '' ? trim($publicName) : 'Dispositivo de seguimiento',
            'status' => TrackingDeviceStatus::Pending,
            'athlete_id' => $athlete->id,
            'event_participant_id' => $participant->id,
            'pairing_code_hash' => hash('sha256', $code),
            'pairing_expires_at' => now()->addMinutes((int) config('tracking.pairing_code_minutes', 15)),
        ]);

        return ['device' => $device, 'code' => $code];
    }
}
