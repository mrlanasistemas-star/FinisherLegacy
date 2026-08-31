<?php

use App\Actions\CreateEvent;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('a manual event can be created with no Organizer at all', function () {
    $sport = Sport::factory()->create();

    $edition = app(CreateEvent::class)->handle([
        'name' => 'Carrera Local 2027',
        'sport_id' => $sport->id,
        'edition_name' => 'Edición 2027',
        'year' => 2027,
        'event_date' => '2027-05-01',
        'city' => 'Guadalajara',
        'country' => 'MX',
        'races' => [['name' => '10K'], ['name' => '21K', 'distance_value' => 21.097, 'distance_unit' => 'km']],
    ]);

    expect($edition->event->organizer_id)->toBeNull()
        ->and($edition->event->slug)->not->toBeEmpty()
        ->and($edition->races)->toHaveCount(2)
        ->and($edition->timezone)->toBe('America/Mexico_City');
});

test('creating two events with the same name produces distinct slugs', function () {
    $sport = Sport::factory()->create();
    $data = fn () => [
        'name' => 'Maratón Repetido', 'sport_id' => $sport->id, 'edition_name' => 'Edición',
        'year' => 2027, 'event_date' => '2027-05-01', 'city' => 'CDMX', 'country' => 'MX',
        'races' => [['name' => '42K']],
    ];

    $first = app(CreateEvent::class)->handle($data());
    $second = app(CreateEvent::class)->handle($data());

    expect($first->event->slug)->not->toBe($second->event->slug);
});

test('POST /api/v1/events requires the events.manage permission', function () {
    $user = User::factory()->create();
    $sport = Sport::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

    $response = $this->withHeaders($headers)->postJson('/api/v1/events', [
        'name' => 'Evento X', 'sport_id' => $sport->id, 'edition_name' => 'Ed', 'year' => 2027,
        'event_date' => '2027-01-01', 'city' => 'CDMX', 'country' => 'MX', 'races' => [['name' => '5K']],
    ]);

    $response->assertForbidden();
});

test('POST /api/v1/events creates an event for a staff user with events.manage', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('events.manage');
    $sport = Sport::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

    $response = $this->withHeaders($headers)->postJson('/api/v1/events', [
        'name' => 'Evento Staff', 'sport_id' => $sport->id, 'edition_name' => 'Edición 1', 'year' => 2027,
        'event_date' => '2027-01-01', 'city' => 'CDMX', 'country' => 'MX', 'races' => [['name' => '5K']],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.event.name', 'Evento Staff');
});
