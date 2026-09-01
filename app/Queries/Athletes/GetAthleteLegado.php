<?php

namespace App\Queries\Athletes;

use App\Enums\AthleteEventMediaType;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;
use App\Models\Medal;
use App\Models\Plate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * "MI LEGADO" (product UX consolidation brief §3-§6) — one card per
 * participation instead of the three separate menus ("Mis eventos",
 * "Mis medallas", "Mis Legacy Plates") it replaces in navigation. Builds on
 * GetAthleteHistory rather than re-querying: same participations/plates/
 * medals/media this Athlete already has, just grouped by
 * event_participant_id so one card can show its medal photo, its Legacy
 * Plate status, and its media count together instead of three empty-until-
 * you-click-through screens.
 */
class GetAthleteLegado
{
    public function __construct(private readonly GetAthleteHistory $history) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Athlete $athlete): array
    {
        $data = $this->history->handle($athlete);

        $plates = $data['plates']->groupBy('event_participant_id');
        $medals = $data['medals']->loadMissing('images')->groupBy('event_participant_id');
        $media = $data['media']->groupBy('event_participant_id');

        $entitlements = LegacyPlateEntitlement::query()
            ->where('athlete_id', $athlete->id)
            ->get()
            ->groupBy('event_participant_id');

        return $data['participations']
            ->map(fn (EventParticipant $participant) => $this->card(
                $participant,
                $plates->get($participant->id, new Collection),
                $medals->get($participant->id, new Collection),
                $media->get($participant->id, new Collection),
                $entitlements->get($participant->id, new Collection)->first(),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Plate>  $plates
     * @param  Collection<int, Medal>  $medals
     * @param  Collection<int, AthleteEventMedia>  $media
     * @return array<string, mixed>
     */
    private function card(
        EventParticipant $participant,
        Collection $plates,
        Collection $medals,
        Collection $media,
        ?LegacyPlateEntitlement $entitlement,
    ): array {
        $medalImage = $medals->first()?->images->sortBy('sort_order')->first();
        $plate = $plates->first();

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
            'has_medal' => $medals->isNotEmpty(),
            'has_legacy_plate' => $plate !== null || $entitlement !== null,
            'legacy_plate_status' => $plate?->status->value ?? $entitlement?->status->value,
            'photo_count' => $media->where('type', AthleteEventMediaType::Image)->count(),
            'video_count' => $media->where('type', AthleteEventMediaType::Video)->count(),
        ];
    }
}
