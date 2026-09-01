<?php

use App\Models\Event;
use App\Models\EventEdition;
use App\Models\Organizer;
use App\Models\OrganizerDataSource;
use App\Models\ProviderConnection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/**
 * "Fuentes de datos debe permitir agregar/editar más fuentes" (brief §23-
 * §40): an Organizer can have several sources — Manual, File, and one or
 * more API connections, each scoped to a purpose, at most one default per
 * purpose.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('an organizer can have more than one data source', function () {
    $organizer = Organizer::factory()->create();

    $this->actingAs($this->admin)->post("/admin/organizers/{$organizer->id}/data-sources", [
        'name' => 'Manual',
        'type' => 'manual',
        'purpose' => 'both',
        'is_default' => true,
    ])->assertRedirect();

    $this->actingAs($this->admin)->post("/admin/organizers/{$organizer->id}/data-sources", [
        'name' => 'API Resultados',
        'type' => 'api',
        'purpose' => 'results',
        'is_default' => false,
    ])->assertRedirect();

    expect($organizer->dataSources()->count())->toBe(2);

    $response = $this->actingAs($this->admin)->get("/admin/organizers/{$organizer->id}");
    $response->assertInertia(fn ($page) => $page->has('dataSources', 2));
});

test('only one default per purpose is allowed — setting a new default clears the old one', function () {
    $organizer = Organizer::factory()->create();
    $first = OrganizerDataSource::create(['organizer_id' => $organizer->id, 'type' => 'manual', 'purpose' => 'participants', 'is_default' => true, 'active' => true]);

    $this->actingAs($this->admin)->post("/admin/organizers/{$organizer->id}/data-sources", [
        'name' => 'New default',
        'type' => 'file',
        'purpose' => 'participants',
        'is_default' => true,
    ])->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and(OrganizerDataSource::where('organizer_id', $organizer->id)->where('is_default', true)->count())->toBe(1);
});

test('a source with existing history is deactivated, never hard-deleted', function () {
    $organizer = Organizer::factory()->create();
    $source = OrganizerDataSource::create(['organizer_id' => $organizer->id, 'type' => 'manual', 'purpose' => 'both', 'is_default' => true, 'active' => true]);

    $this->actingAs($this->admin)->post("/admin/data-sources/{$source->id}/deactivate")->assertRedirect();

    expect($source->fresh())->not->toBeNull()
        ->and($source->fresh()->active)->toBeFalse();
});

test('an event edition can select a specific organizer data source per purpose', function () {
    $organizer = Organizer::factory()->create();
    $resultsSource = OrganizerDataSource::create(['organizer_id' => $organizer->id, 'type' => 'api', 'purpose' => 'results', 'is_default' => false, 'active' => true]);
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $edition = EventEdition::factory()->create(['event_id' => $event->id]);

    $this->actingAs($this->admin)->put("/admin/editions/{$edition->id}/data-sources", [
        'results_data_source_id' => $resultsSource->id,
    ])->assertRedirect();

    expect($edition->fresh()->results_data_source_id)->toBe($resultsSource->id);

    $response = $this->actingAs($this->admin)->get("/admin/editions/{$edition->id}");
    $response->assertInertia(fn ($page) => $page->where('edition.data_source.resolved_results.type', 'api'));
});

test('a Generic REST data source connection can be created from the web, not just Mock', function () {
    $this->actingAs($this->admin)->post('/admin/integrations', [
        'provider_key' => 'generic_rest',
        'name' => 'Sports Timing MX',
        'base_url' => 'https://api.timingpartner.mx',
        'api_key' => 'secret-token',
        'auth_type' => 'bearer',
        'participants_endpoint' => '/events/{external_event_id}/participants',
        'results_endpoint' => '/events/{external_event_id}/results',
    ])->assertRedirect();

    $connection = ProviderConnection::query()->where('name', 'Sports Timing MX')->firstOrFail();
    expect($connection->provider_key)->toBe('generic_rest')
        ->and($connection->settingsArray()['auth_type'])->toBe('bearer')
        ->and($connection->settingsArray()['participants_endpoint'])->toBe('/events/{external_event_id}/participants');
});

test('a provider connection\'s stored secret is never returned to the frontend', function () {
    $connection = ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => 'generic_rest',
        'name' => 'Sports Timing MX',
        'credentials' => 'super-secret-token',
        'settings' => ['auth_type' => 'bearer'],
        'status' => 'untested',
    ]);

    $response = $this->actingAs($this->admin)->get("/admin/integrations/{$connection->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('connection.has_credentials', true));
    $response->assertDontSee('super-secret-token', escape: false);
});

test('leaving the api_key field blank on update keeps the existing stored credential', function () {
    $connection = ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => 'generic_rest',
        'name' => 'Sports Timing MX',
        'credentials' => 'original-secret',
        'settings' => [],
        'status' => 'untested',
    ]);

    $this->actingAs($this->admin)->patch("/admin/integrations/{$connection->id}", [
        'provider_key' => 'generic_rest',
        'name' => 'Sports Timing MX (renamed)',
        'base_url' => null,
        'api_key' => '',
    ])->assertRedirect();

    expect($connection->fresh()->name)->toBe('Sports Timing MX (renamed)')
        ->and($connection->fresh()->credentials)->toBe('original-secret');
});
