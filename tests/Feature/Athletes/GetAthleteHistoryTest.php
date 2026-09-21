<?php

use App\Actions\Athletes\AssignOwnedProductToEvent;
use App\Enums\AthleteOwnedProductStatus;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\Product;
use App\Queries\Athletes\GetAthleteHistory;
use Illuminate\Support\Str;

/**
 * Participation-centric and paginated (product consolidation brief §27-
 * §28) — each row carries summary counts (medals, gear, media), not the
 * Athlete's full media/owned-products/orders collections.
 */
test('GetAthleteHistory paginates participations with summary counts, not full related collections', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $product = Product::factory()->create();

    AthleteEventMedia::create([
        'uuid' => (string) Str::uuid(), 'athlete_id' => $athlete->id, 'event_participant_id' => $participant->id,
        'type' => 'image', 'disk' => 'athlete_media', 'path' => 'x.jpg', 'mime' => 'image/jpeg',
        'size_bytes' => 10, 'checksum' => str_repeat('a', 64), 'is_public' => true, 'sort_order' => 0,
    ]);
    $owned = AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(), 'athlete_id' => $athlete->id, 'product_id' => $product->id,
        'status' => AthleteOwnedProductStatus::Active, 'acquired_at' => now(),
    ]);
    app(AssignOwnedProductToEvent::class)->handle($participant, $owned);

    $history = app(GetAthleteHistory::class)->handle($athlete);

    expect($history->total())->toBe(1);
    $row = GetAthleteHistory::summarize($history->items()[0]);
    expect($row['media_count'])->toBe(1)
        ->and($row['gear_count'])->toBe(1)
        ->and($row['medal_count'])->toBe(0);
});

test('GetAthleteHistory respects the requested page size', function () {
    $athlete = Athlete::factory()->create();
    EventParticipant::factory()->count(3)->create(['athlete_id' => $athlete->id]);

    $history = app(GetAthleteHistory::class)->handle($athlete, perPage: 2);

    expect($history->total())->toBe(3)
        ->and($history->items())->toHaveCount(2);
});

test('GetAthleteHistory orders by the real event date, not by row creation order (brief item 29)', function () {
    $athlete = Athlete::factory()->create();

    // Created first (older created_at) but the race it belongs to is
    // the most recent one — sporting history must still surface it
    // first. Ordering by created_at alone would put it last.
    $recentEdition = EventEdition::factory()->create(['event_date' => now()->subDays(1)]);
    $recentParticipant = EventParticipant::factory()->create([
        'athlete_id' => $athlete->id,
        'event_edition_id' => $recentEdition->id,
    ]);

    $olderEdition = EventEdition::factory()->create(['event_date' => now()->subYears(2)]);
    EventParticipant::factory()->create([
        'athlete_id' => $athlete->id,
        'event_edition_id' => $olderEdition->id,
    ]);

    $history = app(GetAthleteHistory::class)->handle($athlete);

    expect($history->items()[0]->id)->toBe($recentParticipant->id);
});
