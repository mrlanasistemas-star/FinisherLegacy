<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

test('the platform named limiters are registered', function (string $name) {
    expect(RateLimiter::limiter($name))->not->toBeNull();
})->with(['nfc-resolve', 'tracking-ingest', 'live-view', 'community-write', 'uploads']);

test('every /api route carries the baseline api throttle', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '120');
});

test('payment webhooks use their own generous per-IP bucket', function () {
    $response = $this->postJson('/api/webhooks/stripe', []);

    expect($response->headers->get('X-RateLimit-Limit'))->toBe('600');
});

test('live-view throttles one IP + token after 30 requests a minute', function () {
    Route::get('/_platform-test/live/{token}', fn () => 'ok')->middleware('throttle:live-view');

    for ($i = 0; $i < 30; $i++) {
        $this->get('/_platform-test/live/tok-a')->assertOk();
    }

    $this->get('/_platform-test/live/tok-a')->assertTooManyRequests();
    // Another share token from the same IP has its own bucket.
    $this->get('/_platform-test/live/tok-b')->assertOk();
});

test('nfc-resolve throttles a single IP after 30 requests a minute', function () {
    Route::get('/_platform-test/n/{token}', fn () => 'ok')->middleware('throttle:nfc-resolve');

    for ($i = 0; $i < 30; $i++) {
        $this->get('/_platform-test/n/t'.$i)->assertOk();
    }

    $this->get('/_platform-test/n/other')->assertTooManyRequests();
});
