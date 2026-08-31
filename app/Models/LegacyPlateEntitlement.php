<?php

namespace App\Models;

use App\Enums\LegacyPlateEntitlementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The commercial right to a Legacy Plate — separate from ProductionJob,
 * which tracks the physical job (brief §79-§81). Can exist before
 * EventParticipant does (presale); App\Actions\LegacyPlates\
 * LinkLegacyPlateEntitlementToParticipant fills event_participant_id in
 * once a bib appears.
 */
#[Fillable([
    'uuid', 'athlete_id', 'event_edition_id', 'event_participant_id', 'legacy_plate_model_id',
    'order_item_id', 'plate_id', 'status', 'price_type', 'paid_at', 'fulfilled_at',
])]
class LegacyPlateEntitlement extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => LegacyPlateEntitlementStatus::class,
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Athlete, $this> */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
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

    /** @return BelongsTo<LegacyPlateModel, $this> */
    public function legacyPlateModel(): BelongsTo
    {
        return $this->belongsTo(LegacyPlateModel::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Plate, $this> */
    public function plate(): BelongsTo
    {
        return $this->belongsTo(Plate::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [
            LegacyPlateEntitlementStatus::Paid,
            LegacyPlateEntitlementStatus::Linked,
            LegacyPlateEntitlementStatus::Queued,
            LegacyPlateEntitlementStatus::Produced,
            LegacyPlateEntitlementStatus::Delivered,
        ], true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
