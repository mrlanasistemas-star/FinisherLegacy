<?php

namespace App\Support\LegacyTags;

use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Models\EventParticipant;
use App\Models\LegacyStory;
use App\Models\LegacyStoryShare;
use App\Models\LegacyTag;
use App\Models\MediaFile;
use App\Support\RaceDistanceFormatter;

/**
 * One place that turns a tag / story into what the web pages and the API
 * show — so /t, /activar, /legacy, /mi-legacy and /api/v1 never disagree.
 * Media URLs are signed (MediaUrlGenerator) and only minted AFTER the
 * caller authorised the viewer (LegacyStoryPolicy or a share link).
 */
class StoryPresenter
{
    /** Relations every story view needs (one query each, no N+1). */
    public const STORY_RELATIONS = [
        'tag.productVariant.product.collection',
        'participation.eventEdition.event',
        'participation.eventRace',
        'participation.result',
        'eventEdition.event',
        'media',
        'athlete',
    ];

    /**
     * The physical piece: collection + design. No owner, no serial, no PII.
     *
     * @return array<string, mixed>|null
     */
    public function tag(?LegacyTag $tag): ?array
    {
        if ($tag === null) {
            return null;
        }

        $tag->loadMissing('productVariant.product.collection');
        $product = $tag->productVariant?->product;
        $collection = $product?->collection;

        return [
            'product_name' => $product?->name,
            'variant_name' => $tag->productVariant?->name,
            'image_url' => $product?->primaryImageUrl(),
            'collection' => $collection === null ? null : [
                'name' => $collection->name,
                'slug' => $collection->slug,
                'tagline' => $collection->tagline,
                'year' => $collection->year,
                'destination' => $collection->destination,
                'discipline' => $collection->discipline,
                'accent_color' => $collection->accent_color,
                'cover_url' => $collection->coverUrl(),
            ],
        ];
    }

    /**
     * Race data: the real participation when there is one (event, race,
     * official time from results), else what the athlete typed.
     *
     * @return array{source: string, event_name: ?string, edition: ?string, race: ?string, distance: ?string, time: ?string, date: ?string, location: ?string}
     */
    public function race(LegacyStory $story): array
    {
        $participant = $story->participation;

        if ($participant !== null) {
            $edition = $participant->eventEdition;
            $result = $participant->result;

            return [
                'source' => 'participation',
                'event_name' => $edition?->event->name ?? $story->event_name,
                'edition' => $edition?->name,
                'race' => $participant->eventRace?->name,
                'distance' => RaceDistanceFormatter::format($participant->eventRace) ?? $story->distance,
                'time' => $result->official_time ?? $result->chip_time ?? $story->result_time,
                'date' => $edition?->event_date?->toDateString() ?? $story->race_date?->toDateString(),
                'location' => $this->place($edition?->city, $edition?->state, $edition?->country) ?? $story->location,
            ];
        }

        return [
            'source' => $story->event_name !== null ? 'manual' : 'none',
            'event_name' => $story->event_name ?? $story->eventEdition?->event?->name,
            'edition' => $story->eventEdition?->name,
            'race' => null,
            'distance' => $story->distance,
            'time' => $story->result_time,
            'date' => $story->race_date?->toDateString(),
            'location' => $story->location,
        ];
    }

    public function hasRace(LegacyStory $story): bool
    {
        return $story->event_participant_id !== null || filled($story->event_name);
    }

    /**
     * Everything the story page needs. `$forOwner` adds editor-only data
     * (processing previews, shares, the printed code).
     *
     * @return array<string, mixed>
     */
    public function story(LegacyStory $story, bool $forOwner = false): array
    {
        $story->loadMissing(self::STORY_RELATIONS);
        $race = $this->race($story);
        $photos = $this->media($story, StoryMediaRole::Photo, $forOwner);
        $audio = $this->media($story, StoryMediaRole::Audio, $forOwner)[0] ?? null;
        $certificate = $this->media($story, StoryMediaRole::Certificate, $forOwner)[0] ?? null;
        $tag = $this->tag($story->tag);

        return [
            'public_id' => $story->public_id,
            'title' => $story->title ?: ($race['event_name'] ?? $tag['collection']['name'] ?? 'Mi Finisher Legacy'),
            'athlete_first_name' => $story->athlete?->first_name,
            'race' => $race,
            'recipient_label' => $story->recipient_label,
            'dedication' => $story->dedication,
            'body' => $story->body,
            'external_url' => $story->external_url,
            'visibility' => $story->visibility->value,
            'visibility_label' => $story->visibility->label(),
            'is_indexable' => $story->visibility->value === 'public',
            'cover' => $photos[0] ?? null,
            'photos' => $photos,
            'audio' => $audio,
            'certificate' => $certificate,
            'tag' => $tag,
            'display_code' => $forOwner ? $story->tag?->displayCode() : null,
            'year' => (int) ($race['date'] !== null ? substr($race['date'], 0, 4) : $story->created_at?->format('Y')),
            'url' => $story->publicUrl(),
            'created_at' => $story->created_at?->toIso8601String(),
        ];
    }

    /**
     * Stable /api/v1 story resource (docs/api/v1.md §Finisher Legacy Tag).
     * Field names are a contract with the mobile app — add, never rename.
     * `tag.serial`, `tag.display_code` and `shares` only for the owner.
     *
     * @return array<string, mixed>
     */
    public function apiStory(LegacyStory $story, bool $forOwner = false): array
    {
        $view = $this->story($story, $forOwner);
        $race = $view['race'];
        $tag = $view['tag'];

        return [
            'public_id' => $view['public_id'],
            'title' => $view['title'],
            'is_owner' => $forOwner,
            'event' => [
                'source' => $race['source'],
                'name' => $race['event_name'],
                'edition' => $race['edition'],
                'race' => $race['race'],
                'distance' => $race['distance'],
                'result_time' => $race['time'],
                'date' => $race['date'],
                'location' => $race['location'],
            ],
            'dedication' => $view['dedication'],
            'recipient_label' => $view['recipient_label'],
            'body' => $view['body'],
            'external_url' => $view['external_url'],
            'visibility' => $view['visibility'],
            'cover_url' => $view['cover']['url'] ?? null,
            'photos' => array_map(fn (array $p) => [
                'id' => $p['id'],
                'url' => $p['url'],
                'thumb_url' => $p['thumb_url'],
                'caption' => $p['caption'],
                'width' => $p['width'],
                'height' => $p['height'],
                'status' => $p['status'],
            ], $view['photos']),
            'audio_url' => $view['audio']['url'] ?? null,
            'certificate_url' => $view['certificate']['download_url'] ?? null,
            'certificate_mime' => $view['certificate']['mime'] ?? null,
            'tag' => $tag === null ? null : [
                'serial' => $forOwner ? $story->tag?->serial : null,
                'display_code' => $forOwner ? $story->tag?->displayCode() : null,
                'collection' => $tag['collection'],
                'design' => [
                    'product_name' => $tag['product_name'],
                    'variant_name' => $tag['variant_name'],
                    'image_url' => $tag['image_url'],
                ],
            ],
            'web_url' => $view['url'],
            'year' => $view['year'],
            'shares' => $forOwner ? $this->shares($story) : [],
            'created_at' => $view['created_at'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shares(LegacyStory $story): array
    {
        return $story->shares()->latest('id')->get()->map(fn (LegacyStoryShare $share) => [
            'id' => $share->id,
            'label' => $share->label,
            'url' => $share->url(),
            'open_count' => $share->open_count,
            'last_opened_at' => $share->last_opened_at?->toIso8601String(),
            'active' => $share->isActive(),
        ])->values()->all();
    }

    /**
     * A participation as a choice in "¿De qué carrera es este recuerdo?".
     *
     * @return array<string, mixed>
     */
    public function participationOption(EventParticipant $participant): array
    {
        $edition = $participant->eventEdition;

        return [
            'id' => $participant->id,
            'event_name' => $edition?->event?->name,
            'edition' => $edition?->name,
            'race' => $participant->eventRace?->name,
            'distance' => RaceDistanceFormatter::format($participant->eventRace),
            'time' => $participant->result->official_time ?? $participant->result->chip_time ?? null,
            'date' => $edition?->event_date?->toDateString(),
            'location' => $this->place($edition?->city, $edition?->state, null),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function media(LegacyStory $story, StoryMediaRole $role, bool $forOwner = false): array
    {
        $items = [];

        foreach ($story->media as $file) {
            /** @var MediaFile $file */
            /** @var object{id: int, role: string, caption: ?string, sort_order: int} $pivot */
            $pivot = $file->getRelationValue('pivot');

            if ($pivot->role !== $role->value) {
                continue;
            }

            $file->setRelation('mediable', $story);
            $servable = $file->isServable();

            if (! $servable && ! $forOwner) {
                continue;
            }

            $isImage = $file->kind === MediaKind::Image;
            // The uploader may preview a file still in the pipeline through
            // the unsigned route (MediaFileController::uploaderPreview).
            $preview = route('media.file', ['mediaFile' => $file->uuid, 'variant' => 'default']);

            $items[] = [
                'id' => $pivot->id,
                'uuid' => $file->uuid,
                'kind' => $file->kind->value,
                'mime' => $file->mime,
                'status' => $file->status->value,
                'caption' => $pivot->caption,
                'width' => $file->width,
                'height' => $file->height,
                'url' => $servable ? ($isImage ? ($file->url('display') ?? $file->url()) : $file->url()) : $preview,
                'thumb_url' => $servable ? ($isImage ? ($file->url('card') ?? $file->url()) : null) : ($isImage ? $preview : null),
                'download_url' => $servable ? $file->url() : $preview,
                'size_bytes' => $file->size_bytes,
            ];
        }

        return $items;
    }

    private function place(?string $city, ?string $state, ?string $country): ?string
    {
        $parts = array_values(array_filter([$city, $state, $country], fn (?string $p) => filled($p)));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
