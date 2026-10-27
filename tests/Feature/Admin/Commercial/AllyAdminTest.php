<?php

use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Models\Ally;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\Role;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

test('admins create, filter and update allies with commercial terms', function () {
    $admin = tagStaff();

    $this->actingAs($admin)->post('/admin/aliados', [
        'name' => 'Box Cuernavaca',
        'type' => 'gym',
        'status' => 'active',
        'city' => 'Cuernavaca',
        'email' => 'box@example.test',
        'commission_percent' => 18.5,
        'commercial_terms' => 'Consignación 30 días.',
    ])->assertRedirect();

    $ally = Ally::query()->where('name', 'Box Cuernavaca')->firstOrFail();
    expect($ally->slug)->toBe('box-cuernavaca')
        ->and((float) $ally->commission_percent)->toBe(18.5);

    Ally::factory()->create(['name' => 'Tienda Otra', 'type' => 'store', 'city' => 'Puebla']);

    $this->actingAs($admin)->get('/admin/aliados?type=gym&city=Cuernavaca')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/allies/Index')->has('allies.data', 1)->where('allies.data.0.name', 'Box Cuernavaca'));

    $this->actingAs($admin)->patch("/admin/aliados/{$ally->uuid}", [
        'name' => 'Box Cuernavaca Centro', 'type' => 'gym', 'status' => 'paused', 'commission_percent' => 120,
    ])->assertSessionHasErrors('commission_percent');

    $this->actingAs($admin)->patch("/admin/aliados/{$ally->uuid}", [
        'name' => 'Box Cuernavaca Centro', 'type' => 'gym', 'status' => 'paused', 'commission_percent' => 20,
    ])->assertRedirect();

    expect($ally->fresh()->status->value)->toBe('paused');
});

test('attaching a user links them and grants the ally role only when they lack staff access', function () {
    $admin = tagStaff();
    $ally = Ally::factory()->create();
    $plain = User::factory()->create(['email' => 'vendedor@example.test']);
    $staff = User::factory()->create(['email' => 'staff@example.test']);
    $staff->assignRole('sales_staff');

    $this->actingAs($admin)->post("/admin/aliados/{$ally->uuid}/usuarios", ['email' => 'vendedor@example.test', 'role' => 'manager'])->assertRedirect();
    $this->actingAs($admin)->post("/admin/aliados/{$ally->uuid}/usuarios", ['email' => 'staff@example.test', 'role' => 'seller'])->assertRedirect();
    $this->actingAs($admin)->post("/admin/aliados/{$ally->uuid}/usuarios", ['email' => 'nadie@example.test', 'role' => 'seller'])->assertSessionHasErrors('email');

    expect($ally->users()->count())->toBe(2)
        ->and($plain->fresh()->hasRole('ally'))->toBeTrue()
        ->and($ally->users()->whereKey($plain->id)->first()->pivot->role)->toBe('manager')
        ->and($staff->fresh()->hasRole('ally'))->toBeFalse();

    $this->actingAs($admin)->delete("/admin/aliados/{$ally->uuid}/usuarios/{$plain->id}")->assertRedirect();
    expect($plain->fresh()->hasRole('ally'))->toBeFalse();
});

test('the ally detail shows locations, stock by SKU, sales, activations and unreported sales', function () {
    $admin = tagStaff();
    $ally = Ally::factory()->create();

    $this->actingAs($admin)->post("/admin/aliados/{$ally->uuid}/ubicaciones", ['name' => 'Mostrador'])->assertRedirect();
    $location = InventoryLocation::query()->where('ally_id', $ally->id)->firstOrFail();
    expect($location->type)->toBe(InventoryLocationType::Ally);

    $variant = tagVariant();
    $tags = tagStock($variant, $location, 4, $admin);
    inPersonSale($location, [$tags[0]->public_code], $admin);
    LegacyTag::query()->whereKey($tags[1]->id)->update([
        'status' => LegacyTagStatus::Activated->value, 'ally_id' => $ally->id, 'sold_at' => now(), 'activated_at' => now(),
        'metadata' => json_encode(['unreported_sale' => true]),
    ]);
    SupportCase::query()->create(['uuid' => (string) Str::uuid(), 'number' => 'SC-T-1', 'type' => 'defect', 'subject' => 'Correa rota', 'ally_id' => $ally->id]);

    $this->actingAs($admin)->get("/admin/aliados/{$ally->uuid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/allies/Show')
            ->where('locations.0.units', 2)
            ->where('locations.0.skus.0.sku', $variant->sku)
            ->where('stats.orders', 1)
            ->where('stats.units_activated', 1)
            ->where('stats.unreported', 1)
            ->has('orders', 1)
            ->has('unreported', 1)
            ->has('supportCases', 1));
});

test('allies.view can read but not change allies; athletes get nothing', function () {
    tagStaff();
    $ally = Ally::factory()->create();
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('ally_viewer_test');
    $role->syncPermissions(['dashboard.admin.view', 'allies.view']);
    $viewer->assignRole($role);

    $this->actingAs($viewer)->get('/admin/aliados')->assertOk()->assertInertia(fn ($page) => $page->where('canManage', false));
    $this->actingAs($viewer)->post('/admin/aliados', ['name' => 'X', 'type' => 'gym', 'status' => 'active'])->assertForbidden();

    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');
    $this->actingAs($athlete)->get("/admin/aliados/{$ally->uuid}")->assertForbidden();
});
