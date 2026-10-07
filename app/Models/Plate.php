<?php

namespace App\Models;

use App\Enums\PlateGenerationMode;
use App\Enums\PlateLayoutType;
use App\Enums\PlateStatus;
use Database\Factories\PlateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'user_id', 'athlete_id', 'medal_id', 'event_edition_id', 'event_participant_id', 'plate_template_id',
    'plate_template_version_id', 'legacy_plate_model_id', 'legacy_code_id', 'serial_number', 'generation_mode',
    'athlete_name', 'engraving_display_name', 'bib_number', 'event_name', 'race_name', 'official_time', 'pace',
    'event_date', 'dynamic_fields', 'layout_type', 'layout_version', 'layout_snapshot', 'status', 'linked_at', 'produced_at', 'delivered_at',
])]
class Plate extends Model
{
    /** @use HasFactory<PlateFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'generation_mode' => PlateGenerationMode::class,
            'status' => PlateStatus::class,
            'layout_type' => PlateLayoutType::class,
            'event_date' => 'date',
            'dynamic_fields' => 'array',
            'layout_snapshot' => 'array',
            'linked_at' => 'datetime',
            'produced_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * What LegacyPlateViewer draws for THIS plate: the layout frozen when
     * it was produced (layout_snapshot — historical v2 plates keep their
     * printed back here) or, for plates without one, the live layout.
     *
     * @return array<string, mixed>|null
     */
    public function layoutViewer(): ?array
    {
        $snapshot = $this->layout_snapshot;

        if (is_array($snapshot) && $snapshot !== []) {
            $disk = Storage::disk('public');

            return [
                ...$snapshot,
                'preview_image_url' => null,
                'front_artwork_url' => ! empty($snapshot['front_artwork_path']) ? $disk->url($snapshot['front_artwork_path']) : null,
                'back_artwork_url' => ! empty($snapshot['back_artwork_path']) ? $disk->url($snapshot['back_artwork_path']) : null,
            ];
        }

        $model = $this->legacyPlateModel;

        if ($model === null) {
            return null;
        }

        $model->loadMissing('fields');

        return $model->toViewerArray();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set from `eventParticipant.athlete_id` at generation time for an
     * integrated plate; null on a quick plate until its Legacy Code is
     * claimed — see docs/adr/0004-athlete-canonical-identity.md §Plate.
     *
     * @return BelongsTo<Athlete, $this>
     */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /** @return BelongsTo<Medal, $this> */
    public function medal(): BelongsTo
    {
        return $this->belongsTo(Medal::class);
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

    /** @return BelongsTo<PlateTemplate, $this> */
    public function plateTemplate(): BelongsTo
    {
        return $this->belongsTo(PlateTemplate::class);
    }

    /** @return BelongsTo<PlateTemplateVersion, $this> */
    public function plateTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(PlateTemplateVersion::class);
    }

    /**
     * Null for every Plate rendered by the historical PlateTemplate
     * pipeline (`layout_type = legacy_template`) — only set for Legacy
     * Plate v2's pre-manufactured + dynamic-engraving-only pipeline.
     *
     * @return BelongsTo<LegacyPlateModel, $this>
     */
    public function legacyPlateModel(): BelongsTo
    {
        return $this->belongsTo(LegacyPlateModel::class);
    }

    /** @return BelongsTo<LegacyCode, $this> */
    public function legacyCode(): BelongsTo
    {
        return $this->belongsTo(LegacyCode::class);
    }

    /** @return HasMany<ProductionJob, $this> */
    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class);
    }

    /** @return HasOne<ProductionJob, $this> */
    public function latestProductionJob(): HasOne
    {
        return $this->hasOne(ProductionJob::class)->latestOfMany();
    }

    /** @return HasMany<PlateReprint, $this> */
    public function reprints(): HasMany
    {
        return $this->hasMany(PlateReprint::class);
    }

    /** @return HasMany<EventIncident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(EventIncident::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logExcept(['layout_snapshot'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
