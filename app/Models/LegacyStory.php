<?php

namespace App\Models;

use App\Contracts\Media\OwnsMediaFiles;
use App\Enums\StoryVisibility;
use Database\Factories\LegacyStoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The memory a Finisher Legacy Tag opens (docs/architecture/commerce-os.md §Story).
 * Owned by the athlete; Finisher Legacy never publishes it on its own.
 *
 * Race data comes from a real participation when there is one
 * (event_participant_id) — the free fields only describe races we have no
 * results for. Who can open it: App\Policies\LegacyStoryPolicy.
 */
#[Fillable([
    'uuid', 'public_id', 'athlete_id', 'legacy_tag_id', 'event_participant_id', 'event_edition_id', 'title',
    'event_name', 'distance', 'result_time', 'race_date', 'location', 'recipient_label', 'dedication', 'body',
    'external_url', 'visibility', 'completed_at',
])]
class LegacyStory extends Model implements OwnsMediaFiles
{
    /** @use HasFactory<LegacyStoryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'visibility' => StoryVisibility::class,
            'race_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return BelongsTo<LegacyTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(LegacyTag::class, 'legacy_tag_id');
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function participation(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    /** @return BelongsTo<EventEdition, $this> */
    public function eventEdition(): BelongsTo
    {
        return $this->belongsTo(EventEdition::class);
    }

    /** @return BelongsToMany<MediaFile, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaFile::class, 'legacy_story_media')
            ->withPivot(['id', 'role', 'caption', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /** @return HasMany<LegacyStoryShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(LegacyStoryShare::class);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $user->athlete()->value('id') === $this->athlete_id;
    }

    public function publicUrl(): string
    {
        return route('legacy.story.show', $this->public_id);
    }

    /**
     * Without a signed URL only the owner may fetch a story file; every
     * other viewer gets short-lived signed URLs minted after
     * LegacyStoryPolicy::view (or a valid recipient share link).
     */
    public function canViewMedia(?User $viewer, MediaFile $file): bool
    {
        return $this->isOwnedBy($viewer);
    }

    /** Only public stories get long-lived, cacheable media URLs. */
    public function mediaIsPublic(MediaFile $file): bool
    {
        return $this->visibility === StoryVisibility::Public;
    }

    public function syncFromMediaFile(MediaFile $file): void
    {
        // Nothing denormalised: the pivot points at the file itself.
    }
}
