<?php

namespace App\Actions\Preparation;

use App\Actions\Production\DeliverProductionPlate;
use App\Actions\Production\ProductionJobAction;
use App\Actions\Production\StartProductionPreparation;
use App\Enums\ProductionJobStatus;
use App\Exceptions\Nfc\NfcNotClaimableException;
use App\Exceptions\Production\InvalidProductionTransitionException;
use App\Models\NfcAssignment;
use App\Models\ProductionJob;
use App\Models\User;
use App\Services\Production\PlateProductionCoordinator;
use App\Services\Production\ProductionJobStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * "Preparación y entrega" from the web (docs/NFC.md §Preparación). The
 * machine software does the actual personalisation; staff only record the
 * outcome in four plain steps. Every hop still goes through the one
 * ProductionJobStateMachine + PlateProductionCoordinator (docs/adr/0003) —
 * this is a human-friendly front door, not a second state machine. The
 * Device API keeps driving the same jobs untouched.
 *
 * Legacy Plate V3 is front-only (money clip on the back): "Personalizada"
 * records the face as done; the flip/back states are traversed without
 * inventing a back engraving. Historical two-face template plates record
 * both faces, exactly as the old manual fallback did.
 */
class AdvancePlatePreparation extends ProductionJobAction
{
    public function __construct(
        ProductionJobStateMachine $stateMachine,
        PlateProductionCoordinator $coordinator,
        private readonly StartProductionPreparation $startTemplatePreparation,
        private readonly DeliverProductionPlate $deliverAction,
    ) {
        parent::__construct($stateMachine, $coordinator);
    }

    /** Pendiente → Preparando. */
    public function start(ProductionJob $job, User $actor): ProductionJob
    {
        $job->loadMissing('plate');

        // Historical template plates freeze their machine artifact here
        // (docs/adr/0003 §Artifact) — same Action the Device API uses.
        if ($job->plate->plate_template_version_id !== null) {
            return $this->audited($this->startTemplatePreparation->handle($job, $actor), $actor, 'preparation.started');
        }

        return DB::transaction(function () use ($job, $actor) {
            if ($job->status === ProductionJobStatus::Queued) {
                $job = $this->transition($job, ProductionJobStatus::Assigned, ['assigned_user_id' => $actor->id], $actor);
            }

            $job = $this->transition($job, ProductionJobStatus::Preparing, [
                'preparation_started_at' => $job->preparation_started_at ?? now(),
                'started_at' => $job->started_at ?? now(),
            ], $actor);

            return $this->audited($job, $actor, 'preparation.started');
        });
    }

    /** Preparando → Personalizada (resumes wherever the machine left it). */
    public function markPersonalized(ProductionJob $job, User $actor): ProductionJob
    {
        return DB::transaction(function () use ($job, $actor) {
            $job->loadMissing('plate');
            $twoFaces = $job->plate->plate_template_version_id !== null;

            if ($job->status === ProductionJobStatus::Preparing) {
                $job = $this->transition($job, ProductionJobStatus::EngravingFront, ['front_started_at' => $job->front_started_at ?? now()], $actor);
            }

            if ($job->status === ProductionJobStatus::EngravingFront) {
                $job = $this->transition($job, ProductionJobStatus::AwaitingFlip, [
                    'front_engraved_at' => now(),
                    ...ProductionJob::actorAttributes('front', $actor),
                ], $actor);
            }

            if ($job->status === ProductionJobStatus::AwaitingFlip) {
                $job = $this->transition($job, ProductionJobStatus::EngravingBack, $twoFaces ? [
                    'flip_confirmed_at' => now(),
                    'back_started_at' => now(),
                    ...ProductionJob::actorAttributes('flip', $actor),
                ] : [], $actor);
            }

            if ($job->status === ProductionJobStatus::EngravingBack) {
                $job = $this->transition($job, ProductionJobStatus::VerifyingQr, $twoFaces ? [
                    'back_engraved_at' => now(),
                    ...ProductionJob::actorAttributes('back', $actor),
                ] : [], $actor);
            }

            if ($job->status !== ProductionJobStatus::VerifyingQr) {
                throw new InvalidProductionTransitionException($job->status->value, 'personalized');
            }

            return $this->audited($job, $actor, 'preparation.personalized');
        });
    }

    /**
     * NFC asignado → Lista. The NFC tag (resolving to this plate) is the
     * link check that replaces the historical QR verification.
     */
    public function markReady(ProductionJob $job, User $actor): ProductionJob
    {
        return DB::transaction(function () use ($job, $actor) {
            $job = ProductionJob::query()->lockForUpdate()->findOrFail($job->id);

            if (! NfcAssignment::query()->open()->where('plate_id', $job->plate_id)->exists()) {
                throw new NfcNotClaimableException('Asigna la etiqueta NFC antes de marcarla como lista.');
            }

            if ($job->status === ProductionJobStatus::Ready) {
                return $job;
            }

            $job = $this->transition($job, ProductionJobStatus::Ready, ['ready_at' => now(), 'completed_at' => now()], $actor);

            return $this->audited($job, $actor, 'preparation.ready');
        });
    }

    /** Lista → Entregada. */
    public function deliver(ProductionJob $job, User $actor): ProductionJob
    {
        return $this->deliverAction->handle($job, $actor);
    }

    private function audited(ProductionJob $job, User $actor, string $event): ProductionJob
    {
        activity()->causedBy($actor)->performedOn($job)->log($event);

        return $job;
    }
}
