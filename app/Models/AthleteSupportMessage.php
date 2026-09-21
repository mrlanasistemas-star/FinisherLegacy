<?php

namespace App\Models;

use App\Enums\SupportMessageStatus;
use App\Enums\SupportMessageType;
use App\Enums\SupportTriggerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'uuid', 'athlete_support_session_id', 'support_contributor_id', 'type', 'message_text',
    'audio_disk', 'audio_path', 'audio_mime', 'audio_size_bytes', 'audio_duration_seconds',
    'trigger_type', 'trigger_distance_meters', 'status', 'is_surprise', 'approved_at', 'consumed_at',
])]
class AthleteSupportMessage extends Model
{
    protected function casts(): array
    {
        return [
            'type' => SupportMessageType::class,
            'trigger_type' => SupportTriggerType::class,
            'status' => SupportMessageStatus::class,
            'is_surprise' => 'boolean',
            'approved_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AthleteSupportSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AthleteSupportSession::class, 'athlete_support_session_id');
    }

    /** @return BelongsTo<SupportContributor, $this> */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(SupportContributor::class, 'support_contributor_id');
    }

    /**
     * A private, time-limited download link — audio is never served from a
     * public disk URL (product UX consolidation brief §41).
     */
    public function signedAudioUrl(): ?string
    {
        if ($this->audio_path === null) {
            return null;
        }

        return URL::temporarySignedRoute(
            'support-messages.audio',
            now()->addHours(2),
            ['message' => $this->id],
        );
    }
}
