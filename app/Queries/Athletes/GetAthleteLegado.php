<?php

namespace App\Queries\Athletes;

use App\Enums\AthleteEventMediaType;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * "MI LEGADO" (product UX consolidation brief §3-§6/§12-§14) — one card
 * per participation instead of the three separate menus ("Mis eventos",
 * "Mis medallas", "Mis Legacy Plates") it replaces in navigation. Queries
 * EventParticipant directly with targeted eager loads/counts per row
 * rather than going through the paginated, filtered GetAthleteHistory
 * (brief §27-§28) — Mi Legado's card gallery genuinely needs a different
 * shape (a medal's photo, not just a count; every participation, not one
 * filtered page of them), so reusing that Query here would mean either
 * pagination this screen was never meant to have or re-fetching the
 * unbounded collections that Query no longer keeps.
 */
class GetAthleteLegado
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Athlete $athlete): array
    {
        $participants = $athlete->eventParticipations()
            ->with(['eventEdition.event', 'eventRace', 'result', 'medals.images', 'plates'])
            ->withCount([
                'media as photo_count' => fn ($q) => $q->where('type', AthleteEventMediaType::Image->value),
                'media as video_count' => fn ($q) => $q->where('type', AthleteEventMediaType::Video->value),
            ])
            ->orderByDesc('created_at')
            ->get();

        $entitlements = LegacyPlateEntitlement::query()
            ->where('athlete_id', $athlete->id)
            ->get()
            ->groupBy('event_participant_id');

        return $participants
            ->map(fn (EventParticipant $participant) => $this->card(
                $participant,
                $entitlements->get($participant->id, new Collection)->first(),
            ))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function card(EventParticipant $participant, ?LegacyPlateEntitlement $entitlement): array
    {
        $medal = $participant->medals->first();
        $medalImage = $medal?->images->sortBy('sort_order')->first();
        $plate = $participant->plates->first();

        return [
            'id' => $participant->id,
            'event' => $participant->eventEdition?->event?->name,
            'edition' => $participant->eventEdition?->name,
            'race' => $participant->eventRace?->name,
            'bib_number' => $participant->bib_number,
            'event_date' => $participant->eventEdition?->event_date?->toDateString(),
            'official_time' => $participant->result?->official_time,
            'pace' => $participant->result?->pace,
            'position' => $participant->result?->overall_position,
            'image_url' => $medalImage
                ? Storage::disk('public')->url($medalImage->optimized_path ?? $medalImage->original_path)
                : null,
            'has_medal' => $participant->medals->isNotEmpty(),
            'has_legacy_plate' => $plate !== null || $entitlement !== null,
            'legacy_plate_status' => $plate?->status->value ?? $entitlement?->status->value,
            'photo_count' => $participant->photo_count,
            'video_count' => $participant->video_count,
        ];
    }
}
