<?php

namespace App\Services\Production;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\PlateStatus;
use App\Enums\ProductionJobStatus;
use App\Models\LegacyPlateEntitlement;
use App\Models\Plate;
use App\Models\ProductionJob;

/**
 * Syncs `Plate.status` (the product) whenever a `ProductionJob` (the
 * physical attempt) reaches a status that should move it — never the other
 * way around, and never both state machines mutated by the same code path.
 * See docs/adr/0003-production-state-machine.md §Plate↔Job sync.
 */
class PlateProductionCoordinator
{
    public function sync(ProductionJob $job): void
    {
        $plate = $job->plate;

        match ($job->status) {
            ProductionJobStatus::Assigned,
            ProductionJobStatus::Preparing,
            ProductionJobStatus::EngravingFront,
            ProductionJobStatus::AwaitingFlip,
            ProductionJobStatus::EngravingBack,
            ProductionJobStatus::VerifyingQr => $this->moveTo($plate, PlateStatus::Processing),

            ProductionJobStatus::Ready => $this->moveTo($plate, PlateStatus::Ready, ['produced_at' => $plate->produced_at ?? now()]),

            ProductionJobStatus::Delivered => $this->moveTo($plate, PlateStatus::Delivered, ['delivered_at' => $plate->delivered_at ?? now()]),

            // Queued: the plate is already Queued by the time a job exists
            // (PlateGenerationService creates both together) — nothing to do.
            // Failed: the ATTEMPT failed, not the product — the plate stays
            // exactly where it was (typically Processing) so a human can
            // decide retry vs. reprint; see docs/adr/0003 §Failure.
            ProductionJobStatus::Queued,
            ProductionJobStatus::Failed => null,

            ProductionJobStatus::Cancelled => $this->syncCancelled($job, $plate),
        };

        $this->syncEntitlement($job, $plate);
    }

    /**
     * The commercial right (LegacyPlateEntitlement) mirrors the physical
     * outcome so "Mis Legacy Plates" and Preparación y entrega agree:
     * ready → produced, delivered → delivered. Never moves backwards.
     */
    private function syncEntitlement(ProductionJob $job, Plate $plate): void
    {
        $status = match ($job->status) {
            ProductionJobStatus::Ready => LegacyPlateEntitlementStatus::Produced,
            ProductionJobStatus::Delivered => LegacyPlateEntitlementStatus::Delivered,
            default => null,
        };

        if ($status === null) {
            return;
        }

        LegacyPlateEntitlement::query()
            ->where('plate_id', $plate->id)
            ->whereNotIn('status', [LegacyPlateEntitlementStatus::Cancelled, LegacyPlateEntitlementStatus::Delivered])
            ->get()
            ->each(fn (LegacyPlateEntitlement $entitlement) => $entitlement->update(array_filter([
                'status' => $status,
                'fulfilled_at' => $status === LegacyPlateEntitlementStatus::Delivered ? ($entitlement->fulfilled_at ?? now()) : null,
            ])));
    }

    /**
     * Cancelling a job only cancels the Plate if no OTHER job for this
     * plate already reached Ready/Delivered — a reprint's original job
     * being (hypothetically) cancelled after the fact must never take a
     * successfully-delivered plate down with it.
     */
    private function syncCancelled(ProductionJob $job, Plate $plate): void
    {
        $hasSuccessfulSibling = ProductionJob::query()
            ->where('plate_id', $plate->id)
            ->whereKeyNot($job->id)
            ->whereIn('status', [ProductionJobStatus::Ready, ProductionJobStatus::Delivered])
            ->exists();

        if (! $hasSuccessfulSibling) {
            $this->moveTo($plate, PlateStatus::Cancelled);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function moveTo(Plate $plate, PlateStatus $status, array $extra = []): void
    {
        if ($plate->status === $status && $extra === []) {
            return;
        }

        $plate->update([...$extra, 'status' => $status]);
    }
}
