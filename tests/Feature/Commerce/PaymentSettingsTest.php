<?php

use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Commerce\Payments\OpenPayClientFactory;
use App\Services\Commerce\Payments\PaymentSettings;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * /admin/configuracion/pagos — DB values override .env, the private key is
 * encrypted at rest and never reaches the browser.
 */
function paymentsAdmin(): User
{
    paymentPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo(['dashboard.admin.view', 'payments.configure']);

    return $user;
}

function paymentPermissions(): void
{
    Permission::findOrCreate('dashboard.admin.view', 'web');
    Permission::findOrCreate('payments.configure', 'web');
}

function validPaymentPayload(array $overrides = []): array
{
    return [
        'default_gateway' => 'openpay',
        'openpay_merchant_id' => 'mabc123def456',
        'openpay_public_key' => 'pk_public_1234',
        'openpay_private_key' => 'sk_super_secret_9876',
        'openpay_country' => 'MX',
        'openpay_production_mode' => false,
        'openpay_use_3d_secure' => true,
        ...$overrides,
    ];
}

test('an admin with payments.configure saves OpenPay keys; the private key is encrypted at rest', function () {
    $admin = paymentsAdmin();

    $this->actingAs($admin)
        ->put('/admin/configuracion/pagos', validPaymentPayload())
        ->assertRedirect('/admin/configuracion/pagos')
        ->assertSessionHasNoErrors();

    $raw = DB::table('payment_settings')->first();

    expect($raw->openpay_private_key)->not->toBe('sk_super_secret_9876')
        ->and($raw->openpay_private_key)->not->toContain('sk_super_secret')
        ->and($raw->openpay_private_key_last4)->toBe('9876')
        ->and(PaymentSetting::query()->first()->openpay_private_key)->toBe('sk_super_secret_9876');

    // Applied to the runtime config right away.
    expect(config('finisher.payments.default_gateway'))->toBe('openpay')
        ->and(config('finisher.payments.openpay.merchant_id'))->toBe('mabc123def456')
        ->and(config('finisher.payments.openpay.private_key'))->toBe('sk_super_secret_9876')
        ->and(config('finisher.payments.openpay.use_3d_secure'))->toBeTrue();

    // Audit trail without the secret.
    $activity = Activity::query()->where('log_name', 'payments')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and(json_encode($activity->properties))->not->toContain('sk_super_secret_9876')
        ->and(json_encode($activity->properties, JSON_UNESCAPED_UNICODE))->toContain('••••9876');
});

test('the page never sends the private key to the browser — only a masked hint', function () {
    $admin = paymentsAdmin();
    $this->actingAs($admin)->put('/admin/configuracion/pagos', validPaymentPayload());

    $response = $this->actingAs($admin)->get('/admin/configuracion/pagos');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/settings/Payments')
        ->where('settings.openpay.private_key_hint', '••••9876')
        ->where('settings.openpay.private_key_source', 'panel')
        ->where('settings.openpay.configured', true)
        ->where('settings.openpay.webhook_url', route('api.webhooks.openpay'))
        ->missing('settings.openpay.private_key'));

    expect($response->getContent())->not->toContain('sk_super_secret_9876');
});

test('a blank private key keeps the saved one', function () {
    $admin = paymentsAdmin();
    $this->actingAs($admin)->put('/admin/configuracion/pagos', validPaymentPayload());

    $this->actingAs($admin)
        ->put('/admin/configuracion/pagos', validPaymentPayload(['openpay_private_key' => '', 'openpay_public_key' => 'pk_new_5555']))
        ->assertSessionHasNoErrors();

    $row = PaymentSetting::query()->first();
    expect($row->openpay_private_key)->toBe('sk_super_secret_9876')
        ->and($row->openpay_public_key)->toBe('pk_new_5555');
});

test('DB values override .env when the app boots', function () {
    config(['finisher.payments.openpay.merchant_id' => 'from_env', 'finisher.payments.default_gateway' => 'stripe']);

    PaymentSetting::create([
        'default_gateway' => 'openpay',
        'openpay_merchant_id' => 'from_panel',
        'openpay_private_key' => 'sk_panel_0001',
        'openpay_private_key_last4' => '0001',
    ]);

    $settings = app(PaymentSettings::class);
    $settings->forget();
    $settings->applyToConfig();

    expect(config('finisher.payments.default_gateway'))->toBe('openpay')
        ->and(config('finisher.payments.openpay.merchant_id'))->toBe('from_panel')
        ->and(config('finisher.payments.openpay.private_key'))->toBe('sk_panel_0001');
});

test('choosing OpenPay requires complete keys', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.public_key' => null, 'finisher.payments.openpay.private_key' => null]);

    $this->actingAs(paymentsAdmin())
        ->put('/admin/configuracion/pagos', validPaymentPayload(['openpay_merchant_id' => '', 'openpay_private_key' => '']))
        ->assertSessionHasErrors(['openpay_merchant_id', 'openpay_private_key']);

    expect(PaymentSetting::query()->count())->toBe(0);
});

test('keys with the wrong prefix are rejected', function () {
    $this->actingAs(paymentsAdmin())
        ->put('/admin/configuracion/pagos', validPaymentPayload(['openpay_public_key' => 'sk_oops', 'openpay_private_key' => 'pk_oops']))
        ->assertSessionHasErrors(['openpay_public_key', 'openpay_private_key']);
});

test('admins without payments.configure cannot see or change payment settings', function () {
    paymentPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo('dashboard.admin.view');

    $this->actingAs($user)->get('/admin/configuracion/pagos')->assertForbidden();
    $this->actingAs($user)->put('/admin/configuracion/pagos', validPaymentPayload())->assertForbidden();
    $this->actingAs($user)->postJson('/admin/configuracion/pagos/probar')->assertForbidden();

    expect(PaymentSetting::query()->count())->toBe(0);
});

test('"Probar conexión" calls OpenPay merchant info through the client factory (no network)', function () {
    config([
        'finisher.payments.openpay.merchant_id' => 'mtest',
        'finisher.payments.openpay.private_key' => 'sk_test',
    ]);

    $fake = new class extends OpenPayClientFactory
    {
        public array $calls = [];

        public function make(string $merchantId, string $privateKey, string $country, bool $productionMode): mixed
        {
            $this->calls[] = compact('merchantId', 'privateKey', 'country', 'productionMode');

            return new class
            {
                public function getMerchantInfo(): object
                {
                    return (object) ['name' => 'Finisher Legacy Sandbox'];
                }
            };
        }
    };
    app()->instance(OpenPayClientFactory::class, $fake);
    app()->forgetInstance(PaymentSettings::class);

    $this->actingAs(paymentsAdmin())
        ->postJson('/admin/configuracion/pagos/probar')
        ->assertOk()
        ->assertJson(['ok' => true, 'message' => 'Conexión correcta con OpenPay (Finisher Legacy Sandbox).']);

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['productionMode'])->toBeFalse();
});

test('"Probar conexión" reports missing keys without calling OpenPay', function () {
    config(['finisher.payments.openpay.merchant_id' => null, 'finisher.payments.openpay.private_key' => null]);

    $this->actingAs(paymentsAdmin())
        ->postJson('/admin/configuracion/pagos/probar')
        ->assertOk()
        ->assertJson(['ok' => false]);
});

test('the OpenPay verification handshake code is shown to payment admins only', function () {
    $this->postJson('/api/webhooks/openpay', ['type' => 'verification', 'verification_code' => 'Kx91Ab'])->assertNoContent();

    $this->actingAs(paymentsAdmin())->get('/admin/configuracion/pagos')
        ->assertInertia(fn ($page) => $page->where('settings.openpay.webhook_verification.code', 'Kx91Ab'));
});
