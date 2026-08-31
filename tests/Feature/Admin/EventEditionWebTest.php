<?php

use App\Models\EventEdition;
use App\Models\Organizer;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * The web (Inertia) surface added on top of the already-tested backend
 * (App\Actions\CreateEvent, App\Queries\*) for the frontend brief
 * §6-§10/§16-§17: manual event creation and the Organizer detail tabs.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('an admin can create a manual event through the Create form and lands on its detail page', function () {
    $sport = Sport::factory()->create();
    $organizer = Organizer::factory()->create();

    $response = $this->actingAs($this->admin)->post('/admin/editions', [
        'name' => 'Maratón CDMX',
        'sport_id' => $sport->id,
        'organizer_id' => $organizer->id,
        'edition_name' => 'Edición 2026',
        'year' => 2026,
        'event_date' => '2026-11-01',
        'city' => 'Ciudad de México',
        'country' => 'México',
        'data_source_type' => 'manual',
        'races' => [
            ['name' => '42K', 'distance_value' => 42.2, 'distance_unit' => 'km'],
        ],
    ]);

    $edition = EventEdition::query()->where('name', 'Edición 2026')->firstOrFail();
    $response->assertRedirect("/admin/editions/{$edition->id}");

    $this->actingAs($this->admin)->get("/admin/editions/{$edition->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/editions/Show')
            ->where('edition.name', 'Edición 2026')
            ->where('edition.event.name', 'Maratón CDMX')
        );
});

test('creating an event without at least one race fails validation', function () {
    $sport = Sport::factory()->create();

    $this->actingAs($this->admin)->post('/admin/editions', [
        'name' => 'Sin carreras',
        'sport_id' => $sport->id,
        'edition_name' => 'Edición',
        'year' => 2026,
        'event_date' => '2026-11-01',
        'city' => 'CDMX',
        'country' => 'México',
        'races' => [],
    ])->assertSessionHasErrors('races');
});

test('a non-admin cannot reach the event creation screen', function () {
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get('/admin/editions/create')->assertForbidden();
});

test('the Organizer detail page exposes its Eventos and Datos/Integración tabs together', function () {
    $organizer = Organizer::factory()->create();

    $response = $this->actingAs($this->admin)->get("/admin/organizers/{$organizer->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/organizers/Show')
        ->where('organizer.id', $organizer->id)
        ->has('events')
        ->where('dataSource', null)
    );
});

test('updating an Organizer data source never leaks a stored secret back into the page', function () {
    $organizer = Organizer::factory()->create();

    $this->actingAs($this->admin)->put("/admin/organizers/{$organizer->id}/data-source", [
        'type' => 'manual',
    ])->assertRedirect();

    $response = $this->actingAs($this->admin)->get("/admin/organizers/{$organizer->id}");

    $response->assertInertia(fn ($page) => $page->where('dataSource.type', 'manual'));
    $response->assertDontSee('client_secret', escape: false);
});
