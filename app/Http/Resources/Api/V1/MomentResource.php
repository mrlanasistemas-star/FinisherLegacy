<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LegacyMoment;
use App\Models\LegacyMomentMedia;
use App\Models\LegacyMomentReaction;
use App\Support\RaceDistanceFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A Legacy Moment as the feed/detail renders it. Everything linked is
 * eager-loaded by App\Queries\Social\MomentQuery (no N+1): author, media,
 * activity (event/race/result), medal, gear, reaction/comment counts and
 * the viewer's own reactions.
 *
 * @mixin LegacyMoment
 */
class MomentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $participant = $this->eventParticipant;
        $result = $participant?->result;
        $medal = $this->medal;
        $medalFront = $medal?->images?->firstWhere('type', 'front');

        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'caption' => $this->caption,
            'visibility' => $this->visibility->value,
            'created_at' => $this->created_at->toIso8601String(),
            'is_owner' => $viewer !== null && $viewer->id === $this->user_id,
            'author' => new AthleteResource($this->author),
            'activity' => $participant === null ? null : [
                'participant_id' => $viewer !== null && $viewer->id === $this->user_id ? $participant->id : null,
                'event' => $participant->eventEdition?->event?->name,
                'event_slug' => $participant->eventEdition?->event?->slug,
                'edition' => $participant->eventEdition?->name,
                'event_date' => $participant->eventEdition?->event_date?->toDateString(),
                'race' => $participant->eventRace?->name,
                'distance' => RaceDistanceFormatter::format($participant->eventRace),
                'official_time' => $result?->official_time,
                'pace' => $result?->pace,
                'overall_position' => $result?->overall_position,
            ],
            'medal' => $medal === null ? null : [
                'uuid' => $medal->uuid,
                'title' => $medal->title,
                'image_url' => $medalFront?->thumbnail_path ? Storage::disk('public')->url($medalFront->thumbnail_path) : null,
            ],
            'gear' => $this->gear === null ? null : [
                'uuid' => $this->gear->uuid,
                'product_name' => $this->gear->product?->name,
            ],
            'metrics' => $this->metrics === null ? null : [
                'title' => $this->metrics['title'] ?? null,
                'distance_km' => isset($this->metrics['distance_km']) ? (float) $this->metrics['distance_km'] : null,
                'duration_seconds' => isset($this->metrics['duration_seconds']) ? (int) $this->metrics['duration_seconds'] : null,
                'pace' => $this->metrics['pace'] ?? null,
                'is_personal_record' => (bool) ($this->metrics['is_personal_record'] ?? false),
            ],
            'media' => $this->media->map(fn (LegacyMomentMedia $item) => [
                'type' => $item->athlete_event_media_id !== null ? $item->eventMedia?->type->value : $item->type,
                'url' => $item->url(),
                'width' => $item->athlete_event_media_id !== null ? $item->eventMedia?->width : $item->width,
                'height' => $item->athlete_event_media_id !== null ? $item->eventMedia?->height : $item->height,
            ])->filter(fn (array $item) => $item['url'] !== null)->values(),
            'reactions' => [
                'like' => (int) ($this->likes_count ?? 0),
                'cheer' => (int) ($this->cheers_count ?? 0),
            ],
            'my_reactions' => $this->relationLoaded('viewerReactions')
                ? $this->viewerReactions->map(fn (LegacyMomentReaction $r) => $r->type->value)->values()
                : [],
            'comments_count' => (int) ($this->comments_count ?? 0),
        ];
    }
}
