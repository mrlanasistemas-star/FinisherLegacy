<?php

use App\Actions\Athletes\AssignOwnedProductToEvent;
use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Athletes\RemoveOwnedProductFromEvent;
use App\Enums\AthleteOwnedProductStatus;
use App\Exceptions\EventGearAlreadyAssignedException;
use App\Exceptions\EventGearOwnershipMismatchException;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\EventParticipant;
use App\Models\Product;
use App\Models\User;
use App\Queries\Athletes\GetEventGear;
use Illuminate\Support\Str;

/**
 * product consolidation brief §16-§21: buying gear for an event never
 * implies it was used there — assignment is always an explicit Action,
 * and only for owned products belonging to the same Athlete.
 */
function makeOwnedProduct(Athlete $athlete): AthleteOwnedProduct
{
    return AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'product_id' => Product::factory()->create()->id,
        'status' => AthleteOwnedProductStatus::Active,
        'acquired_at' => now(),
    ]);
}

test('assigning an owned product to an event participation creates a gear selection with a snapshot', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = makeOwnedProduct($athlete);

    $selection = app(AssignOwnedProductToEvent::class)->handle($participant, $owned, 'Se sintió increíble');

    expect($selection->athlete_id)->toBe($athlete->id)
        ->and($selection->event_participant_id)->toBe($participant->id)
        ->and($selection->athlete_owned_product_id)->toBe($owned->id)
        ->and($selection->notes)->toBe('Se sintió increíble')
        ->and($selection->snapshot['product_name'])->toBe($owned->product->name);

    expect(app(GetEventGear::class)->handle($participant))->toHaveCount(1);
});

test('assigning an owned product belonging to a different athlete is rejected', function () {
    $athlete = Athlete::factory()->create();
    $otherAthlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = makeOwnedProduct($otherAthlete);

    app(AssignOwnedProductToEvent::class)->handle($participant, $owned);
})->throws(EventGearOwnershipMismatchException::class);

test('assigning the same owned product to the same participation twice is rejected', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = makeOwnedProduct($athlete);

    app(AssignOwnedProductToEvent::class)->handle($participant, $owned);
    app(AssignOwnedProductToEvent::class)->handle($participant, $owned);
})->throws(EventGearAlreadyAssignedException::class);

test('the same owned product can be assigned to two different participations of the same athlete', function () {
    $athlete = Athlete::factory()->create();
    $marathon = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $ironman = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = makeOwnedProduct($athlete);

    app(AssignOwnedProductToEvent::class)->handle($marathon, $owned);
    app(AssignOwnedProductToEvent::class)->handle($ironman, $owned);

    expect(app(GetEventGear::class)->handle($marathon))->toHaveCount(1)
        ->and(app(GetEventGear::class)->handle($ironman))->toHaveCount(1);
});

test('removing a gear selection deletes it', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = makeOwnedProduct($athlete);
    $selection = app(AssignOwnedProductToEvent::class)->handle($participant, $owned);

    app(RemoveOwnedProductFromEvent::class)->handle($selection);

    expect(app(GetEventGear::class)->handle($participant))->toHaveCount(0);
});

test('GET /api/v1/me/gear includes usage_history for gear assigned to an event', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = makeOwnedProduct($athlete);
    app(AssignOwnedProductToEvent::class)->handle($participant, $owned);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/gear');

    $response->assertOk()
        ->assertJsonCount(1, 'data.0.usage_history')
        ->assertJsonPath('data.0.usage_history.0.event_participant_id', $participant->id);
});
