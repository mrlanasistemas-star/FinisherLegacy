<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\LegacyPlateFieldAlignment;
use App\Enums\LegacyPlateFieldKey;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\EventEdition;
use App\Models\EventGearSelection;
use App\Models\EventParticipant;
use App\Models\LegacyPlateModel;
use App\Models\LegacyPlateModelField;
use App\Models\Plate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "MI LEGADO" (product UX consolidation brief §3-§6) — the card gallery on
 * /dashboard and its per-participation detail at /dashboard/legado/{id},
 * replacing "Mis eventos" / "Mis medallas" / "Mis Legacy Plates" in
 * navigation without touching what those routes still return by direct URL.
 */
test('the dashboard includes a Mi Legado card for every one of the athlete\'s own participations', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $edition = EventEdition::factory()->create();
    EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'event_edition_id' => $edition->id]);

    $otherAthlete = Athlete::factory()->create();
    EventParticipant::factory()->create(['athlete_id' => $otherAthlete->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard')->has('legado', 1));
});

test('a participant belonging to another athlete cannot be opened through the Legado URL', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');

    $otherAthlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $otherAthlete->id]);

    $this->actingAs($user)->get("/dashboard/legado/{$participant->id}")->assertForbidden();
});

test('Legado detail carries the real Legacy Plate model geometry for the viewer, not a placeholder', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $edition = EventEdition::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'event_edition_id' => $edition->id]);

    $model = LegacyPlateModel::factory()->create(['width_mm' => 90, 'height_mm' => 36]);
    LegacyPlateModelField::create([
        'legacy_plate_model_id' => $model->id,
        'field_key' => LegacyPlateFieldKey::AthleteName,
        'x' => 8, 'y' => 6, 'width' => 74, 'height' => 8,
        'font_size' => 5.5,
        'alignment' => LegacyPlateFieldAlignment::Center,
        'required' => true,
        'visible' => true,
        'sort_order' => 0,
    ]);

    Plate::factory()->create([
        'athlete_id' => $athlete->id,
        'event_participant_id' => $participant->id,
        'legacy_plate_model_id' => $model->id,
        'engraving_display_name' => 'Jesús Ávila',
    ]);

    $response = $this->actingAs($user)->get("/dashboard/legado/{$participant->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard/LegadoShow')
        ->where('legacyPlate.model.width_mm', 90)
        ->where('legacyPlate.model.height_mm', 36)
        ->has('legacyPlate.model.fields', 1)
        ->where('legacyPlate.model.fields.0.field_key', 'athlete_name')
        ->where('legacyPlate.personalization.athlete_name', 'Jesús Ávila'));
});

test('Legado detail still works for a participant with no Legacy Plate at all', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->actingAs($user)->get("/dashboard/legado/{$participant->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard/LegadoShow')
        ->where('legacyPlate', null));
});

test('Legado detail lists owned gear not yet assigned as available to add', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'product_id' => Product::factory()->create()->id,
        'status' => 'active',
        'acquired_at' => now(),
    ]);

    $response = $this->actingAs($user)->get("/dashboard/legado/{$participant->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('availableGear', 1)
        ->where('availableGear.0.uuid', $owned->uuid)
        ->has('gearUsed', 0));
});

test('assigning owned gear to a participation from the Web page shows it as used, not available', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'product_id' => Product::factory()->create()->id,
        'status' => 'active',
        'acquired_at' => now(),
    ]);

    $this->actingAs($user)
        ->post("/dashboard/legado/{$participant->id}/gear", ['athlete_owned_product_uuid' => $owned->uuid])
        ->assertRedirect();

    $response = $this->actingAs($user)->get("/dashboard/legado/{$participant->id}");

    $response->assertInertia(fn ($page) => $page
        ->has('gearUsed', 1)
        ->where('gearUsed.0.athlete_owned_product_uuid', $owned->uuid)
        ->has('availableGear', 0));

    $gearUuid = EventGearSelection::query()->where('event_participant_id', $participant->id)->firstOrFail()->uuid;

    $this->actingAs($user)
        ->delete("/dashboard/legado/{$participant->id}/gear/{$gearUuid}")
        ->assertRedirect();

    expect(EventGearSelection::query()->where('event_participant_id', $participant->id)->count())->toBe(0);
});
