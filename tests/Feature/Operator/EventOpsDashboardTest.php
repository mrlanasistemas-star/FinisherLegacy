<?php

use App\Enums\ProductionJobStatus;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventRace;
use App\Models\EventResult;
use App\Models\ProductionDevice;
use App\Models\ProductionJob;
use App\Queries\Operations\GetEventOperationsDashboard;

test('the dashboard counts participants, results, and production buckets correctly', function () {
    $edition = EventEdition::factory()->create();
    $race = EventRace::factory()->create(['event_edition_id' => $edition->id]);

    $withResult = EventParticipant::factory()->create(['event_edition_id' => $edition->id, 'event_race_id' => $race->id]);
    EventParticipant::factory()->create(['event_edition_id' => $edition->id, 'event_race_id' => $race->id]);
    EventResult::factory()->create(['event_participant_id' => $withResult->id]);

    ProductionJob::factory()->create(['event_edition_id' => $edition->id, 'status' => ProductionJobStatus::Queued]);
    ProductionJob::factory()->create(['event_edition_id' => $edition->id, 'status' => ProductionJobStatus::Delivered]);
    ProductionJob::factory()->create(['event_edition_id' => $edition->id, 'status' => ProductionJobStatus::Failed]);

    $data = app(GetEventOperationsDashboard::class)->handle($edition);

    expect($data['data']['participants'])->toBe(2)
        ->and($data['data']['results'])->toBe(1)
        ->and($data['production']['pending'])->toBe(1)
        ->and($data['production']['delivered'])->toBe(1)
        ->and($data['production']['failed'])->toBe(1);
});

test('the dashboard reports each station online/offline independently', function () {
    $edition = EventEdition::factory()->create();

    ProductionDevice::factory()->create(['event_edition_id' => $edition->id, 'name' => 'EVENT-01', 'status' => 'active', 'last_seen_at' => now()]);
    ProductionDevice::factory()->create(['event_edition_id' => $edition->id, 'name' => 'EVENT-02', 'status' => 'active', 'last_seen_at' => now()->subMinutes(10)]);

    $data = app(GetEventOperationsDashboard::class)->handle($edition);

    $stations = collect($data['stations'])->keyBy('name');

    expect($stations['EVENT-01']['online'])->toBeTrue()
        ->and($stations['EVENT-02']['online'])->toBeFalse();
});

test('production metrics stay null until at least one job has been delivered', function () {
    $edition = EventEdition::factory()->create();
    ProductionJob::factory()->create(['event_edition_id' => $edition->id, 'status' => ProductionJobStatus::Queued]);

    $data = app(GetEventOperationsDashboard::class)->handle($edition);

    expect($data['metrics'])->toBeNull();
});
