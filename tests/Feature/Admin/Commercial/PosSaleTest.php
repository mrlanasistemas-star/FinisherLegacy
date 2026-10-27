<?php

use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Enums\SalesChannel;
use App\Models\Ally;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Models\User;

require_once __DIR__.'/helpers.php';

test('sales_staff sells scanned tags from the POS and sees the success screen', function () {
    $seller = tagStaff('sales_staff');
    $variant = tagVariant(39900);
    $stand = tagLocation(InventoryLocationType::Stand);
    $tags = tagStock($variant, $stand, 3);

    $this->actingAs($seller)->get('/admin/venta-rapida')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pos/Index')
            ->where('mode', 'admin')
            ->where("stock.{$stand->id}.0.available", 3)
            ->where("stock.{$stand->id}.0.unit_price_minor", 39900));

    $this->actingAs($seller)->post('/admin/venta-rapida', [
        'location_id' => $stand->id,
        'codes' => ['https://example.test/t/'.$tags[0]->public_code.'?src=qr'],
        'quantities' => [$variant->id => 1],
        'payment_method' => 'cash',
        'customer' => ['email' => 'ana@example.test'],
        'started_at' => now()->subSeconds(45)->toIso8601String(),
    ])->assertRedirect('/admin/venta-rapida');

    $order = Order::query()->latest('id')->firstOrFail();

    expect($order->sales_channel)->toBe(SalesChannel::OwnEvent)
        ->and($order->seller_user_id)->toBe($seller->id)
        ->and($order->total_minor)->toBe(79800)
        ->and($order->sale_started_at)->not->toBeNull()
        ->and(LegacyTag::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and($tags[0]->fresh()->status)->toBe(LegacyTagStatus::Delivered);

    $this->actingAs($seller)->get('/admin/venta-rapida')
        ->assertInertia(fn ($page) => $page->where('lastSale.order_number', $order->order_number)->has('lastSale.units', 2));
});

test('the client sale start time is clamped to at most one hour ago', function () {
    $seller = tagStaff('sales_staff');
    $stand = tagLocation(InventoryLocationType::Stand);
    $tags = tagStock(tagVariant(), $stand, 1);

    $this->actingAs($seller)->post('/admin/venta-rapida', [
        'location_id' => $stand->id,
        'codes' => [$tags[0]->public_code],
        'payment_method' => 'mercado_pago_terminal',
        'payment_reference' => 'TX-1',
        'started_at' => now()->subDays(3)->toIso8601String(),
    ])->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();
    expect((int) $order->sale_started_at->diffInSeconds($order->created_at))->toBeLessThanOrEqual(3601);
});

test('a sold piece or one from another location gives a clear error', function () {
    $seller = tagStaff('sales_staff');
    $variant = tagVariant();
    $stand = tagLocation(InventoryLocationType::Stand);
    $warehouse = tagLocation();
    $standTags = tagStock($variant, $stand, 1);
    $warehouseTags = tagStock($variant, $warehouse, 1);

    $this->actingAs($seller)->getJson("/admin/venta-rapida/pieza?location_id={$stand->id}&code={$warehouseTags[0]->public_code}")
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'no en '.$stand->name));

    $this->actingAs($seller)->getJson("/admin/venta-rapida/pieza?location_id={$stand->id}&code={$standTags[0]->public_code}")
        ->assertOk()
        ->assertJsonPath('variant_id', $variant->id);

    inPersonSale($stand, [$standTags[0]->public_code], $seller);

    $this->actingAs($seller)->post('/admin/venta-rapida', [
        'location_id' => $stand->id,
        'codes' => [$standTags[0]->public_code],
        'payment_method' => 'cash',
    ])->assertSessionHasErrors('sale');
});

test('an athlete cannot open or use the POS', function () {
    tagStaff();
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get('/admin/venta-rapida')->assertForbidden();
    $this->actingAs($athlete)->post('/admin/venta-rapida', ['location_id' => 1, 'payment_method' => 'cash'])->assertForbidden();
});

test('an ally user sells only from their own ally locations', function () {
    tagStaff();
    $variant = tagVariant();
    $allyA = Ally::factory()->create();
    $allyB = Ally::factory()->create();
    $locationA = tagLocation(InventoryLocationType::Ally, $allyA);
    $locationB = tagLocation(InventoryLocationType::Ally, $allyB);
    $tagsA = tagStock($variant, $locationA, 2);
    $tagsB = tagStock($variant, $locationB, 2);
    $user = allyUser($allyA);

    // The admin POS is not theirs.
    $this->actingAs($user)->get('/admin/venta-rapida')->assertForbidden();

    $this->actingAs($user)->get('/aliado/venta-rapida')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('mode', 'ally')
            ->has('locations', 1)
            ->where('locations.0.id', $locationA->id)
            ->missing("stock.{$locationB->id}"));

    // IDOR: ally B's location id → 403, nothing sold.
    $this->actingAs($user)->post('/aliado/venta-rapida', [
        'location_id' => $locationB->id,
        'codes' => [$tagsB[0]->public_code],
        'payment_method' => 'cash',
    ])->assertForbidden();
    $this->actingAs($user)->getJson("/aliado/venta-rapida/pieza?location_id={$locationB->id}&code={$tagsB[0]->public_code}")->assertForbidden();

    // Ally B's piece scanned at ally A's location → refused by the action.
    $this->actingAs($user)->post('/aliado/venta-rapida', [
        'location_id' => $locationA->id,
        'codes' => [$tagsB[0]->public_code],
        'payment_method' => 'cash',
    ])->assertSessionHasErrors('sale');

    expect($tagsB[0]->fresh()->status)->toBe(LegacyTagStatus::InStock);

    $this->actingAs($user)->post('/aliado/venta-rapida', [
        'location_id' => $locationA->id,
        'codes' => [$tagsA[0]->public_code],
        'payment_method' => 'cash',
    ])->assertRedirect('/aliado/venta-rapida');

    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->sales_channel)->toBe(SalesChannel::Ally)
        ->and($order->ally_id)->toBe($allyA->id)
        ->and($order->seller_user_id)->toBe($user->id);
});
