<?php

namespace App\Actions\Support;

use App\Enums\SupportActivityType;
use App\Enums\SupportSessionStatus;
use App\Models\Athlete;
use App\Models\AthleteSupportSession;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Support\CodeGenerator;
use Illuminate\Support\Str;

/**
 * "Crear link de apoyo" (product UX consolidation brief §32-§34) — one
 * session per activity, `public_code` impredecible and never the
 * athlete's internal id (brief §25).
 */
class CreateAthleteSupportSession
{
    public function handle(
        Athlete $athlete,
        string $title,
        SupportActivityType $activityType,
        ?EventParticipant $eventParticipant = null,
        ?EventEdition $eventEdition = null,
        ?string $description = null,
        ?int $targetDistanceMeters = null,
        bool $allowText = true,
        bool $allowAudio = true,
        bool $autoApprove = false,
    ): AthleteSupportSession {
        // Not $eventEdition?->id ?? ...: PHPStan's nullsafe.neverNull check
        // reliably mis-fires on `?->id` specifically (reproduced in
        // isolation, unrelated to this file) — an explicit if/else reads
        // identically and doesn't trip it.
        $eventEditionId = $eventEdition !== null ? $eventEdition->id : $eventParticipant?->event_edition_id;

        return AthleteSupportSession::create([
            'uuid' => (string) Str::uuid(),
            'athlete_id' => $athlete->id,
            'event_participant_id' => $eventParticipant?->id,
            'event_edition_id' => $eventEditionId,
            'title' => $title,
            'description' => $description,
            'activity_type' => $activityType,
            'target_distance_meters' => $targetDistanceMeters,
            'public_code' => CodeGenerator::unique(
                '',
                fn (string $code) => AthleteSupportSession::query()->where('public_code', $code)->exists(),
                length: 10,
            ),
            'status' => SupportSessionStatus::Open,
            'allow_text' => $allowText,
            'allow_audio' => $allowAudio,
            'auto_approve' => $autoApprove,
        ]);
    }
}
