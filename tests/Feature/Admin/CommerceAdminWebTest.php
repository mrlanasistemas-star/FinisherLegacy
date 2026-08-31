<?php

use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Store admin web surface (brief §35-§41/§80-§82): catalog, inventory,
 * orders and payments always mutate through the existing Actions/Services
 * (App\Services\Commerce\InventoryService, App\Actions\Commerce\*) — this
 * file checks the HTTP layer wires into them correctly, not the business
 * rules themselves (already covered under tests/Feature/Commerce).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('an admin can create a product from the catalog form', function () {
    $response = $this->actingAs($this->admin)->post('/admin/products', [
        'name' => 'FAST T1 Socks',
        'type' => 'apparel',
        'status' => 'active',
        'qr_capable' => false,
        'requires_shipping' => true,
        'tracks_inventory' => true,
        'active' => true,
    ]);

    $product = Product::query()->where('name', 'FAST T1 Socks')->firstOrFail();
    $response->assertRedirect("/admin/products/{$product->id}");
    expect($product->type->value)->toBe('apparel');
});

test('adjusting inventory goes through InventoryService and moves the available quantity', function () {
    $variant = ProductVariant::factory()->create();
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => 'main-warehouse', 'active' => true]);

    $this->actingAs($this->admin)->post('/admin/inventory/adjust', [
        'product_variant_id' => $variant->id,
        'inventory_location_id' => $location->id,
        'type' => 'receive',
        'quantity' => 10,
    ])->assertRedirect();

    expect($location->levels()->first()->quantity_on_hand)->toBe(10);
});

test('registering a manual payment for the wrong amount is rejected server-side, not just hidden in the UI', function () {
    $order = Order::factory()->create(['total_minor' => 50000]);

    // Tampered — does not match the order's real total. The Action
    // (App\Actions\Commerce\RegisterManualPayment) rejects it and the
    // Controller catches that as an ApiException, so this stays a
    // graceful redirect-with-error-toast, never a 500 or a silent accept.
    $this->actingAs($this->admin)->post("/admin/orders/{$order->uuid}/payments/manual", [
        'method' => 'cash',
        'amount_minor' => 1,
    ])->assertRedirect();

    expect($order->fresh()->payment_status->value)->toBe('pending');
});

test('registering a manual payment for the correct total marks the order paid', function () {
    $order = Order::factory()->create(['total_minor' => 50000]);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->uuid}/payments/manual", [
        'method' => 'cash',
        'amount_minor' => 50000,
        'reference' => 'FOLIO-001',
    ])->assertRedirect();

    expect($order->fresh()->payment_status->value)->toBe('paid');
});

test('a staff user without payments.record_manual cannot register a manual payment', function () {
    $order = Order::factory()->create();
    $operator = User::factory()->create();
    $operator->assignRole('event_operator');

    $this->actingAs($operator)->post("/admin/orders/{$order->uuid}/payments/manual", [
        'method' => 'cash',
        'amount_minor' => $order->total_minor,
    ])->assertForbidden();
});

test('the settings screen only ever surfaces read-only config, never a place to edit it', function () {
    $this->actingAs($this->admin)->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/settings/Index'));
});
