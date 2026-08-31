<?php

use App\Enums\ProviderConnectionStatus;
use App\Exceptions\ProviderConnectionFailedException;
use App\Models\ProviderConnection;
use App\Services\Integrations\Providers\GenericRestEventProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function makeGenericRestConnection(array $settings = [], ?string $credentials = null): ProviderConnection
{
    return ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => 'generic_rest',
        'name' => 'Sports Timing MX',
        'base_url' => 'https://api.timingpartner.mx',
        'credentials' => $credentials,
        'settings' => $settings,
        'status' => ProviderConnectionStatus::Untested,
    ]);
}

test('testConnection succeeds on a 2xx response and never leaks credentials in the message', function () {
    Http::fake(['api.timingpartner.mx/*' => Http::response(['ok' => true], 200)]);

    $connection = makeGenericRestConnection(
        settings: ['auth_type' => 'bearer'],
        credentials: 'super-secret-token',
    );

    $result = app(GenericRestEventProvider::class)->testConnection($connection);

    expect($result->success)->toBeTrue()
        ->and(json_encode($result))->not->toContain('super-secret-token');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer super-secret-token'));
});

test('testConnection fails cleanly on a non-2xx response', function () {
    Http::fake(['api.timingpartner.mx/*' => Http::response(['error' => 'nope'], 500)]);

    $connection = makeGenericRestConnection();

    $result = app(GenericRestEventProvider::class)->testConnection($connection);

    expect($result->success)->toBeFalse();
});

test('fetchParticipants maps configured field paths into canonical DTOs and paginates by offset', function () {
    Http::fake([
        'api.timingpartner.mx/events/EVT-1/participants*' => Http::response([
            'data' => [
                ['runner' => ['bib' => '101', 'first' => 'Ana', 'last' => 'López']],
                ['runner' => ['bib' => '102', 'first' => 'Juan', 'last' => 'Pérez']],
            ],
        ], 200),
    ]);

    $connection = makeGenericRestConnection(settings: [
        'auth_type' => 'none',
        'participants_endpoint' => '/events/{external_event_id}/participants',
        'participants_root' => 'data',
        'participant_field_mapping' => [
            'bib_number' => 'runner.bib',
            'first_name' => 'runner.first',
            'last_name' => 'runner.last',
        ],
    ]);

    $page = app(GenericRestEventProvider::class)->fetchParticipants($connection, 'EVT-1', null, 2);

    expect($page->items)->toHaveCount(2)
        ->and($page->items[0]->bibNumber)->toBe('101')
        ->and($page->items[0]->firstName)->toBe('Ana')
        ->and($page->hasMore)->toBeTrue()
        ->and($page->nextCursor)->toBe('2');
});

test('fetchResults maps configured field paths', function () {
    Http::fake([
        'api.timingpartner.mx/events/EVT-1/results*' => Http::response([
            'items' => [
                ['id' => 'P-1', 'timing' => ['net' => '03:15:00']],
            ],
        ], 200),
    ]);

    $connection = makeGenericRestConnection(settings: [
        'results_endpoint' => '/events/{external_event_id}/results',
        'results_root' => 'items',
        'result_field_mapping' => [
            'external_participant_id' => 'id',
            'official_time' => 'timing.net',
        ],
    ]);

    $page = app(GenericRestEventProvider::class)->fetchResults($connection, 'EVT-1', null, 50);

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]->externalParticipantId)->toBe('P-1')
        ->and($page->items[0]->officialTime)->toBe('03:15:00')
        ->and($page->hasMore)->toBeFalse();
});

test('fetchResults throws a clean domain exception when the endpoint is not configured', function () {
    $connection = makeGenericRestConnection();

    app(GenericRestEventProvider::class)->fetchResults($connection, 'EVT-1', null, 50);
})->throws(ProviderConnectionFailedException::class);
