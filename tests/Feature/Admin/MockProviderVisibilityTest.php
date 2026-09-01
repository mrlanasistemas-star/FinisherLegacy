<?php

use App\Enums\ProviderConnectionStatus;
use App\Models\Organizer;
use App\Models\ProviderConnection;
use App\Models\User;
use App\Services\Integrations\EventProviderRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/**
 * Data Sources UX (product UX consolidation brief §39-§41): a "Mock Event
 * Provider" is a dev/test fixture, never a real production data source
 * choice. Outside local/testing it must not appear as something an admin
 * can pick for a real Organizer/Event — but existing mock connections
 * (and the tests that rely on them, e.g. IntegrationsAdminUiTest) are
 * never deleted, just excluded from "pick a real connection" lists.
 */
function createProviderConnection(string $providerKey, string $name): ProviderConnection
{
    return ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => $providerKey,
        'name' => $name,
        'status' => ProviderConnectionStatus::Untested,
    ]);
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('the mock adapter is offered when creating a connection locally/testing', function () {
    expect(app(EventProviderRegistry::class)->keys())->toContain('mock');
});

test('the mock adapter is not offered when creating a connection in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(app(EventProviderRegistry::class)->keys())->not->toContain('mock');
});

test('an existing mock connection is excluded from "selectable" lists in production, not deleted', function () {
    $mock = createProviderConnection('mock', 'Mock Event Provider');
    $real = createProviderConnection('generic_rest', 'Sports Timing México');

    app()->detectEnvironment(fn () => 'production');

    $selectable = ProviderConnection::query()->selectable()->pluck('id');

    expect($selectable)->not->toContain($mock->id)
        ->and($selectable)->toContain($real->id)
        ->and(ProviderConnection::query()->find($mock->id))->not->toBeNull();
});

test('a mock connection is selectable locally/testing (fixtures keep working)', function () {
    $mock = createProviderConnection('mock', 'Mock Event Provider');

    expect(ProviderConnection::query()->selectable()->pluck('id'))->toContain($mock->id);
});

test('the Create Event form never offers a mock connection as a data source in production', function () {
    createProviderConnection('mock', 'Mock Event Provider');
    app()->detectEnvironment(fn () => 'production');

    $response = $this->actingAs($this->admin)->get('/admin/editions/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('providerConnections', []));
});

test('an Organizer\'s Datos tab never offers a mock connection in production', function () {
    createProviderConnection('mock', 'Mock Event Provider');
    $organizer = Organizer::factory()->create();
    app()->detectEnvironment(fn () => 'production');

    $response = $this->actingAs($this->admin)->get("/admin/organizers/{$organizer->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('providerConnections', []));
});
