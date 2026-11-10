<?php

use App\Actions\Integrations\IngestEventResult;
use App\Exceptions\Integrations\ParticipantNotFoundException;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventRace;
use App\Models\ExternalParticipantMapping;
use App\Models\Plate;
use App\Models\ProviderConnection;
use App\Services\Integrations\EventIngestionService;
use App\Support\Integrations\ExternalParticipantData;
use App\Support\Integrations\ExternalResultData;
use Illuminate\Support\Str;

function integrationConnection(): ProviderConnection
{
    return ProviderConnection::create([
        'uuid' => (string) Str::uuid(),
        'provider_key' => 'mock',
        'name' => 'Mock',
        'status' => 'connected',
        'settings' => [],
    ]);
}

test('the same external athlete id across two editions resolves to one Athlete', function () {
    $connection = integrationConnection();
    $editionA = EventEdition::factory()->create();
    $raceA = EventRace::factory()->create(['event_edition_id' => $editionA->id]);
    $editionB = EventEdition::factory()->create();
    $raceB = EventRace::factory()->create(['event_edition_id' => $editionB->id]);

    $service = app(EventIngestionService::class);

    $participantA = $service->ingestParticipant(
        ExternalParticipantData::fromArray([
            'external_participant_id' => 'P-A', 'external_athlete_id' => 'ATH-SHARED',
            'bib_number' => '22', 'first_name' => 'Zuriel', 'last_name' => 'Ávila',
        ]),
        $editionA, $raceA, $connection, 'api',
    );

    $participantB = $service->ingestParticipant(
        ExternalParticipantData::fromArray([
            'external_participant_id' => 'P-B', 'external_athlete_id' => 'ATH-SHARED',
            'bib_number' => '991', 'first_name' => 'Zuriel', 'last_name' => 'Ávila',
        ]),
        $editionB, $raceB, $connection, 'api',
    );

    expect($participantA->athlete_id)->not->toBeNull()
        ->and($participantA->athlete_id)->toBe($participantB->athlete_id)
        ->and(Athlete::count())->toBe(1);
});

test('two participants sharing a name but different external athlete ids resolve to two Athletes', function () {
    $connection = integrationConnection();
    $edition = EventEdition::factory()->create();
    $race = EventRace::factory()->create(['event_edition_id' => $edition->id]);
    $service = app(EventIngestionService::class);

    $first = $service->ingestParticipant(
        ExternalParticipantData::fromArray([
            'external_participant_id' => 'P-1', 'external_athlete_id' => 'ATH-1',
            'bib_number' => '1', 'first_name' => 'Juan', 'last_name' => 'Pérez',
        ]),
        $edition, $race, $connection, 'api',
    );

    $second = $service->ingestParticipant(
        ExternalParticipantData::fromArray([
            'external_participant_id' => 'P-2', 'external_athlete_id' => 'ATH-2',
            'bib_number' => '2', 'first_name' => 'Juan', 'last_name' => 'Pérez',
        ]),
        $edition, $race, $connection, 'api',
    );

    expect($first->athlete_id)->not->toBe($second->athlete_id)
        ->and(Athlete::count())->toBe(2);
});

test('a result referencing an unknown external participant id falls back to event+bib matching', function () {
    $connection = integrationConnection();
    $edition = EventEdition::factory()->create();
    $race = EventRace::factory()->create(['event_edition_id' => $edition->id]);
    $service = app(EventIngestionService::class);

    // Ingested without an external id (as CSV would) — so no
    // ExternalParticipantMapping row exists for it.
    $participant = $service->ingestParticipant(
        ExternalParticipantData::fromArray(['bib_number' => '55', 'first_name' => 'Ana', 'last_name' => 'Torres']),
        $edition, $race, null, 'import',
    );

    $result = $service->ingestResult(
        ExternalResultData::fromArray(['external_participant_id' => 'R-UNKNOWN', 'bib_number' => '55', 'official_time' => '03:10:00']),
        $edition, $connection, 'mock',
    );

    expect($result->event_participant_id)->toBe($participant->id);
});

test('ingesting a result with neither a mapping nor a matching bib throws ParticipantNotFoundException', function () {
    $connection = integrationConnection();
    $edition = EventEdition::factory()->create();
    $service = app(EventIngestionService::class);

    expect(fn () => $service->ingestResult(
        ExternalResultData::fromArray(['external_participant_id' => 'GHOST', 'official_time' => '01:00:00']),
        $edition, $connection, 'mock',
    ))->toThrow(ParticipantNotFoundException::class);
});

test('a locked field is never overwritten by the next ingested result', function () {
    $participant = EventParticipant::factory()->create();

    app(IngestEventResult::class)->handle(
        $participant,
        ExternalResultData::fromArray(['external_participant_id' => 'X', 'official_time' => '03:00:00', 'pace' => '4:00']),
        'mock',
    );

    $participant->result()->update([
        'official_time' => '02:59:59',
        'manual_override_at' => now(),
        'manual_override_fields' => ['official_time'],
    ]);

    app(IngestEventResult::class)->handle(
        $participant,
        ExternalResultData::fromArray(['external_participant_id' => 'X', 'official_time' => '03:05:00', 'pace' => '4:10']),
        'mock',
    );

    $result = $participant->result()->first();

    expect($result->official_time)->toBe('02:59:59')
        ->and($result->pace)->toBe('4:10');
});

test('a Plate keeps its snapshot time after the provider corrects the result', function () {
    $edition = EventEdition::factory()->create();
    $race = EventRace::factory()->create(['event_edition_id' => $edition->id]);
    $participant = EventParticipant::factory()->create(['event_edition_id' => $edition->id, 'event_race_id' => $race->id]);

    app(IngestEventResult::class)->handle(
        $participant,
        ExternalResultData::fromArray(['external_participant_id' => 'X', 'official_time' => '03:00:00']),
        'mock',
    );

    // A historical plate already engraved with the result at that time.
    $plate = Plate::factory()->create([
        'event_edition_id' => $edition->id,
        'event_participant_id' => $participant->id,
        'official_time' => $participant->result()->first()->official_time,
    ]);
    expect($plate->official_time)->toBe('03:00:00');

    app(IngestEventResult::class)->handle(
        $participant,
        ExternalResultData::fromArray(['external_participant_id' => 'X', 'official_time' => '02:58:12']),
        'mock',
    );

    expect($plate->fresh()->official_time)->toBe('03:00:00')
        ->and($participant->result()->first()->official_time)->toBe('02:58:12');
});
