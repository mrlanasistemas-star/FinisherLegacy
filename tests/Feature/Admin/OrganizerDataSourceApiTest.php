<?php

use App\Models\Organizer;
use App\Models\ProviderConnection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

function adminAuthHeader(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
}

test('setting an Organizer data source requires eventdata.manage', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create();

    $response = $this->withHeaders(adminAuthHeader($user))
        ->putJson("/api/v1/admin/organizers/{$organizer->id}/data-source", ['type' => 'manual']);

    $response->assertForbidden();
});

test('a staff user can set and read back an Organizer data source without leaking credentials', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('eventdata.manage');
    $organizer = Organizer::factory()->create();
    $connection = ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => 'generic_rest',
        'name' => 'Test API',
        'base_url' => 'https://example.test',
        'credentials' => 'top-secret',
        'status' => 'untested',
    ]);
    $headers = adminAuthHeader($user);

    $update = $this->withHeaders($headers)->putJson("/api/v1/admin/organizers/{$organizer->id}/data-source", [
        'type' => 'api',
        'provider_connection_id' => $connection->id,
    ]);
    $update->assertOk()
        ->assertJsonPath('data.type', 'api')
        ->assertJsonPath('data.provider_connection.name', 'Test API');
    expect(json_encode($update->json()))->not->toContain('top-secret');

    $show = $this->withHeaders($headers)->getJson("/api/v1/admin/organizers/{$organizer->id}/data-source");
    $show->assertOk()->assertJsonPath('data.type', 'api');
});
