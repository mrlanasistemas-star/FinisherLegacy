<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\LegacyPlateFieldAlignment;
use App\Enums\LegacyPlateFieldKey;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyPlateModel;
use App\Models\LegacyPlateModelField;
use App\Models\Plate;
use App\Models\User;

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
