<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Search index row: "bib X appears in this photo of this edition".
 */
#[Fillable(['event_photo_id', 'event_edition_id', 'bib_number'])]
class EventPhotoBib extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<EventPhoto, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(EventPhoto::class, 'event_photo_id');
    }
}
