<?php

use App\Models\EventIncident;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('resolving an incident via the API requires incidents.manage', function () {
    $user = User::factory()->create();
    $incident = EventIncident::factory()->create();

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->postJson("/api/v1/admin/incidents/{$incident->id}/resolve", ['resolution_type' => 'fixed']);

    $response->assertForbidden();
});

test('a staff user with incidents.manage can resolve an incident with a real resolution type', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('incidents.manage');
    $incident = EventIncident::factory()->create(['status' => 'open']);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->postJson("/api/v1/admin/incidents/{$incident->id}/resolve", [
            'resolution_type' => 'fixed',
            'notes' => 'Corregido manualmente.',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution_type', 'fixed');

    expect($incident->fresh()->resolution_notes)->toBe('Corregido manualmente.');
});

test('an already-resolved incident cannot be resolved again via the API', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('incidents.manage');
    $incident = EventIncident::factory()->create(['status' => 'resolved']);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->postJson("/api/v1/admin/incidents/{$incident->id}/resolve", ['resolution_type' => 'fixed']);

    $response->assertConflict();
});
