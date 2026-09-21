<?php

namespace App\Models;

use App\Enums\AthleteEventMediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * Belongs to Athlete + EventParticipant, never generically to User (brief
 * §41/§94-§95). Limits/mime rules live in config('finisher.event_media'),
 * never hardcoded here.
 */
#[Fillable([
    'uuid', 'athlete_id', 'event_participant_id', 'type', 'disk', 'path', 'mime',
    'size_bytes', 'checksum', 'width', 'height', 'duration_seconds', 'is_public', 'sort_order',
])]
class AthleteEventMedia extends Model
{
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

    /**
     * Never a direct disk URL (brief §62) — public media gets a permanent
     * link to App\Http\Controllers\AthleteEventMediaFileController, which
     * serves it unconditionally; private media gets a signed, expiring
     * one, same as AthleteSupportMessage::signedAudioUrl().
     */
    public function url(): string
    {
        if ($this->is_public) {
            return URL::route('athlete-media.show', ['media' => $this->uuid]);
        }

        return URL::temporarySignedRoute('athlete-media.show', now()->addHours(2), ['media' => $this->uuid]);
    }
}
