<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Athletes\IngestEventParticipant;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\EventEdition;
use App\Models\EventRace;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "Cuando después llega EventParticipant... vincular entitlement
 * automáticamente cuando sea seguro" (brief §21) — via
 * App\Actions\Athletes\IngestEventParticipant, the one place every real
 * participant entry point already resolves identity through.
 */
test('a pending presale is linked automatically once a matching participant is ingested for the same athlete+edition', function () {
    $user = User::factory()->create(['email' => 'runner@example.com']);
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    $entitlement = LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $race = EventRace::factory()->create(['event_edition_id' => $edition->id]);

    $participant = app(IngestEventParticipant::class)->handle([
        'event_edition_id' => $edition->id,
        'event_race_id' => $race->id,
        'bib_number' => '101',
        'first_name' => $athlete->first_name,
        'last_name' => $athlete->last_name,
        'email' => 'runner@example.com',
    ], 'test_import');

    expect($entitlement->fresh()->event_participant_id)->toBe($participant->id)
        ->and($entitlement->fresh()->status)->toBe(LegacyPlateEntitlementStatus::Linked);
});

test('a presale for a different event edition is never linked to an unrelated participant', function () {
    $user = User::factory()->create(['email' => 'runner2@example.com']);
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $presaleEdition = EventEdition::factory()->create();
    $otherEdition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    $entitlement = LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_edition_id' => $presaleEdition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $race = EventRace::factory()->create(['event_edition_id' => $otherEdition->id]);

    app(IngestEventParticipant::class)->handle([
        'event_edition_id' => $otherEdition->id,
        'event_race_id' => $race->id,
        'bib_number' => '202',
        'first_name' => $athlete->first_name,
        'last_name' => $athlete->last_name,
        'email' => 'runner2@example.com',
    ], 'test_import');

    expect($entitlement->fresh()->event_participant_id)->toBeNull();
});
