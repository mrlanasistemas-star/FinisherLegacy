<?php

namespace App\Services;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;
use App\Support\PlateEligibilityResult;

/**
 * The one place that decides "can this participant get an integrated
 * Plate right now" — Event Ops (Slice 5) and any future surface ask this
 * instead of re-deriving the rule (docs/adr/0005-unified-event-ingestion.md
 * §Plate eligibility). Deliberately never produces a Plate itself and
 * never runs automatically after a sync (§121-123) — an operator always
 * decides when to press "producir".
 */
class PlateEligibilityService
{
    public function check(EventParticipant $participant): PlateEligibilityResult
    {
        $participant->loadMissing(['result', 'eventEdition', 'identityConflicts', 'plates']);

        $reasons = [];

        if ($participant->identityConflicts->contains(fn ($c) => $c->status->value === 'pending')) {
            $reasons[] = 'IDENTITY_CONFLICT';
        }

        if ($participant->result === null || $participant->result->official_time === null) {
            $reasons[] = 'NO_RESULT';
        }

        if ($participant->eventEdition?->defaultPlateTemplateVersion($participant->event_race_id) === null) {
            $reasons[] = 'NO_TEMPLATE';
        }

        if ($participant->plates->isNotEmpty()) {
            $reasons[] = 'PLATE_ALREADY_EXISTS';
        }

        return new PlateEligibilityResult(eligible: $reasons === [], reasons: $reasons);
    }

    /**
     * Legacy Plate v2's production gate (brief §14-§15/§79-§83): a paid
     * commercial entitlement, linked to a participant with a result, is
     * required before GenerateLegacyPlate may run — payment never
     * auto-triggers production (brief §100), an operator still presses
     * "producir" separately.
     */
    public function checkForEntitlement(LegacyPlateEntitlement $entitlement): PlateEligibilityResult
    {
        $entitlement->loadMissing(['eventParticipant.result', 'eventParticipant.identityConflicts', 'eventParticipant.plates', 'legacyPlateModel']);

        $reasons = [];
        $participant = $entitlement->eventParticipant;

        if (! $entitlement->isPaid()) {
            $reasons[] = 'LEGACY_PLATE_NOT_PAID';
        }

        if ($participant === null) {
            $reasons[] = 'NO_PARTICIPANT_LINKED';
        } else {
            if ($participant->identityConflicts->contains(fn ($c) => $c->status->value === 'pending')) {
                $reasons[] = 'IDENTITY_CONFLICT';
            }

            if ($participant->result === null || $participant->result->official_time === null) {
                $reasons[] = 'NO_RESULT';
            }

            if ($participant->plates->isNotEmpty()) {
                $reasons[] = 'PLATE_ALREADY_EXISTS';
            }
        }

        if ($entitlement->legacyPlateModel === null || ! $entitlement->legacyPlateModel->active) {
            $reasons[] = 'NO_MODEL';
        }

        if (in_array($entitlement->status, [LegacyPlateEntitlementStatus::Produced, LegacyPlateEntitlementStatus::Delivered], true)) {
            $reasons[] = 'LEGACY_PLATE_ALREADY_EXISTS';
        }

        return new PlateEligibilityResult(eligible: $reasons === [], reasons: $reasons);
    }
}
