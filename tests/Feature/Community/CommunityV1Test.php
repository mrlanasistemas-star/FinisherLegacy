<?php

use App\Enums\MomentVisibility;
use App\Enums\ReportStatus;
use App\Events\Community\CommunityCommentCreated;
use App\Events\Community\CommunityPostCreated;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\EventEdition;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\LegacyMomentReaction;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Social\SocialVisibility;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Comunidad v1 (spec §52–59, §86–87): privacy matrix, feed ranking and
 * cursor, reactions, comments, moderation, blocks, API contract.
 */
beforeEach(function () {
    Storage::fake('public');
});

function v1Athlete(string $username, string $visibility = 'public'): User
{
    $user = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => $username, 'profile_visibility' => $visibility]);

    return $user->fresh('athleteProfile');
}

function v1Post(User $author, array $attributes = []): LegacyMoment
{
    return LegacyMoment::create([
        'user_id' => $author->id,
        'type' => 'text',
        'caption' => 'Post de '.$author->athleteProfile?->username,
        'visibility' => MomentVisibility::Public,
        ...$attributes,
    ]);
}

function v1Follow(User $follower, User $target): void
{
    AthleteFollow::create(['follower_id' => $follower->id, 'following_id' => $target->id]);
}

// ---------------------------------------------------------------------
// Privacy matrix: profile visibility × viewer
// ---------------------------------------------------------------------

dataset('privacy matrix', [
    // profile, viewer, sees content, sees shell (web profile/Show restricted)
    'public · anonymous' => ['public', 'anonymous', true, true],
    'public · follower' => ['public', 'follower', true, true],
    'public · stranger' => ['public', 'stranger', true, true],
    'public · blocked' => ['public', 'blocked', false, false],
    'followers · anonymous' => ['followers', 'anonymous', false, false],
    'followers · follower' => ['followers', 'follower', true, true],
    'followers · stranger' => ['followers', 'stranger', false, true],
    'followers · blocked' => ['followers', 'blocked', false, false],
    'private · anonymous' => ['private', 'anonymous', false, false],
    'private · follower' => ['private', 'follower', false, false],
    'private · stranger' => ['private', 'stranger', false, false],
    'private · blocked' => ['private', 'blocked', false, false],
]);

/**
 * @return array{0: User, 1: User|null, 2: LegacyMoment}
 */
function v1Scenario(string $profileVisibility, string $viewerKind): array
{
    $owner = v1Athlete('owner', $profileVisibility);
    $post = v1Post($owner, ['caption' => 'Contenido del dueño']);
    $viewer = $viewerKind === 'anonymous' ? null : v1Athlete('viewer');

    if ($viewerKind === 'follower' || $viewerKind === 'blocked') {
        v1Follow($viewer, $owner);
    }

    if ($viewerKind === 'blocked') {
        // The owner blocks the viewer — follows are removed by BlockUser
        // in real life; keep the follow here to prove the block still wins.
        UserBlock::create(['blocker_id' => $owner->id, 'blocked_id' => $viewer->id]);
    }

    return [$owner, $viewer, $post];
}

test('web profile, post and feed respect the privacy matrix', function (string $profile, string $viewerKind, bool $content, bool $shell) {
    [, $viewer, $post] = v1Scenario($profile, $viewerKind);
    $client = $viewer ? $this->actingAs($viewer) : $this;

    $response = $client->get('/@owner')->assertOk();

    if ($content) {
        $response->assertInertia(fn ($page) => $page->component('profile/Show')->where('restricted', false)->has('posts.data', 1));
    } elseif ($shell) {
        $response->assertInertia(fn ($page) => $page->component('profile/Show')->where('restricted', true)->where('posts.data', []));
    } else {
        $response->assertInertia(fn ($page) => $page->component('profile/Private'));
    }

    $client->get("/comunidad/publicaciones/{$post->uuid}")->assertStatus($content ? 200 : 404);

    $client->get('/comunidad')->assertInertia(fn ($page) => $page
        ->component('community/Index')
        ->has('posts.data', $content ? 1 : 0));
})->with('privacy matrix');

test('API profile, moments and feed respect the privacy matrix', function (string $profile, string $viewerKind, bool $content, bool $shell) {
    [, $viewer, $post] = v1Scenario($profile, $viewerKind);

    if ($viewer === null) {
        $this->getJson('/api/v1/athletes/owner')->assertStatus($content ? 200 : 404);

        return;
    }

    Sanctum::actingAs($viewer);

    $profileResponse = $this->getJson('/api/v1/athletes/owner')->assertStatus($content || $shell ? 200 : 404);
    if ($content || $shell) {
        $profileResponse->assertJsonPath('data.restricted', ! $content);
    }

    $this->getJson("/api/v1/moments/{$post->uuid}")->assertStatus($content ? 200 : 404);
    $this->getJson('/api/v1/athletes/owner/moments')->assertStatus($content ? 200 : 404);
    $this->getJson('/api/v1/feed?scope=discover')->assertJsonCount($content ? 1 : 0, 'data');
    $this->getJson('/api/v1/feed?scope=for_you')->assertJsonCount($content ? 1 : 0, 'data');
})->with('privacy matrix');

test('a followers-only profile can be followed, then unlocks its content', function () {
    $owner = v1Athlete('owner', 'followers');
    v1Post($owner);
    $viewer = v1Athlete('viewer');

    $this->actingAs($viewer)->post('/atletas/owner/seguir')->assertRedirect();
    expect(AthleteFollow::query()->where('follower_id', $viewer->id)->exists())->toBeTrue();

    $this->actingAs($viewer)->get('/@owner')
        ->assertInertia(fn ($page) => $page->where('restricted', false)->has('posts.data', 1));
});

test('followers-only profiles never show up in search or suggestions', function () {
    v1Athlete('corredorx', 'followers');
    Sanctum::actingAs(v1Athlete('viewer'));

    $this->getJson('/api/v1/search?q=corredorx&type=athletes')->assertOk()->assertJsonCount(0, 'data.athletes');
});

test('hidden moderation content stays visible only to its author', function () {
    $owner = v1Athlete('owner');
    $post = v1Post($owner, ['hidden_at' => now()]);
    $viewer = v1Athlete('viewer');

    $this->actingAs($viewer)->get("/comunidad/publicaciones/{$post->uuid}")->assertNotFound();
    $this->actingAs($owner)->get("/comunidad/publicaciones/{$post->uuid}")
        ->assertInertia(fn ($page) => $page->where('post.is_hidden', true));
    $this->actingAs($viewer)->get('/comunidad')->assertInertia(fn ($page) => $page->has('posts.data', 0));
});

// ---------------------------------------------------------------------
// Feed: tabs, ranking, cursor
// ---------------------------------------------------------------------

test('para ti ranks followed people first within the day and paginates with a cursor', function () {
    config(['finisher.social.feed_per_page' => 2]);
    $viewer = v1Athlete('viewer');
    $friend = v1Athlete('friend');
    $stranger = v1Athlete('stranger');
    v1Follow($viewer, $friend);

    $friendPost = v1Post($friend, ['caption' => 'amigo']);
    $strangerA = v1Post($stranger, ['caption' => 'extraño A']);
    $strangerB = v1Post($stranger, ['caption' => 'extraño B']);
    $old = v1Post($stranger, ['caption' => 'ayer']);
    $old->forceFill(['created_at' => now()->subDay()])->save();

    Sanctum::actingAs($viewer);
    $first = $this->getJson('/api/v1/feed?scope=for_you')->assertOk()
        ->assertJsonPath('meta.scope', 'for_you')
        ->assertJsonPath('data.0.uuid', $friendPost->uuid)
        ->assertJsonPath('data.1.uuid', $strangerB->uuid);

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson('/api/v1/feed?scope=for_you&cursor='.$cursor)->assertOk();
    expect(collect($second->json('data'))->pluck('uuid')->all())->toBe([$strangerA->uuid, $old->uuid]);
    expect($second->json('meta.next_cursor'))->toBeNull();
});

test('the eventos tab only lists posts tied to an event, yours first', function () {
    $viewer = v1Athlete('viewer');
    $other = v1Athlete('other');
    $edition = EventEdition::factory()->create();

    $eventPost = v1Post($other, ['event_edition_id' => $edition->id, 'caption' => 'En la carrera']);
    v1Post($other, ['caption' => 'Sin evento']);

    $this->actingAs($viewer)->get('/comunidad?tab=eventos')
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'eventos')
            ->has('posts.data', 1)
            ->where('posts.data.0.uuid', $eventPost->uuid)
            ->where('posts.data.0.event.name', $edition->event->name));
});

test('the feed loads a page in a bounded number of queries', function () {
    $viewer = v1Athlete('viewer');
    foreach (range(1, 6) as $i) {
        $author = v1Athlete("autor{$i}");
        v1Follow($viewer, $author);
        $post = v1Post($author, ['event_edition_id' => EventEdition::factory()->create()->id]);
        LegacyMomentComment::create(['legacy_moment_id' => $post->id, 'user_id' => $viewer->id, 'body' => 'Vamos']);
    }

    Sanctum::actingAs($viewer);

    $count = function (int $posts) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/feed?scope=for_you')->assertOk()->assertJsonCount($posts, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $withSix = $count(6);
    foreach (range(7, 12) as $i) {
        $author = v1Athlete("autor{$i}");
        v1Follow($viewer, $author);
        v1Post($author, ['event_edition_id' => EventEdition::factory()->create()->id]);
    }
    app(SocialVisibility::class)->forget($viewer);
    $withTwelve = $count(12);

    expect($withTwelve)->toBe($withSix)->and($withSix)->toBeLessThan(40);
});

// ---------------------------------------------------------------------
// Composer, reactions, comments, delete
// ---------------------------------------------------------------------

test('the web composer publishes a typed post with a related event and announces public posts', function () {
    Event::fake([CommunityPostCreated::class]);
    $author = v1Athlete('autora');
    $edition = EventEdition::factory()->create();

    $this->actingAs($author)->post('/comunidad/publicaciones', [
        'type' => 'text',
        'caption' => 'Lista para el medio maratón',
        'visibility' => 'public',
        'event_edition_id' => $edition->id,
    ])->assertRedirect();

    $post = LegacyMoment::query()->sole();
    expect($post->event_edition_id)->toBe($edition->id)
        ->and($post->kind()->value)->toBe('text');
    Event::assertDispatched(CommunityPostCreated::class, fn ($e) => $e->broadcastAs() === 'community.post.created'
        && $e->broadcastOn()[0]->name === 'community.feed');

    // Followers-only posts are never announced on the public channel.
    $this->actingAs($author)->post('/comunidad/publicaciones', ['type' => 'text', 'caption' => 'Solo amigos', 'visibility' => 'followers']);
    Event::assertDispatchedTimes(CommunityPostCreated::class, 1);
});

test('celebrar is idempotent and turning it off clears any reaction', function () {
    $author = v1Athlete('autor');
    $fan = v1Athlete('fan');
    $post = v1Post($author);

    $this->actingAs($fan)->post("/comunidad/publicaciones/{$post->uuid}/celebrar");
    $this->actingAs($fan)->post("/comunidad/publicaciones/{$post->uuid}/celebrar");
    expect(LegacyMomentReaction::query()->count())->toBe(1);

    LegacyMomentReaction::create(['legacy_moment_id' => $post->id, 'user_id' => $fan->id, 'type' => 'like']);
    $this->actingAs($fan)->delete("/comunidad/publicaciones/{$post->uuid}/celebrar");
    expect(LegacyMomentReaction::query()->count())->toBe(0);

    Sanctum::actingAs($fan);
    $this->putJson("/api/v1/moments/{$post->uuid}/reactions/like")->assertJsonPath('data.reactions.total', 1);
    $this->putJson("/api/v1/moments/{$post->uuid}/reactions/like")->assertJsonPath('data.reactions.total', 1);
});

test('comments: author of the post is notified in realtime, strangers cannot delete others', function () {
    Event::fake([CommunityCommentCreated::class]);
    $author = v1Athlete('autor');
    $fan = v1Athlete('fan');
    $stranger = v1Athlete('extrano');
    $post = v1Post($author);

    $this->actingAs($fan)->post("/comunidad/publicaciones/{$post->uuid}/comentarios", ['body' => '¡Vamos!'])->assertRedirect();
    $comment = LegacyMomentComment::query()->sole();

    Event::assertDispatched(CommunityCommentCreated::class, fn ($e) => $e->broadcastOn()[0]->name === "private-App.Models.User.{$author->id}");

    $this->actingAs($stranger)->delete("/comunidad/comentarios/{$comment->uuid}")->assertNotFound();
    $this->actingAs($fan)->delete("/comunidad/comentarios/{$comment->uuid}")->assertRedirect();
    expect(LegacyMomentComment::query()->count())->toBe(0);
});

test('only the author deletes a post', function () {
    $author = v1Athlete('autor');
    $other = v1Athlete('otro');
    $post = v1Post($author);

    $this->actingAs($other)->delete("/comunidad/publicaciones/{$post->uuid}")->assertNotFound();
    $this->actingAs($author)->delete("/comunidad/publicaciones/{$post->uuid}")->assertRedirect();
    expect(LegacyMoment::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------
// Block, report, moderation
// ---------------------------------------------------------------------

test('blocking from the web hides both people from each other', function () {
    $ana = v1Athlete('ana');
    $beto = v1Athlete('beto');
    v1Post($beto);
    v1Follow($ana, $beto);

    $this->actingAs($ana)->post('/atletas/beto/bloquear')->assertRedirect('/comunidad');

    expect(AthleteFollow::query()->count())->toBe(0);
    $this->actingAs($ana)->get('/@beto')->assertInertia(fn ($page) => $page->component('profile/Private'));
    $this->actingAs($beto)->get('/@ana')->assertInertia(fn ($page) => $page->component('profile/Private'));
    $this->actingAs($ana)->get('/comunidad')->assertInertia(fn ($page) => $page->has('posts.data', 0));

    $this->actingAs($ana)->get('/comunidad/conexiones?tab=bloqueados')
        ->assertInertia(fn ($page) => $page->has('athletes.data', 1)->where('counts.bloqueados', 1));

    $this->actingAs($ana)->delete('/atletas/beto/bloquear')->assertRedirect();
    expect(UserBlock::query()->count())->toBe(0);
});

test('posts, comments and profiles can be reported from the web, never your own', function () {
    $author = v1Athlete('autor');
    $reporter = v1Athlete('reporta');
    $post = v1Post($author);
    $comment = LegacyMomentComment::create(['legacy_moment_id' => $post->id, 'user_id' => $author->id, 'body' => 'spam']);

    $this->actingAs($reporter)->post('/comunidad/reportes', ['target_type' => 'moment', 'target' => $post->uuid, 'reason' => 'spam'])->assertRedirect();
    $this->actingAs($reporter)->post('/comunidad/reportes', ['target_type' => 'comment', 'target' => $comment->uuid, 'reason' => 'harassment'])->assertRedirect();
    $this->actingAs($reporter)->post('/comunidad/reportes', ['target_type' => 'profile', 'target' => 'autor', 'reason' => 'impersonation'])->assertRedirect();
    expect(Report::query()->count())->toBe(3);

    $this->actingAs($author)->post('/comunidad/reportes', ['target_type' => 'moment', 'target' => $post->uuid, 'reason' => 'spam'])
        ->assertSessionHasErrors('reason');
});

test('moderation is admin-only, audited, and hide/restore works on posts and comments', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $author = v1Athlete('autor');
    $reporter = v1Athlete('reporta');
    $post = v1Post($author);
    $comment = LegacyMomentComment::create(['legacy_moment_id' => $post->id, 'user_id' => $reporter->id, 'body' => 'feo']);
    $report = Report::create(['reporter_id' => $reporter->id, 'target_type' => 'moment', 'target_id' => $post->id, 'reason' => 'spam']);

    $this->actingAs($reporter)->post("/admin/community/moments/{$post->uuid}/hide")->assertForbidden();
    $this->actingAs($reporter)->get('/admin/community')->assertForbidden();

    $this->actingAs($admin)->get('/admin/community?tab=reportes')
        ->assertInertia(fn ($page) => $page
            ->component('admin/community/Index')
            ->where('tab', 'reportes')
            ->where('reports.0.target.uuid', $post->uuid));

    $this->actingAs($admin)->post("/admin/community/moments/{$post->uuid}/hide", ['note' => 'Spam'])->assertRedirect();
    expect($post->fresh()->hidden_at)->not->toBeNull()
        ->and($report->fresh()->status)->toBe(ReportStatus::Resolved);
    $this->actingAs($reporter)->get("/comunidad/publicaciones/{$post->uuid}")->assertNotFound();

    $this->actingAs($admin)->post("/admin/community/comments/{$comment->uuid}/hide")->assertRedirect();
    $this->actingAs($admin)->get('/admin/community?tab=contenido')
        ->assertInertia(fn ($page) => $page->has('hiddenMoments', 1)->has('hiddenComments', 1));

    $this->actingAs($admin)->post("/admin/community/moments/{$post->uuid}/restore")->assertRedirect();
    expect($post->fresh()->hidden_at)->toBeNull();

    expect(DB::table('activity_log')->where('log_name', 'community')->where('causer_id', $admin->id)->count())->toBe(3);
});

// ---------------------------------------------------------------------
// API contract
// ---------------------------------------------------------------------

test('API feed exposes the v1 card contract and cursor meta', function () {
    $viewer = v1Athlete('viewer');
    $edition = EventEdition::factory()->create();
    v1Post($viewer, ['type' => 'race_completed', 'event_edition_id' => $edition->id]);

    Sanctum::actingAs($viewer);

    $this->getJson('/api/v1/feed')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['uuid', 'type', 'kind', 'kind_label', 'caption', 'visibility', 'created_at', 'is_owner', 'is_hidden',
                'share_url', 'event' => ['name', 'slug', 'edition', 'date'], 'author', 'media', 'reactions' => ['like', 'cheer', 'total'],
                'my_reactions', 'comments_count']],
            'meta' => ['next_cursor', 'per_page', 'scope'],
        ])
        ->assertJsonPath('data.0.kind', 'race_result');

    $this->getJson('/api/v1/community/meta')
        ->assertOk()
        ->assertJsonCount(6, 'data.post_kinds')
        ->assertJsonPath('data.limits.videos', false);
});

test('API followers lists include followers-only profiles only for people who follow them', function () {
    $owner = v1Athlete('owner');
    $hiddenFan = v1Athlete('fanprivado', 'followers');
    v1Follow($hiddenFan, $owner);
    $viewer = v1Athlete('viewer');

    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/athletes/owner/followers')->assertOk()->assertJsonCount(0, 'data');

    v1Follow($viewer, $hiddenFan);
    app(SocialVisibility::class)->forget($viewer);
    $this->getJson('/api/v1/athletes/owner/followers')->assertOk()->assertJsonCount(1, 'data');
});

test('the public profile web page links to followers lists', function () {
    $owner = v1Athlete('owner');
    $fan = v1Athlete('fan');
    v1Follow($fan, $owner);

    $this->get('/@owner/seguidores')
        ->assertInertia(fn ($page) => $page->component('community/Connections')->where('owner.username', 'owner')->has('athletes.data', 1));

    v1Athlete('cerrado', 'private');
    $this->get('/@cerrado/seguidores')->assertNotFound();
});
