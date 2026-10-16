<?php

use App\Models\Athlete;
use App\Models\AthleteProfile;
use App\Models\Event;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventResult;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Queries\Admin\SearchAdminRecords;
use App\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;

/**
 * Every destination linked from the public navbar, the account menu, the
 * "Mi espacio" sidebar and the admin sections/workspace tabs
 * (resources/js/config/navigation.ts) must answer — no dead links — and
 * every retired admin URL must redirect, never 404 (docs/product/module-audit.md).
 *
 * Cross-agent destinations (/admin/comercio, /admin/legacy-nfc,
 * /admin/events/{id}/tracking, /horizon) are built on other branches and
 * are checked after the merge, not here.
 */
function navAdmin(): User
{
    test()->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

/** A staff role with exactly these permissions (plus the admin panel gate). */
function navStaff(array $permissions): User
{
    test()->seed(RolePermissionSeeder::class);
    $role = Role::findOrCreate('nav_test_'.md5(implode(',', $permissions)));
    $role->syncPermissions(['dashboard.admin.view', ...$permissions]);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('public navigation destinations respond for guests', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    '/', '/comunidad', '/events', '/fotos', '/tienda', '/nosotros', '/contact',
    '/buscar', '/buscar?q=ma', '/how-it-works', '/privacy', '/terms', '/login', '/register',
]);

test('Mi espacio and account menu destinations respond for a signed in athlete', function (string $url) {
    $user = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => 'navtest', 'profile_visibility' => 'public']);

    $this->actingAs($user)->get($url)->assertOk();
})->with([
    // Mi espacio (sidebar + mobile bottom nav)
    '/dashboard', '/dashboard/my-events', '/dashboard/my-gear', '/mis-pedidos', '/dashboard/profile',
    // Explorar + account
    '/comunidad', '/events', '/tienda', '/fotos',
    '/dashboard/profile/edit', '/@navtest', '/notifications', '/settings/profile', '/carrito',
    '/comunidad?tab=siguiendo', '/comunidad?tab=mis-deportes', '/dashboard/medals',
]);

test('the retired appearance settings URL redirects instead of 404ing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/appearance')->assertRedirect('/settings/profile');
});

test('every admin section and workspace tab destination responds for an admin', function (string $url) {
    $this->actingAs(navAdmin())->get($url)->assertOk();
})->with([
    // Inicio
    '/admin',
    // Eventos
    '/admin/events', '/admin/events/create', '/admin/photos', '/admin/preregistrations',
    // Comercio (Resumen is /admin/comercio — A2)
    '/admin/products', '/admin/inventory', '/admin/orders', '/admin/payments', '/admin/coupons',
    '/admin/promotions', '/admin/product-categories',
    // Legacy + NFC (Resumen is /admin/legacy-nfc — A6)
    '/admin/legacy-nfc/preparacion', '/admin/legacy-nfc/etiquetas', '/admin/legacy-nfc/productos', '/admin/plates', '/admin/legacy-plates/presales', '/admin/legacy-plate-models',
    // Comunidad
    '/admin/community',
    // Personas
    '/admin/users', '/admin/athletes', '/admin/photographers', '/admin/identity-conflicts', '/admin/messages',
    // Configuración
    '/admin/settings', '/admin/content', '/admin/organizers', '/admin/data-sources', '/admin/integrations', '/admin/audit',
]);

test('hidden technical modules stay reachable by direct URL', function (string $url) {
    $this->actingAs(navAdmin())->get($url)->assertOk();
})->with(['/admin/legacy-codes', '/admin/plate-studio', '/admin/machine-profiles', '/admin/production-devices', '/imports']);

test('retired admin URLs answer with a permanent redirect to their new place', function () {
    $admin = navAdmin();
    $edition = EventEdition::factory()->create();

    $this->actingAs($admin)->get('/admin/editions')->assertStatus(301)->assertRedirect('/admin/events');
    $this->actingAs($admin)->get('/admin/editions/create')->assertStatus(301)->assertRedirect('/admin/events/create');
    $this->actingAs($admin)->get("/admin/editions/{$edition->id}")->assertStatus(301)->assertRedirect("/admin/events/{$edition->id}");
    $this->actingAs($admin)->get('/admin/participants')->assertStatus(301)->assertRedirect('/admin/events');
    $this->actingAs($admin)->get("/admin/participants?event_edition_id={$edition->id}&q=ana")
        ->assertStatus(301)
        ->assertRedirect("/admin/events/{$edition->id}/participants?q=ana");
    $this->actingAs($admin)->get("/admin/preregistrations?event_edition_id={$edition->id}&status=confirmed")
        ->assertStatus(301)
        ->assertRedirect("/admin/events/{$edition->id}/preregistrations?status=confirmed");
});

test('the redirects never bypass the admin panel gate', function () {
    test()->seed(RolePermissionSeeder::class);
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get('/admin/editions')->assertForbidden();
    $this->actingAs($athlete)->get('/admin/events')->assertForbidden();
});

test('the event workspace renders every tab with its shared header', function (string $suffix, string $component) {
    $edition = EventEdition::factory()->create();
    EventParticipant::factory()->count(2)->create(['event_edition_id' => $edition->id]);

    $this->actingAs(navAdmin())->get("/admin/events/{$edition->id}{$suffix}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component($component)
            ->where('workspace.id', $edition->id)
            ->where('workspace.base_url', "/admin/events/{$edition->id}")
            ->where('workspace.counts.participants', 2));
})->with([
    'Resumen' => ['', 'admin/editions/Show'],
    'Participantes' => ['/participants', 'admin/participants/Index'],
    'Resultados' => ['/results', 'admin/events/Results'],
    'Prerregistros' => ['/preregistrations', 'admin/preregistrations/Index'],
    'Datos' => ['/data', 'admin/events/Data'],
    'Media' => ['/media', 'admin/photos/Index'],
]);

test('the results tab ranks this event only, positioned results first', function () {
    $edition = EventEdition::factory()->create();
    $other = EventEdition::factory()->create();

    $second = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);
    EventResult::factory()->create(['event_participant_id' => $second->id, 'overall_position' => 2, 'status' => 'finished']);
    $unranked = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);
    EventResult::factory()->create(['event_participant_id' => $unranked->id, 'overall_position' => null, 'status' => 'dnf']);
    $first = EventParticipant::factory()->create(['event_edition_id' => $edition->id]);
    EventResult::factory()->create(['event_participant_id' => $first->id, 'overall_position' => 1, 'status' => 'finished']);
    $foreign = EventParticipant::factory()->create(['event_edition_id' => $other->id]);
    EventResult::factory()->create(['event_participant_id' => $foreign->id, 'overall_position' => 1]);

    $this->actingAs(navAdmin())->get("/admin/events/{$edition->id}/results")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results.data', 3)
            ->where('results.data.0.participant_id', $first->id)
            ->where('results.data.1.participant_id', $second->id)
            ->where('results.data.2.participant_id', $unranked->id)
            ->where('summary.finishers', 2)
            ->where('summary.dnf', 1));
});

test('each workspace tab enforces its own permission', function () {
    $edition = EventEdition::factory()->create();
    $eventsOnly = navStaff(['events.view']);

    $this->actingAs($eventsOnly)->get("/admin/events/{$edition->id}")->assertOk();
    $this->actingAs($eventsOnly)->get("/admin/events/{$edition->id}/data")->assertOk();
    $this->actingAs($eventsOnly)->get("/admin/events/{$edition->id}/participants")->assertForbidden();
    $this->actingAs($eventsOnly)->get("/admin/events/{$edition->id}/results")->assertForbidden();
    $this->actingAs($eventsOnly)->get("/admin/events/{$edition->id}/preregistrations")->assertForbidden();
    $this->actingAs($eventsOnly)->get("/admin/events/{$edition->id}/media")->assertForbidden();
});

test('the admin dashboard exposes the operational "today" overview', function () {
    EventEdition::factory()->create(['event_date' => now()->toDateString()]);
    EventEdition::factory()->create(['event_date' => now()->addDays(30)->toDateString()]);

    $this->actingAs(navAdmin())->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.events', 1)
            ->where('today.events.0.is_today', true)
            ->has('today.preparation_queue')
            ->has('today.community_reports'));
});

test('every permission the navigation config references exists in the catalog', function () {
    $source = file_get_contents(resource_path('js/config/navigation.ts'));
    preg_match_all("/permission:\s*'([a-z_.]+)'|hrefPermission:\s*'([a-z_.]+)'/", $source, $matches);
    $referenced = array_unique(array_filter([...$matches[1], ...$matches[2]]));

    // roles.manage is the super-admin-only pseudo permission (HandleInertiaRequests).
    $known = [...PermissionCatalog::allKeys(), 'roles.manage'];

    expect($referenced)->not->toBeEmpty();
    foreach ($referenced as $permission) {
        expect($known)->toContain($permission);
    }
});

test('a plain athlete gets no staff permission, so no admin section renders', function () {
    test()->seed(RolePermissionSeeder::class);
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.permissions', []));
});

test('global admin search is bounded, typed and permission-filtered', function () {
    $event = Event::factory()->create(['name' => 'Maratón Navegación']);
    EventEdition::factory()->count(7)->create(['event_id' => $event->id]);
    Athlete::factory()->create(['full_name' => 'Ana Navegación']);
    Product::factory()->create(['name' => 'Gorra Navegación']);

    $response = $this->actingAs(navAdmin())->getJson('/admin/search?q=Navegación')->assertOk();

    $groups = collect($response->json('data'))->keyBy('type');
    expect($groups->keys()->all())->toEqualCanonicalizing(['event', 'athlete', 'product'])
        ->and($groups['event']['items'])->toHaveCount(SearchAdminRecords::PER_TYPE)
        ->and($groups['event']['items'][0]['url'])->toStartWith('/admin/events/');

    // Too short → nothing, no query work.
    $this->actingAs(navAdmin())->getJson('/admin/search?q=a')->assertOk()->assertExactJson(['data' => []]);
});

test('global admin search only returns the record types the viewer may open', function () {
    $event = Event::factory()->create(['name' => 'Trail Permisos']);
    EventEdition::factory()->create(['event_id' => $event->id]);
    Order::factory()->create(['order_number' => 'FL-PERM-0001']);

    $eventsOnly = navStaff(['events.view']);

    $types = collect($this->actingAs($eventsOnly)->getJson('/admin/search?q=Trail')->assertOk()->json('data'))->pluck('type');
    expect($types->all())->toBe(['event']);

    $this->actingAs($eventsOnly)->getJson('/admin/search?q=FL-PERM')->assertOk()->assertExactJson(['data' => []]);
});

test('global admin search is closed to guests and athletes and is throttled', function () {
    test()->seed(RolePermissionSeeder::class);
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->getJson('/admin/search?q=ana')->assertUnauthorized();
    $this->actingAs($athlete)->getJson('/admin/search?q=ana')->assertForbidden();

    $route = app('router')->getRoutes()->getByName('admin.search');
    expect($route->gatherMiddleware())->toContain('throttle:60,1');
});
