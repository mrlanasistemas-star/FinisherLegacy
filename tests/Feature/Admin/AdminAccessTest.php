<?php

use App\Enums\IncidentStatus;
use App\Models\EventIncident;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('admin routes are blocked for users without dashboard.admin.view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('an admin can see the dashboard with real metrics', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/Dashboard')
        ->has('general.athletes')
        ->has('general.open_incidents')
        ->has('kpis.pending_orders')
    );
});

test('an event_operator without dashboard.admin.view cannot reach admin lists', function () {
    $operator = User::factory()->create();
    $operator->assignRole('event_operator');

    $this->actingAs($operator)->get('/admin/plates')->assertForbidden();
});

test('an admin can list plates and legacy codes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/plates')->assertOk();
    $this->actingAs($admin)->get('/admin/legacy-codes')->assertOk();
    $this->actingAs($admin)->get('/admin/participants')->assertOk();
    $this->actingAs($admin)->get('/admin/preregistrations')->assertOk();
    $this->actingAs($admin)->get('/admin/editions')->assertOk();
});

test('an admin can resolve an open incident but not resolve it twice', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $incident = EventIncident::factory()->create([
        'status' => IncidentStatus::Open,
        'reported_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->patch("/admin/incidents/{$incident->id}/resolve")
        ->assertRedirect();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Resolved)
        ->and($incident->fresh()->resolved_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->patch("/admin/incidents/{$incident->id}/resolve")
        ->assertStatus(409);
});
