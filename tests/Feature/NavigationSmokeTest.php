<?php

use App\Models\AthleteProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Every destination linked from the redesigned public navbar, footer,
 * account menu and admin sidebar (resources/js/config/navigation.ts)
 * must answer — no dead links after the redesign.
 */
test('public navigation destinations respond for guests', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    '/', '/comunidad', '/events', '/fotos', '/tienda', '/nosotros', '/contact',
    '/buscar', '/buscar?q=ma', '/how-it-works', '/privacy', '/terms', '/login', '/register',
]);

test('account menu destinations respond for a signed in athlete', function (string $url) {
    $user = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => 'navtest', 'profile_visibility' => 'public']);

    $this->actingAs($user)->get($url)->assertOk();
})->with([
    '/dashboard', '/dashboard/profile', '/dashboard/profile/edit', '/@navtest', '/fotos',
    '/mis-pedidos', '/notifications', '/settings/profile', '/carrito', '/comunidad?tab=siguiendo',
    '/comunidad?tab=mis-deportes', '/dashboard/my-gear', '/dashboard/medals',
]);

test('the retired appearance settings URL redirects instead of 404ing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/appearance')->assertRedirect('/settings/profile');
});

test('every admin sidebar destination responds for an admin', function (string $url) {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get($url)->assertOk();
})->with([
    '/admin', '/admin/orders', '/admin/products', '/admin/product-categories', '/admin/inventory',
    '/admin/legacy-plates/production', '/admin/plates', '/admin/legacy-codes',
    '/admin/editions', '/admin/participants', '/admin/preregistrations', '/admin/organizers',
    '/admin/photos', '/admin/community', '/admin/content', '/admin/messages',
    '/admin/athletes', '/admin/users', '/admin/settings', '/admin/audit',
]);
