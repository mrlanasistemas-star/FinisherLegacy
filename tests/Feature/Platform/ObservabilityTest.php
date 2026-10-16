<?php

use App\Exceptions\PaymentAmountMismatchException;
use App\Models\User;
use App\Support\Observability\OperationalLog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

test('web responses carry a generated X-Request-ID', function () {
    $response = $this->get('/');

    $id = $response->headers->get('X-Request-ID');
    expect($id)->not->toBeEmpty()->and(Str::isUuid($id))->toBeTrue();
});

test('a sane client X-Request-ID is reused and added to the log context', function () {
    $this->withHeaders(['X-Request-ID' => 'mobile-abc-12345'])
        ->getJson('/api/v1/health')
        ->assertHeader('X-Request-ID', 'mobile-abc-12345');

    expect(Context::get('request_id'))->toBe('mobile-abc-12345');
});

test('a malicious X-Request-ID is replaced, never echoed', function (string $bad) {
    $response = $this->withHeaders(['X-Request-ID' => $bad])->getJson('/api/v1/health');

    expect($response->headers->get('X-Request-ID'))->not->toBe($bad)
        ->and(Str::isUuid($response->headers->get('X-Request-ID')))->toBeTrue();
})->with([
    'too short' => ['abc'],
    'newline injection' => ["abcdefgh\nFAKE LOG LINE"],
    'too long' => [str_repeat('a', 300)],
]);

test('payment amount mismatches are written to the payments log channel', function () {
    $channel = Mockery::mock();
    $channel->shouldReceive('error')->once()->with('payment.incident', Mockery::on(
        fn ($context) => $context['exception'] === PaymentAmountMismatchException::class,
    ));
    Log::shouldReceive('channel')->with('payments')->andReturn($channel);

    OperationalLog::reportPaymentIncident(new PaymentAmountMismatchException, Request::create('/somewhere'));
});

test('non-payment exceptions outside webhooks are not payment incidents', function () {
    Log::shouldReceive('channel')->never();

    OperationalLog::reportPaymentIncident(new RuntimeException('nope'), Request::create('/dashboard'));
});

test('horizon is closed to guests and regular users', function () {
    $this->get('/horizon')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
});

test('only super_admin passes the viewHorizon gate', function () {
    $this->seed(RolePermissionSeeder::class);
    $super = User::factory()->create();
    $super->assignRole('super_admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect(Gate::forUser($super)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewHorizon'))->toBeFalse();
});
