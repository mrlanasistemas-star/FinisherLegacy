<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * "What the Athlete used in this race" — the join between an
 * App\Models\EventParticipant and an App\Models\AthleteOwnedProduct
 * (product consolidation brief §16-§21). `snapshot` freezes
 * product_name/variant_name/sku/attributes at selection time so this
 * participation's gear history reads correctly even if the catalog entry
 * later changes.
 */
#[Fillable([
    'uuid', 'athlete_id', 'event_participant_id', 'athlete_owned_product_id',
    'snapshot', 'notes', 'selected_at',
])]
class EventGearSelection extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'selected_at' => 'datetime',
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

    /** @return BelongsTo<AthleteOwnedProduct, $this> */
    public function athleteOwnedProduct(): BelongsTo
    {
        return $this->belongsTo(AthleteOwnedProduct::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
