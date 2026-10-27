<?php

use App\Enums\DeliveryMethod;
use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Enums\SalesChannel;
use App\Models\Ally;
use App\Models\LegacyTag;
use App\Models\Order;

require_once __DIR__.'/helpers.php';

test('the orders board filters by channel and each card carries channel, ally and seller', function () {
    $admin = tagStaff();
    $ally = Ally::factory()->create(['name' => 'Box Norte']);
    $location = tagLocation(InventoryLocationType::Ally, $ally);
    $tags = tagStock(tagVariant(), $location, 1);
    $allyOrder = inPersonSale($location, [$tags[0]->public_code], $admin);
    Order::factory()->create(['sales_channel' => SalesChannel::Online]);

    $this->actingAs($admin)->get('/admin/orders?canal=ally')
        ->assertOk()
        ->assertInertia(function ($page) use ($allyOrder) {
            $orders = collect($page->toArray()['props']['board'])->flatMap(fn ($lane) => $lane['orders']);
            expect($orders)->toHaveCount(1)
                ->and($orders->first()['order_number'])->toBe($allyOrder->order_number)
                ->and($orders->first()['channel'])->toBe('ally')
                ->and($orders->first()['ally'])->toBe('Box Norte');

            return $page->where('filters.canal', 'ally')->has('channels', 4);
        });
});

test('order detail traces delivered units and staff can edit shipping and mark it delivered', function () {
    $admin = tagStaff();
    $location = tagLocation();
    $tags = tagStock(tagVariant(), $location, 2);
    $order = inPersonSale($location, [$tags[0]->public_code, $tags[1]->public_code], $admin);

    // Turn it into a shipped order whose pieces are still in transit.
    $order->update(['delivery_method' => DeliveryMethod::Shipping, 'delivered_at' => null]);
    LegacyTag::query()->where('order_id', $order->id)->update(['status' => LegacyTagStatus::Sold->value, 'delivered_at' => null]);

    $this->actingAs($admin)->get("/admin/orders/{$order->uuid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('units', 2)
            ->where('units.0.status', 'sold')
            ->where('order.channel', 'direct')
            ->where('canManage', true));

    $this->actingAs($admin)->patch("/admin/orders/{$order->uuid}/envio", [
        'delivery_method' => 'shipping', 'carrier' => 'Estafeta', 'tracking_number' => 'EST123', 'shipped_at' => now()->toDateString(),
    ])->assertRedirect();

    expect($order->fresh()->tracking_number)->toBe('EST123');

    $this->actingAs($admin)->post("/admin/orders/{$order->uuid}/entregado")->assertRedirect();

    expect($order->fresh()->delivered_at)->not->toBeNull()
        ->and(LegacyTag::query()->where('order_id', $order->id)->pluck('status')->unique()->all())->toBe([LegacyTagStatus::Delivered]);

    $this->actingAs($admin)->post("/admin/orders/{$order->uuid}/entregado")->assertSessionHasErrors('delivered');
});

test('orders.view alone cannot edit shipping', function () {
    $seller = tagStaff('sales_staff');
    $order = Order::factory()->create();

    $this->actingAs($seller)->patch("/admin/orders/{$order->uuid}/envio", ['carrier' => 'X'])->assertForbidden();
    $this->actingAs($seller)->post("/admin/orders/{$order->uuid}/entregado")->assertForbidden();
});
