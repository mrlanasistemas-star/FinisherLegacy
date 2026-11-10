<?php

use App\Actions\Notifications\AlertIfPaymentGatewayUnconfigured;
use App\Actions\Notifications\NotifyAdmins;
use App\Actions\Training\ManageCheerLinks;
use App\Enums\AdminAlertCategory;
use App\Enums\CheerReportReason;
use App\Enums\ContactMessageType;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Events\Commerce\OrderPaid;
use App\Events\Commerce\OrderRefunded;
use App\Jobs\SendPushBatchJob;
use App\Models\CheerMessageReport;
use App\Models\ContactMessage;
use App\Models\InventoryLevel;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Services\Commerce\InventoryService;
use App\Support\Notifications\LowStockMonitor;
use App\Support\Notifications\NotificationPreferences;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Admin alerts ("que el admin en web le avise de todo",
 * docs/renovacion/notificaciones.md §Alertas admin): bell rows for admins
 * allowed to see the category, email/push per their preferences, throttling,
 * and the topbar bell / preferences endpoints.
 */
beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    config(['services.push.provider' => 'expo']);
    Mail::fake();
    Queue::fake([SendPushBatchJob::class]);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
    PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => $this->admin->id, 'platform' => 'android',
        'provider' => 'expo', 'token' => 'ExponentPushToken[admin]', 'active' => true,
    ]);
    $this->customer = User::factory()->create(['status' => UserStatus::Active]);
});

function adminAlerts(User $user)
{
    return $user->notifications()->where('type', AdminAlert::class);
}

test('a paid order alerts admins (bell + push) but never plain customers', function () {
    $order = Order::factory()->create(['user_id' => $this->customer->id]);

    OrderPaid::dispatch($order);

    $alert = adminAlerts($this->admin)->sole();
    expect($alert->data['category'])->toBe('ventas')
        ->and($alert->data['type'])->toBe('admin.order_paid')
        ->and($alert->data['action_url'])->toBe("/admin/orders/{$order->uuid}")
        ->and(adminAlerts($this->customer)->count())->toBe(0);

    Queue::assertPushed(SendPushBatchJob::class, fn (SendPushBatchJob $job) => ($job->message->data['admin_category'] ?? null) === 'ventas');
});

test('a refund alerts Ventas with the refunded amount', function () {
    $order = Order::factory()->create();

    OrderRefunded::dispatch($order, 25000, false);

    expect(adminAlerts($this->admin)->sole()->data['message'])->toContain('$250.00 MXN');
});

test('email follows each admin preference (ventas default off, soporte default on)', function () {
    Notification::fake();
    $notify = app(NotifyAdmins::class);

    $notify->handle(AdminAlertCategory::Ventas, 'x', 'Ventas', 'm');
    Notification::assertSentTo($this->admin, AdminAlert::class, fn (AdminAlert $n, array $channels) => ! in_array('mail', $channels, true));

    $notify->handle(AdminAlertCategory::Soporte, 'y', 'Soporte', 'm');
    Notification::assertSentTo($this->admin, AdminAlert::class, fn (AdminAlert $n, array $channels) => $n->category === AdminAlertCategory::Soporte && in_array('mail', $channels, true));

    app(NotificationPreferences::class)->updateAdmin($this->admin, ['soporte' => ['email' => false, 'push' => false]]);
    Queue::fake([SendPushBatchJob::class]);
    $notify->handle(AdminAlertCategory::Soporte, 'z', 'Soporte 2', 'm');
    Queue::assertNotPushed(SendPushBatchJob::class);
});

test('only admins holding the category permission receive it', function () {
    $support = User::factory()->create(['status' => UserStatus::Active]);
    $support->givePermissionTo('support.view');

    $count = app(NotifyAdmins::class)->handle(AdminAlertCategory::Ventas, 'x', 'Ventas', 'm');

    expect(adminAlerts($support)->count())->toBe(0)
        ->and(adminAlerts($this->admin)->count())->toBe(1)
        ->and($count)->toBeGreaterThanOrEqual(1);
});

test('throttled alerts fire once per window', function () {
    $notify = app(NotifyAdmins::class);

    $notify->handle(AdminAlertCategory::Pagos, 'webhook_failed', 'a', 'b', throttleKey: 'k', throttleMinutes: 30);
    $notify->handle(AdminAlertCategory::Pagos, 'webhook_failed', 'a', 'b', throttleKey: 'k', throttleMinutes: 30);

    expect(adminAlerts($this->admin)->count())->toBe(1);
});

test('an account deletion request alerts Soporte', function () {
    ContactMessage::create([
        'name' => 'Ana', 'email' => 'ana@example.test', 'type' => ContactMessageType::AccountDeletion,
        'message' => 'Quiero borrar mi cuenta', 'status' => 'new',
    ]);

    $alert = adminAlerts($this->admin)->sole();
    expect($alert->data['type'])->toBe('admin.account_deletion_requested')
        ->and($alert->data['level'])->toBe('warning');
});

test('a reported cheer message alerts Comunidad without exposing the message', function () {
    $link = app(ManageCheerLinks::class)->create($this->customer, 'Mi maratón', null);

    $report = CheerMessageReport::create([
        'cheer_link_id' => $link->id,
        'reporter_user_id' => $this->customer->id,
        'reason' => CheerReportReason::Harassment,
        'details' => 'Texto privado del reporte',
        'status' => ReportStatus::Open,
    ]);

    $alert = adminAlerts($this->admin)->sole();
    expect($alert->data['category'])->toBe('comunidad')
        ->and($alert->data['type'])->toBe('admin.cheer_report_created')
        ->and($alert->data['level'])->toBe('warning')
        ->and($alert->data['action_url'])->toBe('/admin/entrenamientos')
        ->and($alert->data['metadata']['cheer_report_uuid'])->toBe($report->uuid)
        ->and(json_encode($alert->data))->not->toContain('Texto privado')
        ->and(adminAlerts($this->customer)->count())->toBe(0);
});

test('a webhook replying 401 raises a Pagos alert, throttled', function () {
    config(['finisher.payments.openpay.webhook_user' => 'u', 'finisher.payments.openpay.webhook_password' => 'p']);

    $this->postJson('/api/webhooks/openpay', ['type' => 'charge.succeeded'])->assertStatus(401);
    $this->postJson('/api/webhooks/openpay', ['type' => 'charge.succeeded'])->assertStatus(401);

    $alerts = adminAlerts($this->admin)->get();
    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->data['category'])->toBe('pagos');
});

test('stock movements alert low and out of stock once and re-arm after restock', function () {
    $product = Product::factory()->create(['tracks_inventory' => true, 'minimum_stock' => 3]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $location = app(InventoryService::class)->defaultLocation();
    $level = InventoryLevel::create(['product_variant_id' => $variant->id, 'inventory_location_id' => $location->id, 'quantity_on_hand' => 2, 'quantity_reserved' => 0]);
    $monitor = app(LowStockMonitor::class);

    expect($monitor->checkVariant($variant->id))->toBe('low');
    $monitor->checkVariant($variant->id);
    expect(adminAlerts($this->admin)->where('data->type', 'admin.low_stock')->count())->toBe(1);

    $level->update(['quantity_on_hand' => 0]);
    expect($monitor->checkVariant($variant->id))->toBe('out');

    $level->update(['quantity_on_hand' => 50]);
    expect($monitor->checkVariant($variant->id))->toBe('ok');

    $level->update(['quantity_on_hand' => 1]);
    $monitor->checkVariant($variant->id);
    expect(adminAlerts($this->admin)->where('data->type', 'admin.low_stock')->count())->toBe(2)
        ->and(adminAlerts($this->admin)->where('data->type', 'admin.out_of_stock')->count())->toBe(1);
});

test('the scheduled stock digest sends one summary alert', function () {
    $product = Product::factory()->create(['tracks_inventory' => true, 'minimum_stock' => 5]);
    ProductVariant::factory()->count(2)->create(['product_id' => $product->id]);

    $this->artisan('inventory:check-low-stock')->assertSuccessful();
    $this->artisan('inventory:check-low-stock')->assertSuccessful();

    expect(adminAlerts($this->admin)->where('data->type', 'admin.stock_digest')->count())->toBe(1);
});

test('an unconfigured OpenPay gateway raises a daily Pagos alert', function () {
    config(['finisher.payments.default_gateway' => 'openpay', 'finisher.payments.openpay.merchant_id' => null]);

    expect(app(AlertIfPaymentGatewayUnconfigured::class)->handle())->toBeTrue()
        ->and(adminAlerts($this->admin)->where('data->type', 'admin.gateway_not_configured')->count())->toBe(1);
});

test('the admin bell lists only admin alerts and marks them read', function () {
    app(NotifyAdmins::class)->handle(AdminAlertCategory::Soporte, 'x', 'Caso nuevo', 'Detalle', '/admin/soporte');
    $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\AthleteAlert', 'data' => ['title' => 'Personal']]);

    $summary = $this->actingAs($this->admin)->getJson('/admin/alertas/resumen')->assertOk();
    expect($summary->json('unread_count'))->toBe(1)
        ->and($summary->json('latest.0.title'))->toBe('Caso nuevo')
        ->and($summary->json('latest.0.category_label'))->toBe('Soporte');

    $this->actingAs($this->admin)->postJson('/admin/alertas/'.$summary->json('latest.0.id').'/leer')->assertJsonPath('unread_count', 0);

    // The personal center never shows the admin alert.
    $this->actingAs($this->admin)->getJson('/notifications/summary')->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'latest');

    $this->actingAs($this->customer)->getJson('/admin/alertas/resumen')->assertForbidden();
});

test('admins choose email/push per category on the preferences page', function () {
    $this->actingAs($this->admin)->get('/admin/alertas/preferencias')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/AlertPreferences')->has('categories', 7));

    $this->actingAs($this->admin)->put('/admin/alertas/preferencias', ['preferences' => [
        ['key' => 'ventas', 'email' => true, 'push' => false],
    ]])->assertRedirect();

    $prefs = app(NotificationPreferences::class);
    expect($prefs->allows($this->admin, AdminAlertCategory::Ventas, 'email'))->toBeTrue()
        ->and($prefs->allows($this->admin, AdminAlertCategory::Ventas, 'push'))->toBeFalse()
        ->and($prefs->allows($this->admin, AdminAlertCategory::Ventas, 'in_app'))->toBeTrue();
});
