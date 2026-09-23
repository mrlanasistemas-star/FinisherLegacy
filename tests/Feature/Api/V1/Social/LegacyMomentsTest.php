<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\MomentVisibility;
use App\Enums\NotificationType;
use App\Enums\ProfileVisibility;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\EventParticipant;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\Medal;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function momentAthlete(string $username, array $profile = []): User
{
    $user = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => $username, ...$profile]);

    return $user->fresh('athleteProfile');
}

function momentFor(User $author, MomentVisibility $visibility = MomentVisibility::Public): LegacyMoment
{
    return LegacyMoment::create([
        'user_id' => $author->id,
        'type' => 'manual',
        'caption' => 'Entrenamiento de hoy',
        'visibility' => $visibility,
    ]);
}

test('a training moment is created with metrics and a server-computed pace', function () {
    $ana = momentAthlete('ana');

    $response = $this->withHeaders(apiAuthHeader($ana))->postJson('/api/v1/moments', [
        'type' => 'training',
        'caption' => 'Fondo largo',
        'visibility' => 'public',
        'metrics' => ['title' => 'Fondo', 'distance_km' => 10, 'duration_seconds' => 3000, 'is_personal_record' => true],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'training')
        ->assertJsonPath('data.metrics.pace', '5:00 /km')
        ->assertJsonPath('data.metrics.is_personal_record', true)
        ->assertJsonPath('data.is_owner', true)
        ->assertJsonPath('data.author.username', 'ana');
});

test('a moment can carry uploaded photos', function () {
    $ana = momentAthlete('ana');

    $response = $this->withHeaders(apiAuthHeader($ana))->post('/api/v1/moments', [
        'type' => 'memory',
        'visibility' => 'public',
        'photos' => [UploadedFile::fake()->image('meta.jpg', 1200, 800)],
    ], ['Accept' => 'application/json']);

    $response->assertCreated();
    expect($response->json('data.media'))->toHaveCount(1)
        ->and($response->json('data.media.0.type'))->toBe('image');
});

test('an empty moment is rejected with a human message', function () {
    $ana = momentAthlete('ana');

    $this->withHeaders(apiAuthHeader($ana))->postJson('/api/v1/moments', ['type' => 'manual', 'visibility' => 'public'])
        ->assertStatus(422)->assertJsonValidationErrors('caption');
});

test('a moment can link your own participation and medal but never someone else’s', function () {
    $ana = momentAthlete('ana');
    $athlete = app(EnsureAthleteForUser::class)->handle($ana, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $medal = Medal::factory()->create(['user_id' => $ana->id]);
    $othersMedal = Medal::factory()->create();

    $this->withHeaders(apiAuthHeader($ana))->postJson('/api/v1/moments', [
        'type' => 'race_completed',
        'visibility' => 'public',
        'event_participant_id' => $participant->id,
        'medal_uuid' => $medal->uuid,
    ])->assertCreated()
        ->assertJsonPath('data.activity.participant_id', $participant->id)
        ->assertJsonPath('data.medal.uuid', $medal->uuid);

    $this->withHeaders(apiAuthHeader($ana))->postJson('/api/v1/moments', [
        'type' => 'medal_claimed',
        'visibility' => 'public',
        'medal_uuid' => $othersMedal->uuid,
    ])->assertStatus(422)->assertJsonPath('error.code', 'MOMENT_REFERENCE_INVALID');
});

test('moment visibility is enforced on direct access', function () {
    $ana = momentAthlete('ana');
    $follower = momentAthlete('follower');
    $stranger = momentAthlete('stranger');
    AthleteFollow::create(['follower_id' => $follower->id, 'following_id' => $ana->id]);

    $public = momentFor($ana);
    $followersOnly = momentFor($ana, MomentVisibility::Followers);
    $private = momentFor($ana, MomentVisibility::Private);

    $this->withHeaders(apiAuthHeader($stranger))->getJson("/api/v1/moments/{$public->uuid}")->assertOk();
    $this->withHeaders(apiAuthHeader($stranger))->getJson("/api/v1/moments/{$followersOnly->uuid}")->assertNotFound();
    $this->withHeaders(apiAuthHeader($follower))->getJson("/api/v1/moments/{$followersOnly->uuid}")->assertOk();
    $this->withHeaders(apiAuthHeader($follower))->getJson("/api/v1/moments/{$private->uuid}")->assertNotFound();
    $this->withHeaders(apiAuthHeader($ana))->getJson("/api/v1/moments/{$private->uuid}")->assertOk();
});

test('a private profile hides all of its moments from others', function () {
    $ana = momentAthlete('ana', ['profile_visibility' => ProfileVisibility::Private]);
    $viewer = momentAthlete('viewer');
    AthleteFollow::create(['follower_id' => $viewer->id, 'following_id' => $ana->id]);
    $moment = momentFor($ana);

    $this->withHeaders(apiAuthHeader($viewer))->getJson("/api/v1/moments/{$moment->uuid}")->assertNotFound();
    $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/feed')->assertOk()->assertJsonCount(0, 'data');
});

test('the feed shows own and followed athletes’ visible moments, newest first, with cursor pagination', function () {
    $viewer = momentAthlete('viewer');
    $followed = momentAthlete('followed');
    $stranger = momentAthlete('stranger');
    AthleteFollow::create(['follower_id' => $viewer->id, 'following_id' => $followed->id]);

    $own = momentFor($viewer, MomentVisibility::Private);
    $fromFollowed = momentFor($followed, MomentVisibility::Followers);
    momentFor($stranger);

    $response = $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/feed');

    $response->assertOk()->assertJsonStructure(['data', 'links', 'meta' => ['next_cursor', 'scope']]);
    expect(collect($response->json('data'))->pluck('uuid')->all())->toBe([$fromFollowed->uuid, $own->uuid]);

    $discover = $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/feed?scope=discover');
    expect($discover->json('data'))->toHaveCount(3);
});

test('blocked athletes’ moments disappear from the feed', function () {
    $viewer = momentAthlete('viewer');
    $blocked = momentAthlete('blocked');
    momentFor($blocked);
    UserBlock::create(['blocker_id' => $viewer->id, 'blocked_id' => $blocked->id]);

    $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/feed?scope=discover')
        ->assertOk()->assertJsonCount(0, 'data');
});

test('reactions are idempotent toggles and notify the author once', function () {
    $ana = momentAthlete('ana');
    $beto = momentAthlete('beto');
    $moment = momentFor($ana);

    $this->withHeaders(apiAuthHeader($beto))->putJson("/api/v1/moments/{$moment->uuid}/reactions/like")
        ->assertOk()->assertJsonPath('data.reactions.like', 1)->assertJsonPath('data.my_reactions', ['like']);
    $this->withHeaders(apiAuthHeader($beto))->putJson("/api/v1/moments/{$moment->uuid}/reactions/like")
        ->assertOk()->assertJsonPath('data.reactions.like', 1);
    $this->withHeaders(apiAuthHeader($beto))->putJson("/api/v1/moments/{$moment->uuid}/reactions/cheer")
        ->assertOk()->assertJsonPath('data.reactions.cheer', 1);

    expect($ana->notifications()->where('data->type', NotificationType::MomentReaction->value)->count())->toBe(1);

    $this->withHeaders(apiAuthHeader($beto))->deleteJson("/api/v1/moments/{$moment->uuid}/reactions/like")
        ->assertOk()->assertJsonPath('data.reactions.like', 0)->assertJsonPath('data.my_reactions', ['cheer']);

    $this->withHeaders(apiAuthHeader($beto))->putJson("/api/v1/moments/{$moment->uuid}/reactions/fire")->assertNotFound();
});

test('comments are created, listed with pagination and deletable by their author or the moment author', function () {
    $ana = momentAthlete('ana');
    $beto = momentAthlete('beto');
    $carla = momentAthlete('carla');
    $moment = momentFor($ana);

    $created = $this->withHeaders(apiAuthHeader($beto))
        ->postJson("/api/v1/moments/{$moment->uuid}/comments", ['body' => 'Qué carrerón 🔥'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Qué carrerón 🔥')
        ->assertJsonPath('data.author.username', 'beto');

    expect($ana->notifications()->where('data->type', NotificationType::MomentComment->value)->count())->toBe(1);

    $this->withHeaders(apiAuthHeader($carla))->getJson("/api/v1/moments/{$moment->uuid}/comments")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonPath('data.0.can_delete', false);

    $commentUuid = $created->json('data.uuid');
    $this->withHeaders(apiAuthHeader($carla))->deleteJson("/api/v1/comments/{$commentUuid}")->assertNotFound();
    $this->withHeaders(apiAuthHeader($ana))->deleteJson("/api/v1/comments/{$commentUuid}")->assertOk();

    expect(LegacyMomentComment::query()->count())->toBe(0);
});

test('you cannot comment on a moment you cannot see', function () {
    $ana = momentAthlete('ana');
    $beto = momentAthlete('beto');
    $moment = momentFor($ana, MomentVisibility::Private);

    $this->withHeaders(apiAuthHeader($beto))
        ->postJson("/api/v1/moments/{$moment->uuid}/comments", ['body' => 'Hola'])
        ->assertNotFound();
});

test('comments from blocked athletes are hidden', function () {
    $ana = momentAthlete('ana');
    $troll = momentAthlete('troll');
    $moment = momentFor($ana);
    LegacyMomentComment::create(['legacy_moment_id' => $moment->id, 'user_id' => $troll->id, 'body' => 'spam']);
    UserBlock::create(['blocker_id' => $ana->id, 'blocked_id' => $troll->id]);

    $this->withHeaders(apiAuthHeader($ana))->getJson("/api/v1/moments/{$moment->uuid}/comments")
        ->assertOk()->assertJsonCount(0, 'data');
});

test('only the author can edit or delete a moment', function () {
    $ana = momentAthlete('ana');
    $beto = momentAthlete('beto');
    $moment = momentFor($ana);

    $this->withHeaders(apiAuthHeader($beto))->patchJson("/api/v1/moments/{$moment->uuid}", ['caption' => 'x'])->assertNotFound();
    $this->withHeaders(apiAuthHeader($ana))->patchJson("/api/v1/moments/{$moment->uuid}", ['visibility' => 'followers'])
        ->assertOk()->assertJsonPath('data.visibility', 'followers');
    $this->withHeaders(apiAuthHeader($beto))->deleteJson("/api/v1/moments/{$moment->uuid}")->assertNotFound();
    $this->withHeaders(apiAuthHeader($ana))->deleteJson("/api/v1/moments/{$moment->uuid}")->assertOk();

    expect(LegacyMoment::query()->count())->toBe(0);
});

test('profiles, moments and comments can be reported, but not your own content', function () {
    $ana = momentAthlete('ana');
    $beto = momentAthlete('beto');
    $moment = momentFor($ana);

    $this->withHeaders(apiAuthHeader($beto))->postJson('/api/v1/reports', [
        'target_type' => 'moment', 'target' => $moment->uuid, 'reason' => 'spam',
    ])->assertCreated();

    $this->withHeaders(apiAuthHeader($beto))->postJson('/api/v1/reports', [
        'target_type' => 'profile', 'target' => 'ana', 'reason' => 'impersonation', 'details' => 'No es ella',
    ])->assertCreated();

    $this->withHeaders(apiAuthHeader($ana))->postJson('/api/v1/reports', [
        'target_type' => 'moment', 'target' => $moment->uuid, 'reason' => 'spam',
    ])->assertStatus(422);

    expect(Report::query()->count())->toBe(2)
        ->and(Report::query()->first()->status->value)->toBe('open');
});

test('an athlete profile lists only the moments the viewer may see', function () {
    $ana = momentAthlete('ana');
    $viewer = momentAthlete('viewer');
    momentFor($ana);
    momentFor($ana, MomentVisibility::Followers);

    $this->withHeaders(apiAuthHeader($viewer))->getJson('/api/v1/athletes/ana/moments')
        ->assertOk()->assertJsonCount(1, 'data');
});
