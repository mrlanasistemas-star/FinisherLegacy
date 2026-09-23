<?php

namespace App\Models;

use Database\Factories\EventRaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['event_edition_id', 'name', 'distance_value', 'distance_unit', 'race_type', 'start_time', 'active'])]
class EventRace extends Model
{
    /** @use HasFactory<EventRaceFactory> */
    use HasFactory;

    /**
     * `uuid` is the race's public identifier for API clients — never the
     * integer PK (see the 2026_09_23 migration).
     */
    protected static function booted(): void
    {
        static::creating(function (EventRace $race) {
            $race->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'distance_value' => 'decimal:3',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<EventEdition, $this> */
    public function eventEdition(): BelongsTo
    {
        return $this->belongsTo(EventEdition::class);
    }

    /** @return HasMany<EventParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /** @return HasMany<EventPreregistration, $this> */
    public function preregistrations(): HasMany
    {
        return $this->hasMany(EventPreregistration::class);
    }
}
