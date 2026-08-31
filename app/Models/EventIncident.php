<?php

namespace App\Models;

use App\Enums\IncidentResolutionType;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Database\Factories\EventIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'event_edition_id', 'event_participant_id', 'plate_id', 'reported_by', 'type',
    'entity_type', 'entity_id', 'description', 'status', 'resolved_by', 'resolved_at',
    'resolution_type', 'resolution_notes', 'before_data', 'after_data',
])]
class EventIncident extends Model
{
    /** @use HasFactory<EventIncidentFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'resolution_type' => IncidentResolutionType::class,
            'before_data' => 'array',
            'after_data' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventEdition, $this> */
    public function eventEdition(): BelongsTo
    {
        return $this->belongsTo(EventEdition::class);
    }

    /** @return BelongsTo<EventParticipant, $this> */
    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    /** @return BelongsTo<Plate, $this> */
    public function plate(): BelongsTo
    {
        return $this->belongsTo(Plate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
