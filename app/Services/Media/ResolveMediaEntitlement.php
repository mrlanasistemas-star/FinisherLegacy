<?php

namespace App\Services\Media;

use App\Actions\Media\UploadAthleteEventMedia;
use App\Enums\AthleteEventMediaType;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use App\Models\MediaEntitlement;

/**
 * Media limits per participation (brief §42/§96-§97) — the free tier from
 * config/finisher.php, plus any `media_entitlements` rows (Memory Pack
 * foundation) when `finisher.memory_packs.enabled`. The single source a
 * client reads through `GET me/events/{participant}/media-entitlement`,
 * so the app never hardcodes limits.
 */
class ResolveMediaEntitlement
{
    /**
     * @return array{images: int, videos: int}
     */
    public function limits(?EventParticipant $participant = null): array
    {
        $limits = [
            'images' => (int) config('finisher.event_media.free_images_per_participation'),
            'videos' => (int) config('finisher.event_media.free_videos_per_participation'),
        ];

        if ($participant !== null && config('finisher.memory_packs.enabled')) {
            $extra = MediaEntitlement::query()
                ->where('event_participant_id', $participant->id)
                ->active()
                ->selectRaw('coalesce(sum(extra_images), 0) as images, coalesce(sum(extra_videos), 0) as videos')
                ->first();

            $limits['images'] += (int) ($extra->images ?? 0);
            $limits['videos'] += (int) ($extra->videos ?? 0);
        }

        return $limits;
    }

    /**
     * @return array{images: int, videos: int}
     */
    public function used(EventParticipant $participant): array
    {
        $used = AthleteEventMedia::query()
            ->where('event_participant_id', $participant->id)
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            'images' => (int) ($used[AthleteEventMediaType::Image->value] ?? 0),
            'videos' => (int) ($used[AthleteEventMediaType::Video->value] ?? 0),
        ];
    }

    /**
     * @return array{images: int, videos: int}
     */
    public function remaining(EventParticipant $participant): array
    {
        $limits = $this->limits($participant);
        $used = $this->used($participant);

        return [
            'images' => max(0, $limits['images'] - $used['images']),
            'videos' => max(0, $limits['videos'] - $used['videos']),
        ];
    }

    /**
     * Everything a client needs to validate before uploading.
     *
     * @return array<string, mixed>
     */
    public function summary(EventParticipant $participant): array
    {
        $limits = $this->limits($participant);
        $used = $this->used($participant);

        return [
            'images' => [
                'used' => $used['images'],
                'limit' => $limits['images'],
                'remaining' => max(0, $limits['images'] - $used['images']),
                'max_bytes' => (int) config('finisher.event_media.max_image_bytes'),
                'allowed_mimes' => UploadAthleteEventMedia::IMAGE_MIME_TYPES,
            ],
            'videos' => [
                'used' => $used['videos'],
                'limit' => $limits['videos'],
                'remaining' => max(0, $limits['videos'] - $used['videos']),
                'max_bytes' => (int) config('finisher.event_media.max_video_bytes'),
                'allowed_mimes' => UploadAthleteEventMedia::VIDEO_MIME_TYPES,
            ],
            'memory_packs_enabled' => (bool) config('finisher.memory_packs.enabled'),
        ];
    }
}
