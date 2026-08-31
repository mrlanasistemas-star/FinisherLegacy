<?php

use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\EventEdition;
use App\Models\EventParticipant;
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
