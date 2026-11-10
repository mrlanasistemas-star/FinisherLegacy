<?php

namespace App\Services;

use App\Enums\MedalImageType;
use App\Enums\ResultStatus;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventRace;
use App\Models\Medal;
use App\Models\MedalImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedalService
{
    public function __construct(private readonly MedalImageService $images) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $galleryImages
     */
    public function create(User $user, array $data, UploadedFile $front, ?UploadedFile $back, array $galleryImages = []): Medal
    {
        $this->ensureMedalQuota($user);
        $this->ensureImageQuota($user, 1 + ($back ? 1 : 0) + count($galleryImages));

        return DB::transaction(function () use ($user, $data, $front, $back, $galleryImages) {
            $attributes = $this->resolveOrigin($user, $data);
            $attributes['story'] = $data['story'] ?? null;
            $attributes['visibility'] = $data['visibility'];
            $attributes['status'] = 'active';

            /** @var Medal $medal */
            $medal = $user->medals()->create($attributes);

            $this->images->store($front, $medal, MedalImageType::Front, 0);

            if ($back) {
                $this->images->store($back, $medal, MedalImageType::Back, 1);
            }

            foreach (array_values($galleryImages) as $index => $galleryImage) {
                $this->images->store($galleryImage, $medal, MedalImageType::Gallery, 2 + $index);
            }

            return $medal->fresh('images');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $newGalleryImages
     */
    public function update(Medal $medal, array $data, ?UploadedFile $front, ?UploadedFile $back, array $newGalleryImages = []): Medal
    {
        if ($newGalleryImages !== []) {
            $this->ensureMedalImageLimit($medal, count($newGalleryImages));
            $this->ensureImageQuota($medal->user, count($newGalleryImages));
        }

        return DB::transaction(function () use ($medal, $data, $front, $back, $newGalleryImages) {
            $isOfficial = $medal->event_participant_id !== null;

            $attributes = [
                'story' => $data['story'] ?? null,
                'visibility' => $data['visibility'],
            ];

            if (! $isOfficial) {
                foreach (['event_date', 'city', 'country', 'distance_label', 'official_time', 'pace'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $attributes[$field] = $data[$field];
                    }
                }

                if (! empty($data['event_name_manual'])) {
                    $attributes['event_name_manual'] = $data['event_name_manual'];
                    $attributes['title'] = $data['event_name_manual'];
                }
            }

            $medal->update($attributes);

            if ($front) {
                $this->replaceImage($medal, MedalImageType::Front, $front, 0);
            }

            if ($back) {
                $this->replaceImage($medal, MedalImageType::Back, $back, 1);
            }

            $nextSortOrder = $medal->images()->where('type', MedalImageType::Gallery)->max('sort_order') + 1;

            foreach (array_values($newGalleryImages) as $index => $galleryImage) {
                $this->images->store($galleryImage, $medal, MedalImageType::Gallery, $nextSortOrder + $index);
            }

            return $medal->fresh('images');
        });
    }

    public function removeGalleryImage(Medal $medal, int $medalImageId): void
    {
        $image = $medal->images()->where('type', MedalImageType::Gallery)->findOrFail($medalImageId);

        $this->images->delete($image);
    }

    public function delete(Medal $medal): void
    {
        // Medal uses SoftDeletes: this always archives (deleted_at is set) and
        // never destroys the row, so Plate/LegacyCode historical links stay intact.
        $medal->delete();
    }

    private function ensureMedalQuota(User $user): void
    {
        $max = (int) config('finisher.quotas.max_medals_per_athlete');

        if ($user->medals()->count() >= $max) {
            throw ValidationException::withMessages([
                'quota' => [__('No pudimos agregar otra medalla porque alcanzaste el límite de :max medallas de esta versión piloto.', ['max' => $max])],
            ]);
        }
    }

    private function ensureImageQuota(User $user, int $incomingCount): void
    {
        if ($incomingCount === 0) {
            return;
        }

        $max = (int) config('finisher.quotas.max_images_per_athlete');
        $current = MedalImage::whereIn('medal_id', $user->medals()->pluck('id'))->count();

        if ($current + $incomingCount > $max) {
            throw ValidationException::withMessages([
                'quota' => [__('No pudimos agregar otra imagen porque alcanzaste el límite de almacenamiento de esta versión piloto.')],
            ]);
        }
    }

    private function ensureMedalImageLimit(Medal $medal, int $incomingCount): void
    {
        $max = (int) config('finisher.medal.max_images_per_medal');
        $current = $medal->images()->count();

        if ($current + $incomingCount > $max) {
            throw ValidationException::withMessages([
                'gallery_images' => [__('Esta medalla puede tener máximo :max imágenes en total.', ['max' => $max])],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveOrigin(User $user, array $data): array
    {
        if ($data['origin'] !== 'registered') {
            return [
                'event_id' => null,
                'event_edition_id' => null,
                'event_race_id' => null,
                'event_participant_id' => null,
                'title' => $data['event_name_manual'],
                'event_name_manual' => $data['event_name_manual'],
                'event_date' => $data['event_date'] ?? null,
                'distance_label' => $data['distance_label'] ?? null,
                'official_time' => $data['official_time'] ?? null,
                'pace' => $data['pace'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
            ];
        }

        $edition = EventEdition::with('event')->findOrFail((int) $data['event_edition_id']);
        $race = EventRace::findOrFail((int) $data['event_race_id']);

        $match = $this->matchParticipant($user, (int) $data['event_race_id']);

        return [
            'event_id' => $edition->event_id,
            'event_edition_id' => $edition->id,
            'event_race_id' => $race->id,
            'event_participant_id' => $match?->id,
            'title' => $edition->event->name,
            'event_name_manual' => null,
            'event_date' => $edition->event_date->toDateString(),
            'distance_label' => $race->name,
            'official_time' => $match?->result?->official_time,
            'pace' => $match?->result?->pace,
            'city' => $edition->city,
            'country' => $edition->country,
        ];
    }

    public function matchParticipant(User $user, int $eventRaceId): ?EventParticipant
    {
        $participant = EventParticipant::query()
            ->where('event_race_id', $eventRaceId)
            ->where('user_id', $user->id)
            ->with('result')
            ->first();

        if (! $participant?->result) {
            return null;
        }

        $verifiedStatuses = [ResultStatus::Finished, ResultStatus::Verified];

        return in_array($participant->result->status, $verifiedStatuses, true) ? $participant : null;
    }

    private function replaceImage(Medal $medal, MedalImageType $type, UploadedFile $file, int $sortOrder): void
    {
        $existing = $medal->images()->where('type', $type)->first();

        if ($existing) {
            $this->images->delete($existing);
        }

        $this->images->store($file, $medal, $type, $sortOrder);
    }
}
