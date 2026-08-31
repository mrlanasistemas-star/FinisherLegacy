<?php

namespace App\Actions\LegacyPlates;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;

/**
 * A presale can happen before a bib exists (brief §39/§163/§167) — once
 * EventParticipant appears, this safely matches it to the entitlement by
 * Athlete + EventEdition. Refuses to link across a different edition or
 * athlete than the entitlement was sold for, and never re-links an
 * already-linked entitlement to a different participant.
 */
class LinkLegacyPlateEntitlementToParticipant
{
    public function handle(LegacyPlateEntitlement $entitlement, EventParticipant $participant): LegacyPlateEntitlement
    {
        if ($entitlement->event_participant_id !== null && $entitlement->event_participant_id !== $participant->id) {
            throw new \InvalidArgumentException('Esta entitlement ya está vinculada a otro participante.');
        }

        if ($participant->event_edition_id !== $entitlement->event_edition_id) {
            throw new \InvalidArgumentException('El participante no pertenece a la edición de esta entitlement.');
        }

        if ($entitlement->athlete_id !== null && $participant->athlete_id !== null && $entitlement->athlete_id !== $participant->athlete_id) {
            throw new \InvalidArgumentException('El participante no corresponde al Athlete de esta entitlement.');
        }

        $entitlement->update([
            'event_participant_id' => $participant->id,
            'athlete_id' => $entitlement->athlete_id ?? $participant->athlete_id,
            'status' => $entitlement->status === LegacyPlateEntitlementStatus::Paid
                ? LegacyPlateEntitlementStatus::Linked
                : $entitlement->status,
        ]);

        return $entitlement->fresh();
    }
}
