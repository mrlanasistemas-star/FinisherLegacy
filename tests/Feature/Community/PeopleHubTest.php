<?php

use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * "Personas" hub (/comunidad/personas): friends of friends, mutual
 * friends, followers/following, blocked, suggestions and the name filter.
 */
beforeEach(function () {
    Storage::fake('public');
});

function hubAthlete(string $username, string $visibility = 'public', ?string $first = null): User
{
    $user = User::factory()->create($first ? ['first_name' => $first] : []);
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => $username, 'profile_visibility' => $visibility]);

    return $user->fresh('athleteProfile');
}

function hubFollow(User $follower, User $target): void
{
    AthleteFollow::create(['follower_id' => $follower->id, 'following_id' => $target->id]);
}

/**
 * @return list<string>
 */
function hubUsernames($response): array
{
    return collect($response->inertiaProps('people.data'))->pluck('username')->all();
}

test('guests are sent to login from the people hub', function () {
    $this->get('/comunidad/personas')->assertRedirect('/login');
    $this->get('/comunidad/personas?tab=amigos-de-amigos')->assertRedirect('/login');
});

test('the old conexiones url redirects to the matching hub tab', function () {
    $me = hubAthlete('yo');

    $this->actingAs($me)->get('/comunidad/conexiones')->assertRedirect('/comunidad/personas?tab=siguiendo');
    $this->actingAs($me)->get('/comunidad/conexiones?tab=bloqueados')->assertRedirect('/comunidad/personas?tab=bloqueados');
});

test('friends of friends are ranked by mutual connections and say who follows them', function () {
    $me = hubAthlete('yo');
    $ana = hubAthlete('ana', first: 'Ana');
    $beto = hubAthlete('beto', first: 'Beto');
    $popular = hubAthlete('popular');
    $single = hubAthlete('single');

    hubFollow($me, $ana);
    hubFollow($me, $beto);
    hubFollow($ana, $popular);
    hubFollow($beto, $popular);
    hubFollow($ana, $single);

    $response = $this->actingAs($me)->get('/comunidad/personas?tab=amigos-de-amigos')->assertOk();

    expect(hubUsernames($response))->toBe(['popular', 'single']);
    $response->assertInertia(fn ($page) => $page
        ->component('community/People')
        ->where('tab', 'amigos-de-amigos')
        ->where('people.data.0.mutual_count', 2)
        ->where('people.data.0.is_following', false)
        ->where('people.data.1.mutual_count', 1)
        ->where('people.data.1.mutual_names', ['Ana '.$ana->last_name])
    );
});

test('friends of friends exclude me, people I already follow, blocks either way and private profiles', function () {
    $me = hubAthlete('yo');
    $ana = hubAthlete('ana');
    $followed = hubAthlete('yasigo');
    $blockedByMe = hubAthlete('bloqueado');
    $blocksMe = hubAthlete('mebloquea');
    $private = hubAthlete('privado', 'private');
    $ok = hubAthlete('valido');

    hubFollow($me, $ana);
    hubFollow($me, $followed);

    foreach ([$me, $followed, $blockedByMe, $blocksMe, $private, $ok] as $target) {
        hubFollow($ana, $target);
    }

    UserBlock::create(['blocker_id' => $me->id, 'blocked_id' => $blockedByMe->id]);
    UserBlock::create(['blocker_id' => $blocksMe->id, 'blocked_id' => $me->id]);

    $response = $this->actingAs($me)->get('/comunidad/personas?tab=amigos-de-amigos')->assertOk();

    expect(hubUsernames($response))->toBe(['valido']);
});

test('follows made by a private profile never feed friends of friends', function () {
    $me = hubAthlete('yo');
    $secret = hubAthlete('secreto', 'private');
    $target = hubAthlete('objetivo');

    hubFollow($me, $secret);
    hubFollow($secret, $target);

    $response = $this->actingAs($me)->get('/comunidad/personas?tab=amigos-de-amigos')->assertOk();

    expect(hubUsernames($response))->toBe([]);
});

test('amigos lists mutual follows only; followers and following tabs', function () {
    $me = hubAthlete('yo');
    $friend = hubAthlete('amiga');
    $fan = hubAthlete('fan');
    $idol = hubAthlete('idolo');

    hubFollow($me, $friend);
    hubFollow($friend, $me);
    hubFollow($fan, $me);
    hubFollow($me, $idol);

    expect(hubUsernames($this->actingAs($me)->get('/comunidad/personas?tab=amigos')))->toBe(['amiga']);
    expect(hubUsernames($this->actingAs($me)->get('/comunidad/personas?tab=seguidores')))->toEqualCanonicalizing(['amiga', 'fan']);
    expect(hubUsernames($this->actingAs($me)->get('/comunidad/personas?tab=siguiendo')))->toEqualCanonicalizing(['amiga', 'idolo']);

    $this->actingAs($me)->get('/comunidad/personas?tab=amigos')
        ->assertInertia(fn ($page) => $page
            ->where('counts.amigos', 1)
            ->where('counts.seguidores', 2)
            ->where('counts.siguiendo', 2)
            ->where('counts.bloqueados', 0)
            ->where('people.data.0.follows_you', true)
        );
});

test('suggestions rank friends of friends first and skip followed, blocked and non-public people', function () {
    $me = hubAthlete('yo');
    $ana = hubAthlete('ana');
    $fof = hubAthlete('conocida');
    $stranger = hubAthlete('extrano');
    $followersOnly = hubAthlete('cerrada', 'followers');
    $blocked = hubAthlete('bloqueado');

    hubFollow($me, $ana);
    hubFollow($ana, $fof);
    // The stranger is more popular, but has no mutuals with me.
    foreach (range(1, 3) as $i) {
        hubFollow(hubAthlete("fan{$i}"), $stranger);
    }
    UserBlock::create(['blocker_id' => $blocked->id, 'blocked_id' => $me->id]);

    $usernames = hubUsernames($this->actingAs($me)->get('/comunidad/personas'));

    expect($usernames[0])->toBe('conocida')
        ->and($usernames)->toContain('extrano')
        ->and($usernames)->not->toContain('yo')
        ->and($usernames)->not->toContain('ana')
        ->and($usernames)->not->toContain('cerrada')
        ->and($usernames)->not->toContain('bloqueado');
});

test('the people hub filters by name or username', function () {
    $me = hubAthlete('yo');
    $maria = hubAthlete('mari_run', first: 'María');
    $pedro = hubAthlete('pedro', first: 'Pedro');
    hubFollow($me, $maria);
    hubFollow($me, $pedro);

    expect(hubUsernames($this->actingAs($me)->get('/comunidad/personas?tab=siguiendo&q=mari')))->toBe(['mari_run']);
    expect(hubUsernames($this->actingAs($me)->get('/comunidad/personas?tab=siguiendo&q=Pedro')))->toBe(['pedro']);
    expect(hubUsernames($this->actingAs($me)->get('/comunidad/personas?tab=siguiendo&q=zzz')))->toBe([]);
});

test('the blocked tab lists who I blocked', function () {
    $me = hubAthlete('yo');
    $blocked = hubAthlete('bloqueado');
    UserBlock::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);

    expect(hubUsernames($this->actingAs($me)->get('/comunidad/personas?tab=bloqueados')))->toBe(['bloqueado']);
});

test('a page of people is annotated in a bounded number of queries', function () {
    $me = hubAthlete('yo');
    $friends = collect(range(1, 3))->map(fn ($i) => hubAthlete("amigo{$i}"));
    $friends->each(fn ($friend) => hubFollow($me, $friend));

    $count = function () use ($me) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($me)->get('/comunidad/personas?tab=amigos-de-amigos')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    foreach (range(1, 3) as $i) {
        $target = hubAthlete("meta{$i}");
        $friends->each(fn ($friend) => hubFollow($friend, $target));
    }
    $count(); // Warm up (roles, settings caches).
    $few = $count();

    foreach (range(4, 12) as $i) {
        $target = hubAthlete("meta{$i}");
        $friends->each(fn ($friend) => hubFollow($friend, $target));
    }
    $many = $count();

    expect($many)->toBe($few);
});

test('the feed exposes the people strip, my counts and comment previews', function () {
    $me = hubAthlete('yo');
    $ana = hubAthlete('ana');
    hubFollow($me, $ana);
    $post = LegacyMoment::create(['user_id' => $ana->id, 'type' => 'text', 'caption' => 'Hola', 'visibility' => 'public']);
    foreach (['uno', 'dos', 'tres'] as $body) {
        LegacyMomentComment::create(['legacy_moment_id' => $post->id, 'user_id' => $me->id, 'body' => $body]);
    }

    $this->actingAs($me)->get('/comunidad?publicar=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('openComposer', true)
            ->where('peopleStrip.0.username', 'ana')
            ->where('peopleStrip.0.recent', true)
            ->where('me.following', 1)
            ->has('posts.data.0.comments_preview', 2)
            ->where('posts.data.0.comments_preview.1.body', 'tres')
        );
});

test('the public profile says which people you follow also follow them', function () {
    $me = hubAthlete('yo');
    $ana = hubAthlete('ana', first: 'Ana');
    $owner = hubAthlete('dueno');
    hubFollow($me, $ana);
    hubFollow($ana, $owner);
    hubFollow($owner, $me);

    $this->actingAs($me)->get('/@dueno')
        ->assertInertia(fn ($page) => $page
            ->component('profile/Show')
            ->where('social.mutual_count', 1)
            ->where('social.mutual_names', ['Ana '.$ana->last_name])
            ->where('social.follows_you', true)
        );
});
