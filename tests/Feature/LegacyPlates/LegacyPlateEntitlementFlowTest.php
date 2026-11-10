<?php

use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Actions\LegacyPlates\LinkLegacyPlateEntitlementToParticipant;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyPlateModel;

/**
 * Mirrors the presale E2E from the brief (§169): buy before a bib exists,
 * pay, then a participant appears later and gets linked automatically.
 */
test('a presale entitlement can be created before a participant exists, then linked once one appears', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $athlete = Athlete::factory()->create();

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'athlete_id' => $athlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'price_type' => 'early_presale',
    ]);

    expect($entitlement->status)->toBe(LegacyPlateEntitlementStatus::PendingPayment)
        ->and($entitlement->event_participant_id)->toBeNull();

    $entitlement->update(['status' => LegacyPlateEntitlementStatus::Paid, 'paid_at' => now()]);

    $participant = EventParticipant::factory()->create([
        'event_edition_id' => $edition->id,
        'athlete_id' => $athlete->id,
    ]);

    $linked = app(LinkLegacyPlateEntitlementToParticipant::class)->handle($entitlement->fresh(), $participant);

    expect($linked->event_participant_id)->toBe($participant->id)
        ->and($linked->status)->toBe(LegacyPlateEntitlementStatus::Linked);
});
