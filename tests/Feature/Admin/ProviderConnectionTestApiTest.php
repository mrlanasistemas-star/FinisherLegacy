<?php

use App\Models\ProviderConnection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

test('testing a provider connection requires integrations.sync', function () {
    $connection = ProviderConnection::create([
        'uuid' => (string) Str::uuid(), 'provider_key' => 'mock', 'name' => 'Mock', 'status' => 'untested',
    ]);
    $user = User::factory()->create();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->postJson("/api/v1/admin/provider-connections/{$connection->id}/test");

    $response->assertForbidden();
});

test('a staff user can test a provider connection via the API', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('integrations.sync');
    $connection = ProviderConnection::create([
        'uuid' => (string) Str::uuid(), 'provider_key' => 'mock', 'name' => 'Mock', 'status' => 'untested',
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->postJson("/api/v1/admin/provider-connections/{$connection->id}/test");

    $response->assertOk()->assertJsonPath('data.success', true);
    expect($connection->fresh()->status->value)->toBe('connected');
});
