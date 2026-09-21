<?php

namespace App\Models;

use App\Enums\SupportActivityType;
use App\Enums\SupportSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "MI EQUIPO DE APOYO" (product UX consolidation brief §32-§35) — a link/QR
 * an Athlete shares with family/friends for one activity (a race, a
 * training run, or free-form). `public_code` is the only thing exposed
 * publicly — never the athlete's internal id.
 */
#[Fillable([
    'uuid', 'athlete_id', 'event_participant_id', 'event_edition_id', 'title', 'description',
    'activity_type', 'target_distance_meters', 'public_code', 'status', 'opens_at', 'closes_at',
    'allow_text', 'allow_audio', 'auto_approve',
])]
class AthleteSupportSession extends Model
{
    protected function casts(): array
    {
        return [
            'activity_type' => SupportActivityType::class,
            'status' => SupportSessionStatus::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'allow_text' => 'boolean',
            'allow_audio' => 'boolean',
            'auto_approve' => 'boolean',
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

    /** @return BelongsTo<EventEdition, $this> */
    public function eventEdition(): BelongsTo
    {
        return $this->belongsTo(EventEdition::class);
    }

    /** @return HasMany<SupportContributor, $this> */
    public function contributors(): HasMany
    {
        return $this->hasMany(SupportContributor::class);
    }

    /** @return HasMany<AthleteSupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AthleteSupportMessage::class);
    }

    public function isAcceptingMessages(): bool
    {
        if (! in_array($this->status, [SupportSessionStatus::Open, SupportSessionStatus::Active], true)) {
            return false;
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }
}
