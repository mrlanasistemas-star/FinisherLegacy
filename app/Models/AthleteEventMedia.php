<?php

namespace App\Models;

use App\Contracts\Media\OwnsMediaFiles;
use App\Contracts\Privacy\PinProtectable;
use App\Enums\AthleteEventMediaType;
use App\Enums\MediaFileStatus;
use App\Models\Concerns\PinProtection;
use App\Services\Media\MediaUrlGenerator;
use App\Services\Privacy\ContentPin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\URL;

/**
 * Belongs to Athlete + EventParticipant, never generically to User (brief
 * §41/§94-§95). Limits/mime rules live in config('finisher.event_media'),
 * never hardcoded here.
 *
 * Uploads since the media pipeline (docs/SYNOLOGY.md) own a MediaFile
 * (variants, status, signed delivery through MediaFileController). Older
 * rows without one keep being served from `disk`/`path` by
 * AthleteEventMediaFileController.
 *
 * @property-read MediaFile|null $mediaFile
 */
#[Fillable([
    'uuid', 'athlete_id', 'event_participant_id', 'type', 'disk', 'path', 'mime',
    'size_bytes', 'checksum', 'width', 'height', 'duration_seconds', 'is_public', 'sort_order',
])]
class AthleteEventMedia extends Model implements OwnsMediaFiles, PinProtectable
{
    use PinProtection;

    /** One extra query per list — never one per item (url() needs it). */
    protected $with = ['mediaFile'];

    protected function casts(): array
    {
        return [
            'type' => AthleteEventMediaType::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    /** @return MorphOne<MediaFile, $this> */
    public function mediaFile(): MorphOne
    {
        return $this->morphOne(MediaFile::class, 'mediable')->chaperone('mediable');
    }

    /**
     * Never a direct disk URL (brief §62). Pipeline media: a signed URL to
     * the requested rendition (`thumb`/`card`/`display`, `poster`…) or the
     * primary file. Legacy rows: public media gets a permanent link to
     * AthleteEventMediaFileController, private media a signed, expiring one.
     */
    public function url(?string $variant = null): string
    {
        $file = $this->mediaFile;

        // PIN-protected (App\Services\Privacy\ContentPin): while locked only a
        // URL both media controllers refuse (no `pin` marker); unlocked, a
        // short-lived one carrying it. API resources return null instead.
        if ($this->isPinProtected()) {
            if ($file !== null && app(ContentPin::class)->reveals($this)) {
                return $file->url($variant) ?? $file->url() ?? app(MediaUrlGenerator::class)->url($file, 'default', false);
            }

            return URL::temporarySignedRoute('athlete-media.show', now()->addMinutes(ContentPin::MEDIA_URL_TTL_MINUTES), array_filter([
                'media' => $this->uuid,
                'pin' => app(ContentPin::class)->reveals($this) ? 1 : null,
            ]));
        }

        if ($file !== null) {
            return $file->url($variant) ?? $file->url() ?? app(MediaUrlGenerator::class)->url($file, 'default', $this->is_public);
        }

        if ($this->is_public) {
            return URL::route('athlete-media.show', ['media' => $this->uuid]);
        }

        return URL::temporarySignedRoute('athlete-media.show', now()->addHours(2), ['media' => $this->uuid]);
    }

    /**
     * Rows a non-owner can actually load right now (legacy rows, or
     * pipeline media that is ready / pending_transcode). For public lists.
     *
     * @param  Builder<AthleteEventMedia>  $query
     * @return Builder<AthleteEventMedia>
     */
    public function scopeServable(Builder $query): Builder
    {
        return $query->whereDoesntHave('mediaFile', fn (Builder $file) => $file->whereNotIn('status', [
            MediaFileStatus::Ready->value,
            MediaFileStatus::PendingTranscode->value,
        ]));
    }

    /** processing | ready | pending_transcode | failed (legacy rows: ready). */
    public function processingStatus(): string
    {
        return $this->mediaFile?->status->value ?? 'ready';
    }

    public function canViewMedia(?User $viewer, MediaFile $file): bool
    {
        if ($this->isPinProtected()) {
            return $viewer !== null && $this->isPinOwnedBy($viewer) && app(ContentPin::class)->reveals($this);
        }

        return $this->is_public || ($viewer !== null && $viewer->athlete?->id === $this->athlete_id);
    }

    public function mediaIsPublic(MediaFile $file): bool
    {
        return (bool) $this->is_public && ! $this->isPinProtected();
    }

    public function isPinOwnedBy(User $user): bool
    {
        return $user->athlete?->id === $this->athlete_id;
    }

    /** A PIN-protected photo/video is never public (App\Models\Concerns\PinProtection). */
    protected function applyPinPrivacy(): void
    {
        $this->is_public = false;
    }

    public function syncFromMediaFile(MediaFile $file): void
    {
        $this->forceFill([
            'disk' => $file->disk,
            'path' => $file->path ?? $this->path,
            'width' => $file->width ?? $this->width,
            'height' => $file->height ?? $this->height,
            'duration_seconds' => $file->duration_ms !== null ? (int) round($file->duration_ms / 1000) : $this->duration_seconds,
        ])->saveQuietly();
    }
}
