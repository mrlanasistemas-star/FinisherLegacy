<?php

namespace App\Actions\Social;

use App\Enums\MomentType;
use App\Enums\MomentVisibility;
use App\Exceptions\MomentReferenceInvalidException;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventParticipant;
use App\Models\LegacyMoment;
use App\Models\Medal;
use App\Models\User;
use App\Services\ImageProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Publishes a Legacy Moment. Never auto-called by the system — the app
 * offers "Compartir como Legacy Moment" after a race/medal/upload and the
 * athlete decides (no automatic spam). Every linked record must belong to
 * the author; linked event media is referenced, never copied.
 */
class CreateMoment
{
    public function __construct(private readonly ImageProcessingService $images) {}

    /**
     * @param  array<string, mixed>  $data  Validated StoreMomentRequest input: type, visibility, caption?,
     *                                      event_participant_id?, medal_uuid?, gear_uuid?, event_media_uuids?, metrics?
     * @param  list<UploadedFile>  $photos
     */
    public function handle(User $author, array $data, array $photos = []): LegacyMoment
    {
        $athlete = $author->athlete()->first();
        $type = MomentType::from((string) $data['type']);
        $visibility = MomentVisibility::from((string) $data['visibility']);

        $participant = null;
        if (! empty($data['event_participant_id']) && is_numeric($data['event_participant_id'])) {
            $participant = EventParticipant::query()->find((int) $data['event_participant_id']);
            if ($participant === null || $athlete === null || $participant->athlete_id !== $athlete->id) {
                throw new MomentReferenceInvalidException('event_participant_id');
            }
        }

        $medal = null;
        if (! empty($data['medal_uuid'])) {
            $medal = Medal::query()->where('uuid', $data['medal_uuid'])->first();
            if ($medal === null || $medal->user_id !== $author->id) {
                throw new MomentReferenceInvalidException('medal_uuid');
            }
        }

        $gear = null;
        if (! empty($data['gear_uuid'])) {
            $gear = AthleteOwnedProduct::query()->where('uuid', $data['gear_uuid'])->first();
            if ($gear === null || $athlete === null || $gear->athlete_id !== $athlete->id) {
                throw new MomentReferenceInvalidException('gear_uuid');
            }
        }

        $mediaUuids = array_values(array_unique(array_filter((array) ($data['event_media_uuids'] ?? []), 'is_string')));
        $eventMedia = $mediaUuids === [] ? collect() : AthleteEventMedia::query()->whereIn('uuid', $mediaUuids)->get()->keyBy('uuid');

        if ($eventMedia->count() !== count($mediaUuids) || $eventMedia->contains(fn (AthleteEventMedia $m) => $athlete === null || $m->athlete_id !== $athlete->id)) {
            throw new MomentReferenceInvalidException('event_media_uuids');
        }

        $metrics = $this->normalizeMetrics(is_array($data['metrics'] ?? null) ? $data['metrics'] : null);
        $caption = isset($data['caption']) && is_string($data['caption']) ? trim($data['caption']) : null;

        if (($caption === null || $caption === '') && $participant === null && $medal === null && $gear === null
            && $mediaUuids === [] && $photos === [] && $metrics === null) {
            throw ValidationException::withMessages(['caption' => 'Escribe algo o agrega una foto o actividad.']);
        }

        // Process uploads before the transaction (no DB lock held during
        // image work); clean them up if the insert fails.
        $stored = [];
        foreach ($photos as $photo) {
            $processed = $this->images->process($photo, "moments/{$author->id}", withThumbnail: false);
            if ($processed['original_path']) {
                Storage::disk('public')->delete($processed['original_path']);
            }
            $stored[] = $processed;
        }

        try {
            return DB::transaction(function () use ($author, $type, $visibility, $caption, $participant, $medal, $gear, $metrics, $mediaUuids, $eventMedia, $stored) {
                $moment = LegacyMoment::create([
                    'user_id' => $author->id,
                    'type' => $type,
                    'caption' => $caption === '' ? null : $caption,
                    'visibility' => $visibility,
                    'event_participant_id' => $participant?->id,
                    'medal_id' => $medal?->id,
                    'athlete_owned_product_id' => $gear?->id,
                    'metrics' => $metrics,
                ]);

                $sort = 0;
                foreach ($mediaUuids as $uuid) {
                    $media = $eventMedia[$uuid];
                    $moment->media()->create([
                        'athlete_event_media_id' => $media->id,
                        'type' => $media->type->value,
                        'sort_order' => $sort++,
                    ]);
                }

                foreach ($stored as $photo) {
                    $moment->media()->create([
                        'type' => 'image',
                        'disk' => 'public',
                        'path' => $photo['display_path'],
                        'width' => $photo['width'],
                        'height' => $photo['height'],
                        'sort_order' => $sort++,
                    ]);
                }

                return $moment;
            });
        } catch (Throwable $e) {
            foreach ($stored as $photo) {
                Storage::disk('public')->delete($photo['display_path']);
            }

            throw $e;
        }
    }

    /**
     * Only the handful of optional numbers a training/PR moment carries.
     * Pace is derived here (never trusted from the client).
     *
     * @param  array<string, mixed>|null  $metrics
     * @return array<string, mixed>|null
     */
    private function normalizeMetrics(?array $metrics): ?array
    {
        if ($metrics === null) {
            return null;
        }

        $distance = isset($metrics['distance_km']) && is_numeric($metrics['distance_km']) ? round((float) $metrics['distance_km'], 2) : null;
        $duration = isset($metrics['duration_seconds']) && is_numeric($metrics['duration_seconds']) ? (int) $metrics['duration_seconds'] : null;
        $title = isset($metrics['title']) && is_string($metrics['title']) && trim($metrics['title']) !== '' ? trim($metrics['title']) : null;
        $isPr = (bool) ($metrics['is_personal_record'] ?? false);

        if ($distance === null && $duration === null && $title === null && ! $isPr) {
            return null;
        }

        $pace = null;
        if ($distance !== null && $distance > 0 && $duration !== null && $duration > 0) {
            $secondsPerKm = (int) round($duration / $distance);
            $pace = sprintf('%d:%02d /km', intdiv($secondsPerKm, 60), $secondsPerKm % 60);
        }

        return array_filter([
            'title' => $title,
            'distance_km' => $distance,
            'duration_seconds' => $duration,
            'pace' => $pace,
            'is_personal_record' => $isPr ?: null,
        ], fn ($value) => $value !== null);
    }
}
