<?php

namespace App\Models;

use App\Enums\EventPhotoStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A photographer's event photo for sale. The full-resolution original
 * lives on the private `event_photo_originals` disk and is only delivered
 * to a buyer who paid; the watermarked preview/thumbnail are public.
 *
 * @property int $id
 * @property string $uuid
 * @property int $photographer_profile_id
 * @property int $event_edition_id
 * @property string $original_path
 * @property string $preview_path
 * @property string $thumb_path
 * @property int|null $width
 * @property int|null $height
 * @property int $price_minor
 * @property string $currency
 * @property EventPhotoStatus $status
 * @property list<string>|null $bib_numbers
 * @property Carbon|null $published_at
 */
#[Fillable([
    'uuid', 'photographer_profile_id', 'event_edition_id', 'original_path', 'preview_path', 'thumb_path',
    'width', 'height', 'size_bytes', 'price_minor', 'currency', 'status', 'rejection_reason', 'bib_numbers', 'published_at',
])]
class EventPhoto extends Model
{
    protected function casts(): array
    {
        return [
            'status' => EventPhotoStatus::class,
            'bib_numbers' => 'array',
            'published_at' => 'datetime',
            'price_minor' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function previewUrl(): string
    {
        return Storage::disk('public')->url($this->preview_path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumb_path);
    }

    /** @return BelongsTo<PhotographerProfile, $this> */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_profile_id');
    }

    /** @return BelongsTo<EventEdition, $this> */
    public function eventEdition(): BelongsTo
    {
        return $this->belongsTo(EventEdition::class);
    }

    /** @return HasMany<EventPhotoBib, $this> */
    public function bibs(): HasMany
    {
        return $this->hasMany(EventPhotoBib::class);
    }

    /** @return HasMany<PhotoSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(PhotoSale::class);
    }

    /**
     * Replaces the searchable bib list (normalized: trimmed, no leading '#').
     *
     * @param  list<string>  $bibs
     */
    public function syncBibs(array $bibs): void
    {
        $clean = collect($bibs)
            ->map(fn ($bib) => ltrim(trim((string) $bib), '#'))
            ->filter(fn ($bib) => $bib !== '' && mb_strlen($bib) <= 20)
            ->unique()
            ->values();

        $this->bibs()->delete();
        $this->bibs()->createMany($clean->map(fn ($bib) => [
            'event_edition_id' => $this->event_edition_id,
            'bib_number' => $bib,
        ])->all());
        $this->update(['bib_numbers' => $clean->all()]);
    }
}
