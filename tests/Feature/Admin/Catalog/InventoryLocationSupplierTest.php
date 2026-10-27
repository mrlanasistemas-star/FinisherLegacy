<?php

use App\Enums\InventoryLocationType;
use App\Enums\SupplierStatus;
use App\Models\Ally;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\Supplier;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../LegacyTags/helpers.php';

test('location CRUD: create ally location, update, show stock, delete or deactivate', function () {
    $admin = tagStaff();
    $ally = Ally::factory()->create();

    $this->actingAs($admin)->post('/admin/inventario/ubicaciones', [
        'name' => 'Gimnasio Cancún', 'type' => 'ally',
    ])->assertSessionHasErrors('ally_id');

    $this->actingAs($admin)->post('/admin/inventario/ubicaciones', [
        'name' => 'Gimnasio Cancún', 'type' => 'ally', 'ally_id' => $ally->id, 'address' => 'Av. Tulum 1', 'active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $location = InventoryLocation::query()->where('name', 'Gimnasio Cancún')->firstOrFail();
    expect($location->type)->toBe(InventoryLocationType::Ally)
        ->and($location->ally_id)->toBe($ally->id)
        ->and($location->slug)->toBe('gimnasio-cancun');

    $this->actingAs($admin)->patch("/admin/inventario/ubicaciones/{$location->id}", [
        'name' => 'Gimnasio Cancún Centro', 'type' => 'ally', 'ally_id' => $ally->id, 'notes' => 'Mostrador', 'active' => true,
    ])->assertSessionHasNoErrors();
    expect($location->fresh()->name)->toBe('Gimnasio Cancún Centro');

    $variant = tagVariant();
    tagStock($variant, $location, 2, $admin);

    $this->actingAs($admin)->get("/admin/inventario/ubicaciones/{$location->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/locations/Show')
            ->where('stock.0.sku', $variant->sku)
            ->where('stock.0.on_hand', 2)
            ->where('stock.0.units_in_stock', 2)
            ->where('movements.0.type', 'receive'));

    $this->actingAs($admin)->get('/admin/inventario/ubicaciones?type=ally')
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/locations/Index')->has('locations.data', 1));

    // In use → deactivated, not deleted.
    $this->actingAs($admin)->delete("/admin/inventario/ubicaciones/{$location->id}");
    expect($location->fresh())->not->toBeNull()->and($location->fresh()->active)->toBeFalse();

    $empty = tagLocation();
    $this->actingAs($admin)->delete("/admin/inventario/ubicaciones/{$empty->id}")->assertRedirect('/admin/inventario/ubicaciones');
    expect(InventoryLocation::query()->find($empty->id))->toBeNull();
});

test('locations require inventory.manage', function () {
    $this->actingAs(tagStaff('sales_staff'))->get('/admin/inventario/ubicaciones')->assertForbidden();
    $this->actingAs(tagStaff('athlete'))->get('/admin/inventario/ubicaciones')->assertForbidden();
});

test('supplier CRUD; a supplier with lots is deactivated instead of deleted', function () {
    $admin = tagStaff();

    $this->actingAs($admin)->post('/admin/inventario/proveedores', [
        'name' => 'Fábrica Shenzhen', 'email' => 'ventas@fabrica.test', 'country' => 'China', 'lead_time_days' => 30, 'moq' => 500, 'status' => 'active',
    ])->assertSessionHasNoErrors();

    $supplier = Supplier::query()->where('name', 'Fábrica Shenzhen')->firstOrFail();

    $this->actingAs($admin)->get('/admin/inventario/proveedores?q=shenzhen')
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/suppliers/Index')->has('suppliers.data', 1));

    $this->actingAs($admin)->patch("/admin/inventario/proveedores/{$supplier->id}", [
        'name' => 'Fábrica Shenzhen 2', 'status' => 'active', 'email' => 'no-es-correo',
    ])->assertSessionHasErrors('email');

    $this->actingAs($admin)->patch("/admin/inventario/proveedores/{$supplier->id}", [
        'name' => 'Fábrica Shenzhen 2', 'status' => 'active',
    ])->assertSessionHasNoErrors();
    expect($supplier->fresh()->name)->toBe('Fábrica Shenzhen 2');

    InventoryLot::query()->create([
        'uuid' => (string) Str::uuid(), 'code' => 'LOT-T-1', 'product_variant_id' => tagVariant()->id,
        'supplier_id' => $supplier->id, 'quantity' => 1, 'status' => 'ordered',
    ]);

    $this->actingAs($admin)->delete("/admin/inventario/proveedores/{$supplier->id}");
    expect($supplier->fresh()->status)->toBe(SupplierStatus::Inactive);

    $unused = Supplier::query()->create(['name' => 'Sin lotes', 'status' => 'active']);
    $this->actingAs($admin)->delete("/admin/inventario/proveedores/{$unused->id}");
    expect(Supplier::query()->find($unused->id))->toBeNull();
});

test('suppliers: sales staff denied, view-only role cannot write', function () {
    $this->actingAs(tagStaff('sales_staff'))->get('/admin/inventario/proveedores')->assertForbidden();
    $this->actingAs(tagStaff('athlete'))->post('/admin/inventario/proveedores', ['name' => 'X', 'status' => 'active'])->assertForbidden();
});
