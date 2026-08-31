<?php

namespace App\Queries\Production;

use App\Enums\ProductionJobStatus;
use App\Models\EventEdition;
use App\Models\ProductionJob;
use Illuminate\Database\Eloquent\Collection;

/**
 * Production is always scoped to one event (brief §13/§31/§188) — every
 * Production UI/API selects an EventEdition first, then queries this
 * instead of listing every job in the system.
 */
class GetEventProductionQueue
{
    /**
     * @param  list<ProductionJobStatus>|null  $statuses  Defaults to every non-terminal status.
     * @return Collection<int, ProductionJob>
     */
    public function handle(EventEdition $edition, ?array $statuses = null): Collection
    {
        $statuses ??= [
            ProductionJobStatus::Queued,
            ProductionJobStatus::Assigned,
            ProductionJobStatus::Preparing,
            ProductionJobStatus::EngravingFront,
            ProductionJobStatus::AwaitingFlip,
            ProductionJobStatus::EngravingBack,
            ProductionJobStatus::VerifyingQr,
            ProductionJobStatus::Ready,
        ];

        return ProductionJob::query()
            ->where('event_edition_id', $edition->id)
            ->whereIn('status', $statuses)
            ->with(['plate.athlete', 'plate.eventParticipant', 'plate.legacyPlateModel', 'productionDevice'])
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->get();
    }
}
