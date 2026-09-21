<?php

namespace App\Models;

use App\Enums\EditionStatus;
use App\Enums\OperationMode;
use App\Enums\ResultsStatus;
use Database\Factories\EventEditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'event_id', 'name', 'year', 'event_date', 'city', 'state', 'country', 'timezone',
    'registration_open_at', 'registration_close_at', 'operation_mode', 'status', 'results_status',
    'production_export_format', 'default_dpi', 'data_source_type', 'data_source_provider_connection_id',
    'participants_data_source_id', 'results_data_source_id',
])]
class EventEdition extends Model
{
    /** @use HasFactory<EventEditionFactory> */
    use HasFactory;

    protected $appends = ['phase'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
            'operation_mode' => OperationMode::class,
            'status' => EditionStatus::class,
            'results_status' => ResultsStatus::class,
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Public-facing phase, derived from the event date rather than stored: "upcoming",
     * "ongoing", or "finished". Distinct from `status`, which is the admin lifecycle.
     *
     * @return Attribute<string, never>
     */
    protected function phase(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match (true) {
                $this->event_date->isFuture() => 'upcoming',
                $this->event_date->isToday() => 'ongoing',
                default => 'finished',
            },
        );
    }

    /** @return HasMany<EventRace, $this> */
    public function races(): HasMany
    {
        return $this->hasMany(EventRace::class);
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

    /** @return HasMany<EventImport, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(EventImport::class);
    }

    /** @return HasMany<EventStaffAssignment, $this> */
    public function staffAssignments(): HasMany
    {
        return $this->hasMany(EventStaffAssignment::class);
    }

    /** @return HasMany<Plate, $this> */
    public function plates(): HasMany
    {
        return $this->hasMany(Plate::class);
    }

    /** @return HasMany<ProductionJob, $this> */
    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class);
    }

    /** @return HasMany<EventIncident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(EventIncident::class);
    }

    /** @return HasMany<Medal, $this> */
    public function medals(): HasMany
    {
        return $this->hasMany(Medal::class);
    }

    /** @return HasMany<EventPlateTemplate, $this> */
    public function plateTemplateAssignments(): HasMany
    {
        return $this->hasMany(EventPlateTemplate::class);
    }

    /** @return HasOne<EventProductionCheck, $this> */
    public function productionCheck(): HasOne
    {
        return $this->hasOne(EventProductionCheck::class);
    }

    /**
     * Override of the Organizer's default OrganizerDataSource — null means
     * "inherit from Organizer" (brief §18). Resolved centrally by
     * App\Actions\Integrations\ResolveEventDataSource, never read directly.
     *
     * @return BelongsTo<ProviderConnection, $this>
     */
    public function dataSourceProviderConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class, 'data_source_provider_connection_id');
    }

    /**
     * Specific OrganizerDataSource picked for participants, overriding the
     * Organizer's default-for-participants (brief §28/§39). Null means
     * "inherit".
     *
     * @return BelongsTo<OrganizerDataSource, $this>
     */
    public function participantsDataSource(): BelongsTo
    {
        return $this->belongsTo(OrganizerDataSource::class, 'participants_data_source_id');
    }

    /**
     * Specific OrganizerDataSource picked for results, overriding the
     * Organizer's default-for-results. Null means "inherit".
     *
     * @return BelongsTo<OrganizerDataSource, $this>
     */
    public function resultsDataSource(): BelongsTo
    {
        return $this->belongsTo(OrganizerDataSource::class, 'results_data_source_id');
    }

    /** @return HasMany<LegacyPlateEntitlement, $this> */
    public function legacyPlateEntitlements(): HasMany
    {
        return $this->hasMany(LegacyPlateEntitlement::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The version to use when generating a plate for this edition/race. Race-specific
     * assignment wins over the edition-wide default (21K vs 42K having different molds).
     */
    public function defaultPlateTemplateVersion(?int $eventRaceId = null): ?PlateTemplateVersion
    {
        if ($eventRaceId !== null) {
            $raceSpecific = $this->plateTemplateAssignments()
                ->where('event_race_id', $eventRaceId)
                ->where('active', true)
                ->where('is_default', true)
                ->first();

            if ($raceSpecific) {
                return $raceSpecific->plateTemplateVersion;
            }
        }

        return $this->plateTemplateAssignments()
            ->whereNull('event_race_id')
            ->where('active', true)
            ->where('is_default', true)
            ->first()
            ?->plateTemplateVersion;
    }
}
