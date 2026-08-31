<?php

use App\Actions\Integrations\ResolveEventDataSource;
use App\Enums\OrganizerDataSourceType;
use App\Enums\ProviderConnectionStatus;
use App\Exceptions\EventDataSourceNotConfiguredException;
use App\Models\Event;
use App\Models\EventEdition;
use App\Models\Organizer;
use App\Models\OrganizerDataSource;
use App\Models\ProviderConnection;
use Illuminate\Support\Str;

test('an edition without its own override inherits the Organizer default data source', function () {
    $organizer = Organizer::factory()->create();
    $connection = ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => 'generic_rest',
        'name' => 'Sports Timing MX',
        'base_url' => 'https://api.timingpartner.mx',
        'status' => ProviderConnectionStatus::Connected,
    ]);
    OrganizerDataSource::create([
        'organizer_id' => $organizer->id,
        'type' => OrganizerDataSourceType::Api,
        'provider_connection_id' => $connection->id,
        'active' => true,
    ]);
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $edition = EventEdition::factory()->create(['event_id' => $event->id]);

    $resolved = app(ResolveEventDataSource::class)->handle($edition);

    expect($resolved->type)->toBe(OrganizerDataSourceType::Api)
        ->and($resolved->providerConnection?->id)->toBe($connection->id)
        ->and($resolved->isOverride)->toBeFalse();
});

test("an edition's own override wins over the Organizer default", function () {
    $organizer = Organizer::factory()->create();
    OrganizerDataSource::create([
        'organizer_id' => $organizer->id,
        'type' => OrganizerDataSourceType::Api,
        'active' => true,
    ]);
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $edition = EventEdition::factory()->create([
        'event_id' => $event->id,
        'data_source_type' => OrganizerDataSourceType::Manual->value,
    ]);

    $resolved = app(ResolveEventDataSource::class)->handle($edition);

    expect($resolved->type)->toBe(OrganizerDataSourceType::Manual)
        ->and($resolved->isOverride)->toBeTrue();
});

test('an event with no Organizer and no override throws a clean domain exception', function () {
    $edition = EventEdition::factory()->create(['event_id' => Event::factory()->create(['organizer_id' => null])]);

    app(ResolveEventDataSource::class)->handle($edition);
})->throws(EventDataSourceNotConfiguredException::class);

test('a manual event can be created with no Organizer at all', function () {
    $event = Event::factory()->create(['organizer_id' => null]);

    expect($event->organizer_id)->toBeNull();
});
