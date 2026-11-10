<?php

namespace App\Support\LegacyTags;

use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Models\EventParticipant;
use App\Models\LegacyStory;
use App\Models\LegacyStoryShare;
use App\Models\LegacyTag;
use App\Models\MediaFile;
use App\Services\Portrait\PortraitProduct;
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
        'sport',
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
     * The extra race data (bib, name, pace, category, positions, status,
     * elevation, sport) and the `official` map come from StoryRaceData:
     * an official value wins, a field with no official record shows what
     * the athlete typed.
     *
     * @return array<string, mixed>
     */
    public function race(LegacyStory $story): array
    {
        $data = StoryRaceData::for($story);
        $values = $data['values'];
        $status = is_string($values['finish_status']) ? $values['finish_status'] : null;

        return [
            ...$this->raceCore($story),
            'bib_number' => $values['bib_number'],
            'athlete_name' => $values['athlete_display_name'],
            'pace' => $values['pace'],
            'suggested_pace' => $data['suggested_pace'],
            'category' => $values['category'],
            'category_position' => $values['category_position'],
            'gender_position' => $values['gender_position'],
            'finish_status' => $status,
            'finish_status_label' => $status !== null ? (StoryRaceData::finishStatuses()[$status] ?? null) : null,
            'elevation_gain_m' => $values['elevation_gain_m'],
            'sport' => $data['sport']['name'] ?? null,
            'official' => [...$data['official'], 'sport' => $data['sport_official']],
        ];
    }

    /**
     * @return array{source: string, event_name: ?string, edition: ?string, race: ?string, distance: ?string, time: ?string, position: ?string, date: ?string, location: ?string}
     */
    private function raceCore(LegacyStory $story): array
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
                'position' => $result?->overall_position !== null ? (string) $result->overall_position : $story->result_position,
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
            'position' => $story->result_position,
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
        $videos = $this->media($story, StoryMediaRole::Video, $forOwner);
        $audios = $this->media($story, StoryMediaRole::Audio, $forOwner);
        $audio = $audios[0] ?? null;
        $certificate = $this->media($story, StoryMediaRole::Certificate, $forOwner)[0] ?? null;
        $tag = $this->tag($story->tag);
        // Portarretrato inteligente → 'album' (photo-first page, "Álbum" labels); a tag → 'tag'.
        $kind = PortraitProduct::kindFor($story->tag);

        return [
            'public_id' => $story->public_id,
            'title' => $story->title ?: ($race['event_name'] ?? $tag['collection']['name'] ?? ($kind === PortraitProduct::KIND_ALBUM ? __('Mi álbum') : __('Mi Finisher Legacy'))),
            'kind' => $kind,
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
            'videos' => $videos,
            'audio' => $audio,
            'audios' => $audios,
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
                'result_position' => $race['position'],
                'date' => $race['date'],
                'location' => $race['location'],
                'bib_number' => $race['bib_number'],
                'athlete_name' => $race['athlete_name'],
                'pace' => $race['pace'] ?? $race['suggested_pace'],
                'category' => $race['category'],
                'category_position' => $race['category_position'],
                'gender_position' => $race['gender_position'],
                'finish_status' => $race['finish_status'],
                'elevation_gain_m' => $race['elevation_gain_m'],
                'sport' => $race['sport'],
                'official' => $race['official'],
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
            'videos' => array_map(fn (array $v) => [
                'id' => $v['id'],
                'uuid' => $v['uuid'],
                'url' => $v['url'],
                'poster_url' => $v['poster_url'],
                'thumb_url' => $v['thumb_url'],
                'caption' => $v['caption'],
                'width' => $v['width'],
                'height' => $v['height'],
                'duration_seconds' => $v['duration_seconds'],
                'status' => $v['status'],
            ], $view['videos']),
            'audio_url' => $view['audio']['url'] ?? null,
            'audios' => array_map(fn (array $a) => [
                'uuid' => $a['uuid'],
                'url' => $a['url'],
                'duration_seconds' => $a['duration_seconds'],
                'status' => $a['status'],
            ], $view['audios']),
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
     * The story as its owner edits it in the app
     * (GET|PATCH /api/v1/me/stories/{publicId}, docs/api/v1.md). Every race
     * field carries the value that is SHOWN — the official one when the
     * linked participation has it — and `official` says which fields are
     * official, i.e. read-only (a PATCH of those is ignored).
     *
     * @return array<string, mixed>
     */
    public function apiEditableStory(LegacyStory $story): array
    {
        $story->loadMissing(self::STORY_RELATIONS);
        $data = StoryRaceData::for($story);
        $values = $data['values'];
        $cover = $this->media($story, StoryMediaRole::Photo, true)[0] ?? null;

        return [
            'public_id' => $story->public_id,
            'title' => $story->title,
            'event_name' => $values['event_name'],
            'distance' => $values['distance'],
            'result_time' => $values['result_time'],
            'result_position' => $values['result_position'],
            'bib_number' => $values['bib_number'],
            'athlete_display_name' => $values['athlete_display_name'],
            'pace' => $values['pace'],
            'suggested_pace' => $data['suggested_pace'],
            'category' => $values['category'],
            'category_position' => $values['category_position'],
            'gender_position' => $values['gender_position'],
            'finish_status' => $values['finish_status'],
            'elevation_gain_m' => $values['elevation_gain_m'],
            'sport' => $data['sport'],
            'race_date' => $values['race_date'],
            'location' => $values['location'],
            'recipient_label' => $story->recipient_label,
            'dedication' => $story->dedication,
            'body' => $story->body,
            'external_url' => $story->external_url,
            'visibility' => $story->visibility->value,
            'official' => [...$data['official'], 'sport' => $data['sport_official']],
            'race_source' => $story->event_participant_id !== null ? 'participation' : ($story->event_name !== null ? 'manual' : 'none'),
            'share_url' => $story->publicUrl(),
            'cover_url' => $cover !== null && $cover['status'] !== 'processing' && $cover['status'] !== 'failed' ? $cover['thumb_url'] : null,
            'updated_at' => $story->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Photos, videos and voice messages of a story for its owner in the
     * app, in gallery order (GET /api/v1/me/stories/{publicId}/media).
     *
     * @return list<array<string, mixed>>
     */
    public function apiMedia(LegacyStory $story): array
    {
        $story->loadMissing('media');
        $items = [];

        foreach ([StoryMediaRole::Photo, StoryMediaRole::Video, StoryMediaRole::Audio] as $role) {
            foreach ($this->media($story, $role, true) as $item) {
                $items[] = self::apiMediaItem($item);
            }
        }

        usort($items, fn (array $a, array $b) => [$a['sort_order'], $a['role']] <=> [$b['sort_order'], $b['role']]);

        return $items;
    }

    /**
     * One media item of the owner's API. While a file is still in the
     * pipeline (or failed) it has no URL: the app shows its own local
     * preview and polls until `status` is `ready`.
     *
     * @param  array<string, mixed>  $item  an entry of media()
     * @return array<string, mixed>
     */
    public static function apiMediaItem(array $item): array
    {
        $status = match ($item['status']) {
            'ready', 'pending_transcode' => 'ready',
            'failed' => 'failed',
            default => 'processing',
        };
        $ready = $status === 'ready';

        return [
            'uuid' => $item['uuid'],
            'role' => $item['role'],
            'url' => $ready ? $item['url'] : null,
            'thumb_url' => $ready ? $item['thumb_url'] : null,
            'poster_url' => $ready ? $item['poster_url'] : null,
            'duration_seconds' => $item['duration_seconds'],
            'status' => $status,
            'sort_order' => $item['sort_order'],
            'source' => $item['source'],
            'caption' => $item['caption'],
            'width' => $item['width'],
            'height' => $item['height'],
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
            /** @var object{id: int, role: string, source: ?string, photo_sale_id: ?int, caption: ?string, sort_order: int} $pivot */
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
            $isVideo = $file->kind === MediaKind::Video;
            $poster = $servable && $isVideo ? ($file->url('poster_card') ?? $file->url('poster')) : null;
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
                'thumb_url' => $servable ? ($isImage ? ($file->url('card') ?? $file->url()) : ($isVideo ? ($file->url('poster_thumb') ?? $poster) : null)) : ($isImage ? $preview : null),
                'poster_url' => $poster,
                'duration_seconds' => $file->duration_ms !== null ? (int) round($file->duration_ms / 1000) : null,
                'role' => $pivot->role,
                'source' => $pivot->source ?: 'upload',
                'sort_order' => (int) $pivot->sort_order,
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
