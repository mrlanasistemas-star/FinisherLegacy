<?php

namespace App\Services\Media;

use App\Enums\AthleteEventMediaType;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;

/**
 * Free-tier limits per participation (brief §42/§96-§97) — always reads
 * config/finisher.php, never a hardcoded number. Returns the free-tier
 * limits today; the return shape is stable so a future storage-
 * subscription entitlement can extend it without changing callers (brief
 * §46/§143: prepared, not built).
 */
class ResolveMediaEntitlement
{
    /**
     * @return array{images: int, videos: int}
     */
    public function limits(): array
    {
        return [
            'images' => (int) config('finisher.event_media.free_images_per_participation'),
            'videos' => (int) config('finisher.event_media.free_videos_per_participation'),
        ];
    }

    /**
     * @return array{images: int, videos: int}
     */
    public function remaining(EventParticipant $participant): array
    {
        $limits = $this->limits();

        $used = AthleteEventMedia::query()
            ->where('event_participant_id', $participant->id)
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            'images' => max(0, $limits['images'] - (int) ($used[AthleteEventMediaType::Image->value] ?? 0)),
            'videos' => max(0, $limits['videos'] - (int) ($used[AthleteEventMediaType::Video->value] ?? 0)),
        ];
    }
}
