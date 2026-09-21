<?php

use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventRace;
use App\Models\EventResult;
use App\Models\LegacyPlateModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * The Legacy Plate v2 admin web surface (brief §14-§19/§79-§83): the
 * production queue's eligibility and payment gate always come from
 * App\Services\PlateEligibilityService — Vue never re-derives it — and the
 * "producir" action is blocked server-side even if a client somehow enables
 * the button.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('an unpaid entitlement is listed but cannot be produced, even by direct request', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);
    EventResult::factory()->create(['event_participant_id' => $participant->id]);

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'event_edition_id' => $edition->id,
        'event_participant_id' => $participant->id,
        'legacy_plate_model_id' => $model->id,
    ]);

    $response = $this->actingAs($this->admin)->get("/admin/legacy-plates/production?event_edition_id={$edition->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/legacy-plates/Production')
        ->where('queue.0.paid', false)
        ->where('queue.0.eligible', false)
        ->where('queue.0.reasons', ['LEGACY_PLATE_NOT_PAID'])
    );

    $this->actingAs($this->admin)
        ->post("/admin/legacy-plates/production/entitlements/{$entitlement->id}/produce")
        ->assertRedirect();

    expect($entitlement->fresh()->plate_id)->toBeNull();
});

test('a paid, linked, result-ready entitlement can be produced from the queue', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);
    EventResult::factory()->create(['event_participant_id' => $participant->id]);

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'event_edition_id' => $edition->id,
        'event_participant_id' => $participant->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/legacy-plates/production/entitlements/{$entitlement->id}/produce")
        ->assertRedirect();

    expect($entitlement->fresh()->plate_id)->not->toBeNull()
        ->and($entitlement->fresh()->status)->toBe(LegacyPlateEntitlementStatus::Queued);
});

test('the presales screen keeps preventa wording separate from prerregistro and lists unlinked presales', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    app(CreateLegacyPlateEntitlement::class)->handle([
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'price_type' => 'early_presale',
    ]);

    $response = $this->actingAs($this->admin)->get("/admin/legacy-plates/presales?event_edition_id={$edition->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/legacy-plates/Presales')
        ->where('presales.0.linked', false)
        ->where('presales.0.price_type', 'early_presale')
    );
});

test('a production_operator without legacyplates.produce cannot reach the production queue', function () {
    $operator = User::factory()->create();
    $operator->assignRole('production_operator');

    $this->actingAs($operator)->get('/admin/legacy-plates/production')->assertForbidden();
});

test('admin can manually link a presale to a safe candidate — one whose athlete_id already matches', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $athlete = Athlete::factory()->create();

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'athlete_id' => $athlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $race = EventRace::factory()->create(['event_edition_id' => $edition->id]);
    $participant = EventParticipant::factory()->create([
        'event_edition_id' => $edition->id,
        'event_race_id' => $race->id,
        'athlete_id' => $athlete->id,
        'bib_number' => '505',
    ]);

    // The presales list only ever offers this participant as a candidate
    // — it already matches the entitlement's athlete_id (brief §22: "no
    // magic fuzzy merge").
    $listResponse = $this->actingAs($this->admin)->get("/admin/legacy-plates/presales?event_edition_id={$edition->id}");
    $listResponse->assertInertia(fn ($page) => $page->where('presales.0.link_candidates.0.id', $participant->id));

    $this->actingAs($this->admin)
        ->post("/admin/legacy-plates/presales/{$entitlement->id}/link", ['event_participant_id' => $participant->id])
        ->assertRedirect();

    expect($entitlement->fresh()->event_participant_id)->toBe($participant->id);
});

test('admin cannot link a presale to a participant from a different event edition', function () {
    $edition = EventEdition::factory()->create();
    $otherEdition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();
    $athlete = Athlete::factory()->create();

    $entitlement = app(CreateLegacyPlateEntitlement::class)->handle([
        'athlete_id' => $athlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $race = EventRace::factory()->create(['event_edition_id' => $otherEdition->id]);
    $participant = EventParticipant::factory()->create([
        'event_edition_id' => $otherEdition->id,
        'event_race_id' => $race->id,
        'athlete_id' => $athlete->id,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/legacy-plates/presales/{$entitlement->id}/link", ['event_participant_id' => $participant->id])
        ->assertSessionHasErrors('event_participant_id');

    expect($entitlement->fresh()->event_participant_id)->toBeNull();
});
