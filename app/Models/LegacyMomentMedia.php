<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One item in a Moment: either a reference to existing event media (never
 * a duplicated file) or a photo uploaded with the Moment.
 *
 * @property int|null $athlete_event_media_id
 * @property string $type
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $width
 * @property int|null $height
 */
#[Fillable(['legacy_moment_id', 'athlete_event_media_id', 'type', 'disk', 'path', 'width', 'height', 'sort_order'])]
class LegacyMomentMedia extends Model
{
    protected $table = 'legacy_moment_media';

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

    public function url(): ?string
    {
        if ($this->athlete_event_media_id !== null) {
            return $this->eventMedia?->url();
        }

        return $this->path !== null ? Storage::disk($this->disk ?? 'public')->url($this->path) : null;
    }
}
