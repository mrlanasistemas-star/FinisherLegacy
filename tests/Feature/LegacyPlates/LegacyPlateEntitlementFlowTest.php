<?php

use App\Actions\Integrations\IngestEventResult;
use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Actions\LegacyPlates\GenerateLegacyPlate;
use App\Actions\LegacyPlates\LinkLegacyPlateEntitlementToParticipant;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Exceptions\LegacyPlateAlreadyExistsException;
use App\Exceptions\LegacyPlateNotPaidException;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyPlateModel;
use App\Models\LegacyPlateModelField;
use App\Support\Integrations\ExternalResultData;

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

test('generating a Legacy Plate is blocked when the entitlement is not paid', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'event_edition_id' => $edition->id,
        'event_participant_id' => $participant->id,
        'legacy_plate_model_id' => $model->id,
    ]);

    app(GenerateLegacyPlate::class)->handle($entitlement);
})->throws(LegacyPlateNotPaidException::class);

test('a paid, linked, eligible entitlement generates a Legacy Plate with only dynamic fields', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    LegacyPlateModelField::create([
        'legacy_plate_model_id' => $model->id,
        'field_key' => 'athlete_name',
        'x' => 8, 'y' => 6, 'width' => 60, 'height' => 8,
        'required' => true, 'visible' => true, 'sort_order' => 0,
    ]);

    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);
    app(IngestEventResult::class)->handle(
        $participant,
        ExternalResultData::fromArray(['external_participant_id' => 'X', 'official_time' => '03:00:00']),
        'mock',
    );

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'event_edition_id' => $edition->id,
        'event_participant_id' => $participant->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $plate = app(GenerateLegacyPlate::class)->handle($entitlement->fresh(), 'J. ÁVILA');

    expect($plate->legacy_plate_model_id)->toBe($model->id)
        ->and($plate->engraving_display_name)->toBe('J. ÁVILA')
        ->and($plate->layout_type->value)->toBe('manufactured_dynamic')
        ->and($plate->legacyCode)->not->toBeNull()
        ->and($plate->latestProductionJob)->not->toBeNull();

    expect($entitlement->fresh()->status)->toBe(LegacyPlateEntitlementStatus::Queued)
        ->and($entitlement->fresh()->plate_id)->toBe($plate->id);
});

test('a second generation attempt on the same entitlement is rejected', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);
    app(IngestEventResult::class)->handle(
        $participant,
        ExternalResultData::fromArray(['external_participant_id' => 'X', 'official_time' => '03:00:00']),
        'mock',
    );

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'event_edition_id' => $edition->id,
        'event_participant_id' => $participant->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    app(GenerateLegacyPlate::class)->handle($entitlement->fresh());

    app(GenerateLegacyPlate::class)->handle($entitlement->fresh());
})->throws(LegacyPlateAlreadyExistsException::class);
