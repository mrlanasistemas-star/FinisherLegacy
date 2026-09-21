<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\Event;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "Mi Perfil" (product consolidation brief §99-§101): header + stats +
 * filterable history, replacing a page that was just the edit form.
 */
test('Mi Perfil shows the athlete\'s Legacy ID, stats, and history', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->actingAs($user)->get('/dashboard/profile');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard/profile/Show')
        ->where('athlete.legacy_id', $athlete->uuid)
        ->where('stats.event_count', 1)
        ->has('participations.data', 1));
});

test('Mi Perfil filters participations by event without losing them from the filter options', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $eventA = Event::factory()->create();
    $editionA = EventEdition::factory()->create(['event_id' => $eventA->id]);
    $matching = EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'event_edition_id' => $editionA->id]);

    $eventB = Event::factory()->create();
    $editionB = EventEdition::factory()->create(['event_id' => $eventB->id]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'event_edition_id' => $editionB->id]);

    $response = $this->actingAs($user)->get("/dashboard/profile?event_id={$eventA->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('participations.data', 1)
        ->where('participations.data.0.id', $matching->id)
        ->has('filterOptions.events', 2));
});

test('Mi Perfil filters participations by legacy_plate status', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $withPlate = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_edition_id' => $withPlate->event_edition_id,
        'event_participant_id' => $withPlate->id,
        'legacy_plate_model_id' => LegacyPlateModel::factory()->create()->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $response = $this->actingAs($user)->get('/dashboard/profile?legacy_plate=paid');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('participations.data', 1)
        ->where('participations.data.0.id', $withPlate->id));
});
