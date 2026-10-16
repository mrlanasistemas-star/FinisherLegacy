<?php

namespace App\Models;

use App\Contracts\Media\OwnsMediaFiles;
use App\Enums\MomentVisibility;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

/**
 * One item in a Moment: either a reference to existing event media (never
 * a duplicated file) or a photo uploaded with the Moment. Photos uploaded
 * through the media pipeline own a MediaFile (signed, variant-aware
 * delivery; visible to whoever may see the Moment); older rows keep their
 * public-disk `path`.
 *
 * @property int|null $athlete_event_media_id
 * @property string $type
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $width
 * @property int|null $height
 * @property-read MediaFile|null $mediaFile
 */
#[Fillable(['legacy_moment_id', 'athlete_event_media_id', 'type', 'disk', 'path', 'width', 'height', 'sort_order'])]
class LegacyMomentMedia extends Model implements OwnsMediaFiles
{
    protected $table = 'legacy_moment_media';

    protected $with = ['mediaFile'];

    /** @return BelongsTo<LegacyMoment, $this> */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(LegacyMoment::class, 'legacy_moment_id');
    }

    /** @return BelongsTo<AthleteEventMedia, $this> */
    public function eventMedia(): BelongsTo
    {
        return $this->belongsTo(AthleteEventMedia::class, 'athlete_event_media_id');
    }

    /** @return MorphOne<MediaFile, $this> */
    public function mediaFile(): MorphOne
    {
        return $this->morphOne(MediaFile::class, 'mediable')->chaperone('mediable');
    }

    /** Null while a new photo is still processing (clients skip it). */
    public function url(?string $variant = 'display'): ?string
    {
        if ($this->athlete_event_media_id !== null) {
            return $this->eventMedia?->url();
        }

        if ($this->mediaFile !== null) {
            return $this->mediaFile->url($variant) ?? $this->mediaFile->url();
        }

        return $this->path !== null ? Storage::disk($this->disk ?? 'public')->url($this->path) : null;
    }

    public function canViewMedia(?User $viewer, MediaFile $file): bool
    {
        $moment = $this->moment;

        return $moment !== null && app(SocialVisibility::class)->canViewMoment($moment, $viewer);
    }

    public function mediaIsPublic(MediaFile $file): bool
    {
        return $this->moment?->visibility === MomentVisibility::Public;
    }

    public function syncFromMediaFile(MediaFile $file): void
    {
        $this->forceFill([
            'width' => $file->width ?? $this->width,
            'height' => $file->height ?? $this->height,
        ])->saveQuietly();
    }
}
