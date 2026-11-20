<?php

use App\Actions\LegacyTags\ManageStoryShares;
use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Enums\MomentVisibility;
use App\Enums\StoryMediaRole;
use App\Enums\StoryVisibility;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\User;
use App\Notifications\ContentPinNotice;
use App\Services\Privacy\ContentPin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../LegacyTags/story-helpers.php';

/*
| Optional content PIN (App\Services\Privacy\ContentPin): set / change /
| disable / lockout / unlock / recovery, and that protected items stay
| hidden without an unlock — and always from other users and staff.
*/

beforeEach(function () {
    Storage::fake('local');
});

function pinUser(): User
{
    $user = User::factory()->create(['password' => 'secret-password']);
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => 'pin'.Str::lower(Str::random(6))]);

    return $user->fresh();
}

/** @return array<string, string> One Bearer token reused for the whole test (grants are bound to it). */
function pinHeaders(User $user): array
{
    return apiAuthHeader($user);
}

function enablePin(User $user, string $pin = '2580'): void
{
    app(ContentPin::class)->enable($user, $pin);
}

function pinStoryPhoto(LegacyStory $story, User $owner): MediaFile
{
    $uuid = (string) Str::uuid();
    Storage::disk('local')->put("stories/{$uuid}.jpg", 'jpeg');

    $file = MediaFile::query()->create([
        'uuid' => $uuid, 'mediable_type' => $story->getMorphClass(), 'mediable_id' => $story->id,
        'owner_user_id' => $owner->id, 'kind' => MediaKind::Image, 'status' => MediaFileStatus::Ready,
        'visibility' => 'private', 'disk' => 'local', 'directory' => 'stories', 'extension' => 'jpg', 'path' => "stories/{$uuid}.jpg", 'original_path' => "stories/{$uuid}.jpg",
        'mime' => 'image/jpeg', 'width' => 10, 'height' => 10, 'size_bytes' => 4, 'checksum' => str_repeat('a', 64),
    ]);

    DB::table('legacy_story_media')->insert([
        'legacy_story_id' => $story->id, 'media_file_id' => $file->id, 'role' => StoryMediaRole::Photo->value,
        'source' => 'upload', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $file;
}

test('activating stores only a hash, confirms the PIN and never returns it', function () {
    $user = pinUser();
    $h = pinHeaders($user);

    $this->withHeaders($h)->postJson('/api/v1/me/pin', ['pin' => '2580', 'pin_confirmation' => '1111'])
        ->assertStatus(422);
    $this->withHeaders($h)->postJson('/api/v1/me/pin', ['pin' => '12', 'pin_confirmation' => '12'])->assertStatus(422);

    $response = $this->withHeaders($h)->postJson('/api/v1/me/pin', ['pin' => '2580', 'pin_confirmation' => '2580'])
        ->assertCreated()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.max_attempts', 5);

    expect($response->getContent())->not->toContain('2580')
        ->and($user->fresh()->content_pin_hash)->not->toBeNull()->not->toContain('2580')
        ->and($user->fresh()->toArray())->not->toHaveKey('content_pin_hash');

    $this->withHeaders($h)->postJson('/api/v1/me/pin', ['pin' => '9999', 'pin_confirmation' => '9999'])
        ->assertStatus(409)->assertJsonPath('error.code', 'PIN_ALREADY_SET');
});

test('changing requires the current PIN and disabling takes the PIN or the password', function () {
    $user = pinUser();
    enablePin($user);
    $h = pinHeaders($user);

    $this->withHeaders($h)->putJson('/api/v1/me/pin', ['current_pin' => '0000', 'pin' => '135790', 'pin_confirmation' => '135790'])
        ->assertStatus(422)->assertJsonPath('error.code', 'PIN_INVALID')->assertJsonPath('error.details.remaining_attempts', 4);

    $this->withHeaders($h)->putJson('/api/v1/me/pin', ['current_pin' => '2580', 'pin' => '135790', 'pin_confirmation' => '135790'])->assertOk();
    $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '135790'])->assertOk();

    $this->withHeaders($h)->deleteJson('/api/v1/me/pin', ['password' => 'wrong'])->assertStatus(422);
    $this->withHeaders($h)->deleteJson('/api/v1/me/pin', ['password' => 'secret-password'])
        ->assertOk()->assertJsonPath('data.enabled', false);
    expect($user->fresh()->content_pin_hash)->toBeNull();

    enablePin($user->fresh(), '4444');
    $this->withHeaders(pinHeaders($user))->deleteJson('/api/v1/me/pin', ['pin' => '4444'])->assertOk()->assertJsonPath('data.enabled', false);
});

test('five wrong PINs lock it server-side with a growing delay', function () {
    $user = pinUser();
    enablePin($user);
    $h = pinHeaders($user);

    foreach (range(1, 4) as $i) {
        $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '0000'])
            ->assertStatus(422)->assertJsonPath('error.details.remaining_attempts', 5 - $i);
    }

    $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '0000'])
        ->assertStatus(429)->assertJsonPath('error.code', 'PIN_LOCKED')->assertJsonPath('error.details.retry_after', 60);

    // Locked: even the right PIN is refused (no oracle).
    $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '2580'])->assertStatus(429);

    $this->travel(61)->seconds();
    $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '0000'])
        ->assertStatus(429)->assertJsonPath('error.details.retry_after', 120);

    $this->travel(121)->seconds();
    $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '2580'])->assertOk();
    expect($user->fresh()->content_pin_failed_attempts)->toBe(0)
        ->and(ContentPin::lockMinutes(30))->toBe(60);
});

test('a protected memory is a placeholder until unlocked, then readable with the grant', function () {
    [$story, $owner] = ownedStory(StoryVisibility::Public);
    pinStoryPhoto($story, $owner);
    enablePin($owner);
    $h = pinHeaders($owner);

    $this->withHeaders($h)->putJson('/api/v1/me/pin/items', ['type' => 'story', 'id' => $story->public_id, 'protected' => true])
        ->assertOk()->assertJsonPath('data.pin_protected', true);
    expect($story->fresh()->visibility)->toBe(StoryVisibility::Private);

    $locked = $this->withHeaders($h)->getJson('/api/v1/me/stories')->assertOk();
    $locked->assertJsonPath('data.0.locked', true)
        ->assertJsonPath('data.0.title', null)
        ->assertJsonPath('data.0.photos', [])
        ->assertJsonPath('data.0.cover_url', null);
    expect($locked->getContent())->not->toContain('Mi medio maratón')->not->toContain('/media/f/');

    $this->withHeaders($h)->getJson("/api/v1/me/stories/{$story->public_id}")
        ->assertStatus(423)->assertJsonPath('error.code', 'CONTENT_LOCKED');
    // Unmarking needs the unlock too.
    $this->withHeaders($h)->putJson('/api/v1/me/pin/items', ['type' => 'story', 'id' => $story->public_id, 'protected' => false])
        ->assertStatus(423);

    $grant = $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '2580'])
        ->assertOk()->assertJsonPath('data.expires_in', ContentPin::GRANT_TTL_SECONDS)
        ->json('data.unlock_token');

    $open = $this->withHeaders([...$h, 'X-Content-Unlock' => $grant])->getJson("/api/v1/stories/{$story->public_id}")
        ->assertOk()->assertJsonPath('data.locked', false)->assertJsonPath('data.title', 'Mi medio maratón');
    $url = (string) $open->json('data.photos.0.url');
    expect($url)->toContain('pin=1');
    $this->get($url)->assertOk();

    $this->withHeaders([...$h, 'X-Content-Unlock' => $grant])->getJson("/api/v1/me/stories/{$story->public_id}")->assertOk();

    // The grant is bound to the Bearer token that asked for it…
    $this->withHeaders([...pinHeaders($owner), 'X-Content-Unlock' => $grant])->getJson("/api/v1/stories/{$story->public_id}")
        ->assertJsonPath('data.locked', true);

    // …and dies when it is revoked.
    app('auth')->forgetGuards();
    $this->withHeaders([...$h, 'X-Content-Unlock' => $grant])->postJson('/api/v1/me/pin/lock')->assertOk();
    $this->withHeaders([...$h, 'X-Content-Unlock' => $grant])->getJson("/api/v1/stories/{$story->public_id}")
        ->assertJsonPath('data.locked', true);
});

test('changing the PIN revokes outstanding grants and old media URLs stop working', function () {
    [$story, $owner] = ownedStory(StoryVisibility::Private);
    $file = pinStoryPhoto($story, $owner);
    $preProtectionUrl = $file->url();
    enablePin($owner);
    $story->forceFill(['pin_protected' => true])->save();
    $h = pinHeaders($owner);

    // A private signed URL minted before the protection is refused now.
    $this->get((string) $preProtectionUrl)->assertForbidden();

    $grant = $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '2580'])->json('data.unlock_token');
    $this->withHeaders($h)->putJson('/api/v1/me/pin', ['current_pin' => '2580', 'pin' => '8642', 'pin_confirmation' => '8642'])->assertOk();

    $this->withHeaders([...$h, 'X-Content-Unlock' => $grant])->getJson("/api/v1/stories/{$story->public_id}")
        ->assertJsonPath('data.locked', true);
});

test('other users, recipient links and tag opens never see a protected memory', function () {
    [$story, $owner, $tag] = ownedStory(StoryVisibility::Public);
    enablePin($owner);
    $share = app(ManageStoryShares::class)->create($story, $owner, 'Mamá');
    $story->forceFill(['pin_protected' => true])->save();
    $stranger = pinUser();

    $this->withHeaders(pinHeaders($stranger))->getJson("/api/v1/stories/{$story->public_id}")->assertNotFound();
    $this->getJson("/api/v1/stories/{$story->public_id}")->assertNotFound();
    $this->getJson("/api/v1/tags/{$tag->public_code}")->assertOk()->assertJsonPath('data.story_public_id', null);
    $this->get("/legacy/para/{$share->token}")->assertNotFound();
});

test('a protected moment disappears for everyone else and is a placeholder for its owner', function () {
    $owner = pinUser();
    enablePin($owner);
    $moment = LegacyMoment::create(['user_id' => $owner->id, 'type' => 'manual', 'caption' => 'Mi secreto', 'visibility' => MomentVisibility::Public]);
    $h = pinHeaders($owner);

    $this->withHeaders($h)->putJson('/api/v1/me/pin/items', ['type' => 'moment', 'id' => $moment->uuid, 'protected' => true])->assertOk();
    expect($moment->fresh()->visibility)->toBe(MomentVisibility::Private);

    $stranger = pinUser();
    $this->withHeaders(pinHeaders($stranger))->getJson("/api/v1/moments/{$moment->uuid}")->assertNotFound();
    app('auth')->forgetGuards();

    $this->withHeaders($h)->getJson("/api/v1/moments/{$moment->uuid}")
        ->assertOk()->assertJsonPath('data.locked', true)->assertJsonPath('data.caption', null);
    $this->withHeaders($h)->patchJson("/api/v1/moments/{$moment->uuid}", ['caption' => 'x'])->assertStatus(423);

    $grant = $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '2580'])->json('data.unlock_token');
    $this->withHeaders([...$h, 'X-Content-Unlock' => $grant])->getJson("/api/v1/moments/{$moment->uuid}")
        ->assertJsonPath('data.locked', false)->assertJsonPath('data.caption', 'Mi secreto');

    // Turning the PIN off removes the flag but keeps the moment private.
    $this->withHeaders($h)->deleteJson('/api/v1/me/pin', ['pin' => '2580'])->assertOk();
    expect($moment->fresh()->pin_protected)->toBeFalse()->and($moment->fresh()->visibility)->toBe(MomentVisibility::Private);
});

test('staff with media.manage cannot open protected media', function () {
    [$story, $owner] = ownedStory(StoryVisibility::Private);
    $file = pinStoryPhoto($story, $owner);
    enablePin($owner);
    $story->forceFill(['pin_protected' => true])->save();

    Permission::findOrCreate('media.manage', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('media.manage');

    expect(Gate::forUser($admin)->allows('view', $file->fresh()))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('viewOriginal', $file->fresh()))->toBeFalse()
        ->and($file->fresh()->url())->toBeNull();

    $this->actingAs($admin)->get("/media/f/{$file->uuid}/default")->assertForbidden();
});

test('a forgotten PIN is reset with the password or an emailed code', function () {
    Notification::fake();
    $user = pinUser();
    enablePin($user);
    $h = pinHeaders($user);

    $this->withHeaders($h)->postJson('/api/v1/me/pin/reset', ['password' => 'nope', 'pin' => '1357', 'pin_confirmation' => '1357'])->assertStatus(422);
    $this->withHeaders($h)->postJson('/api/v1/me/pin/reset', ['password' => 'secret-password', 'pin' => '1357', 'pin_confirmation' => '1357'])->assertOk();
    $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '1357'])->assertOk();

    $this->withHeaders($h)->postJson('/api/v1/me/pin/recovery-code')->assertOk();
    $code = null;
    Notification::assertSentTo($user, ContentPinNotice::class, function (ContentPinNotice $n) use (&$code, $user) {
        if ($n->kind === ContentPinNotice::RECOVERY_CODE) {
            preg_match('/\*\*(\d{6})\*\*/', implode("\n", $n->toMail($user)->introLines), $m);
            $code = $m[1] ?? null;
        }

        return true;
    });
    expect($code)->not->toBeNull();

    $this->withHeaders($h)->postJson('/api/v1/me/pin/reset', ['code' => '000000', 'pin' => '2468', 'pin_confirmation' => '2468'])->assertStatus(422);
    $this->withHeaders($h)->postJson('/api/v1/me/pin/reset', ['code' => $code, 'pin' => '2468', 'pin_confirmation' => '2468'])->assertOk();
    $this->withHeaders($h)->postJson('/api/v1/me/pin/unlock', ['pin' => '2468'])->assertOk();
    // One-time code.
    $this->withHeaders($h)->postJson('/api/v1/me/pin/reset', ['code' => $code, 'pin' => '1111', 'pin_confirmation' => '1111'])->assertStatus(422);
});

test('the owner opening a protected memory on the web is sent to the unlock page', function () {
    [$story, $owner] = ownedStory(StoryVisibility::Private);
    enablePin($owner);
    $story->forceFill(['pin_protected' => true])->save();

    $this->actingAs($owner)->get("/legacy/{$story->public_id}")
        ->assertRedirect(route('content-pin.show', ['redirect' => "/legacy/{$story->public_id}"]));

    $this->actingAs($owner)->post('/settings/content-pin/unlock', ['pin' => '2580', 'redirect' => "/legacy/{$story->public_id}"])
        ->assertRedirect("/legacy/{$story->public_id}");
    $this->actingAs($owner)->get("/legacy/{$story->public_id}")->assertOk();
});
