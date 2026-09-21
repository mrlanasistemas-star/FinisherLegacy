<?php

use App\Actions\Athletes\AssignOwnedProductToEvent;
use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\LegacyPlates\CreateLegacyPlateEntitlement;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyPlateModel;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * "MIS EVENTOS / MIS LEGACY PLATES / MI EQUIPO" (brief §39-§46/§94-§111) —
 * always the athlete's own data, reusing App\Queries\Athletes\
 * GetAthleteHistory and App\Queries\Commerce\GetAthleteOwnedProducts, never
 * a second read model.
 */
test('my-events only ever shows the signed-in athlete\'s own participations', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $edition = EventEdition::factory()->create();
    EventParticipant::factory()->create(['athlete_id' => $athlete->id, 'event_edition_id' => $edition->id]);

    $otherAthlete = Athlete::factory()->create();
    EventParticipant::factory()->create(['athlete_id' => $otherAthlete->id]);

    $response = $this->actingAs($user)->get('/dashboard/my-events');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('dashboard/MyEvents')->has('participations', 1));
});

test('a participant belonging to another athlete cannot be opened through the URL', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');

    $otherAthlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $otherAthlete->id]);

    $this->actingAs($user)->get("/dashboard/my-events/{$participant->id}")->assertForbidden();
});

test('media upload is blocked once the free-tier limit is reached, matching the badge the UI shows', function () {
    config(['finisher.event_media.free_images_per_participation' => 1]);

    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $this->actingAs($user)->post("/dashboard/my-events/{$participant->id}/media", [
        'file' => UploadedFile::fake()->image('finish-line.jpg'),
    ])->assertRedirect();

    expect(AthleteEventMedia::query()->where('event_participant_id', $participant->id)->count())->toBe(1);

    // A second photo exceeds the configured free limit of 1 — rejected
    // with a validation error, never silently accepted past the cap the
    // dashboard's "Fotos 1/1" badge already promised the athlete.
    $this->actingAs($user)->post("/dashboard/my-events/{$participant->id}/media", [
        'file' => UploadedFile::fake()->image('another.jpg'),
    ])->assertSessionHasErrors('file');

    expect(AthleteEventMedia::query()->where('event_participant_id', $participant->id)->count())->toBe(1);
});

test('an athlete cannot delete or expose media that belongs to someone else', function () {
    $owner = User::factory()->create();
    $ownerAthlete = app(EnsureAthleteForUser::class)->handle($owner, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $ownerAthlete->id]);
    $media = AthleteEventMedia::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $ownerAthlete->id,
        'event_participant_id' => $participant->id,
        'type' => 'image',
        'disk' => 'public',
        'path' => 'event-media/test.jpg',
        'mime' => 'image/jpeg',
        'size_bytes' => 1024,
        'checksum' => 'abc',
        'is_public' => false,
        'sort_order' => 1,
    ]);

    $stranger = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($stranger, 'test');

    $this->actingAs($stranger)->delete("/dashboard/media/{$media->uuid}")->assertForbidden();
    $this->actingAs($stranger)->patch("/dashboard/media/{$media->uuid}/visibility", ['is_public' => true])->assertForbidden();

    expect($media->fresh())->not->toBeNull()
        ->and($media->fresh()->is_public)->toBeFalse();
});

test('my-plates lists Legacy Plates linked to the athlete\'s own entitlements', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    app(CreateLegacyPlateEntitlement::class)->handle([
        'athlete_id' => $athlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $response = $this->actingAs($user)->get('/dashboard/my-plates');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('dashboard/MyPlates'));
});

test('my-gear (Digital Closet) renders for an athlete with nothing owned yet', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');

    $response = $this->actingAs($user)->get('/dashboard/my-gear');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('dashboard/MyGear')->where('items', []));
});

test('my-gear shows which event each owned product was used in', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $owned = AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'product_id' => Product::factory()->create()->id,
        'status' => 'active',
        'acquired_at' => now(),
    ]);
    app(AssignOwnedProductToEvent::class)->handle($participant, $owned);

    $response = $this->actingAs($user)->get('/dashboard/my-gear');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items.0.usage_history', 1)
        ->where('items.0.usage_history.0.event_participant_id', $participant->id));
});
