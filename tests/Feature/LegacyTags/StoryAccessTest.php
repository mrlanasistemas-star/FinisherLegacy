<?php

use App\Enums\StoryMediaRole;
use App\Enums\StoryVisibility;
use App\Enums\TagScanEvent;
use App\Models\LegacyStoryShare;
use App\Models\LegacyTagScan;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/story-helpers.php';

beforeEach(function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
});

test('visibility matrix: owner, public, unlisted, private', function () {
    [$public, $owner] = ownedStory(StoryVisibility::Public);
    [$unlisted] = ownedStory(StoryVisibility::Unlisted);
    [$private, $privateOwner] = ownedStory(StoryVisibility::Private);
    $stranger = User::factory()->create();

    // Public: anyone, indexable (no X-Robots-Tag).
    $response = $this->get(route('legacy.story.show', $public->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('legacy/Story')->where('mode', 'visitor')->where('noindex', false)->where('shares', []));
    expect($response->headers->get('X-Robots-Tag'))->toBeNull();

    // Unlisted: anyone with the link / tag, never indexed.
    $this->get(route('legacy.story.show', $unlisted->public_id))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->component('legacy/Story')->where('noindex', true));

    // Private: only its owner.
    $this->get(route('legacy.story.show', $private->public_id))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->component('legacy/Private'));
    $this->actingAs($stranger)->get(route('legacy.story.show', $private->public_id))
        ->assertInertia(fn ($page) => $page->component('legacy/Private'));
    $this->actingAs($privateOwner)->get(route('legacy.story.show', $private->public_id))
        ->assertInertia(fn ($page) => $page->component('legacy/Story')->where('mode', 'owner')->where('story.display_code', fn ($c) => $c !== null));

    // Owner of a public story sees owner mode.
    $this->actingAs($owner)->get(route('legacy.story.show', $public->public_id))
        ->assertInertia(fn ($page) => $page->where('mode', 'owner'));

    expect(LegacyTagScan::query()->where('event', TagScanEvent::LegacyViewed->value)->count())->toBeGreaterThanOrEqual(4);
});

test('opening a private story through its tag shows the private page to strangers', function () {
    [, , $tag] = ownedStory(StoryVisibility::Private);

    $this->get("/t/{$tag->public_code}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('legacy/Private'));
});

test('opening an unlisted story through its tag redirects to the story', function () {
    [$story, , $tag] = ownedStory(StoryVisibility::Unlisted);

    $this->get("/t/{$tag->public_code}?src=qr")->assertRedirect(route('legacy.story.show', $story->public_id));
    $this->get(route('legacy.story.show', $story->public_id))->assertOk();

    expect(LegacyTagScan::query()->where('event', TagScanEvent::LegacyViewed->value)->value('source')->value)->toBe('qr');
});

test('recipient share links open even private stories and can be revoked', function () {
    [$story, $owner] = ownedStory(StoryVisibility::Private);

    $this->actingAs($owner)->post(route('my-legacy.shares.store', $story->public_id), ['label' => 'Mamá'])
        ->assertRedirect()
        ->assertInertiaFlash('share_url');

    $share = LegacyStoryShare::query()->sole();
    expect(strlen($share->token))->toBeGreaterThanOrEqual(32);

    auth()->logout();
    $this->get(route('legacy.share.show', $share->token))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->component('legacy/Story')->where('mode', 'recipient')->where('recipientLabel', 'Mamá'));

    expect($share->fresh()->open_count)->toBe(1)
        ->and(LegacyTagScan::query()->where('event', TagScanEvent::ShareOpened->value)->count())->toBe(1);

    $this->actingAs($owner)->delete(route('my-legacy.shares.destroy', [$story->public_id, $share->id]))->assertRedirect();

    auth()->logout();
    $this->get(route('legacy.share.show', $share->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('legacy/Unavailable')->where('kind', 'revoked'));

    $this->get(route('legacy.share.show', str_repeat('x', 40)))->assertNotFound();
});

test('IDOR: another user cannot edit, upload, delete, share or print my story', function () {
    [$story, $owner] = ownedStory();
    $this->actingAs($owner)->post(route('my-legacy.media.store', $story->public_id), ['role' => 'photo', 'file' => UploadedFile::fake()->image('a.jpg', 400, 300)]);
    $pivotId = (int) DB::table('legacy_story_media')->value('id');
    $this->actingAs($owner)->post(route('my-legacy.shares.store', $story->public_id), ['label' => 'Mamá']);
    $share = LegacyStoryShare::query()->sole();

    $intruder = User::factory()->create();
    $this->actingAs($intruder);

    $this->get(route('my-legacy.edit', $story->public_id))->assertForbidden();
    $this->patch(route('my-legacy.update', $story->public_id), ['visibility' => 'public', 'title' => 'hack'])->assertForbidden();
    $this->post(route('my-legacy.media.store', $story->public_id), ['role' => 'photo', 'file' => UploadedFile::fake()->image('b.jpg')])->assertForbidden();
    $this->patch(route('my-legacy.media.update', [$story->public_id, $pivotId]), ['caption' => 'x'])->assertForbidden();
    $this->patch(route('my-legacy.media.reorder', $story->public_id), ['order' => [$pivotId]])->assertForbidden();
    $this->delete(route('my-legacy.media.destroy', [$story->public_id, $pivotId]))->assertForbidden();
    $this->post(route('my-legacy.shares.store', $story->public_id), ['label' => 'x'])->assertForbidden();
    $this->delete(route('my-legacy.shares.destroy', [$story->public_id, $share->id]))->assertForbidden();
    $this->get(route('my-legacy.print', $story->public_id))->assertForbidden();
    $this->post(route('my-legacy.print.download', $story->public_id), ['layout' => 'classic', 'photo_id' => $pivotId])->assertForbidden();

    // The intruder's own story cannot be used to reach my media rows.
    [$theirs] = ownedStory(owner: $intruder);
    $this->delete(route('my-legacy.media.destroy', [$theirs->public_id, $pivotId]))->assertNotFound();

    expect($story->fresh()->title)->toBe('Mi medio maratón')
        ->and($story->fresh()->visibility)->toBe(StoryVisibility::Unlisted)
        ->and(DB::table('legacy_story_media')->count())->toBe(1)
        ->and($share->fresh()->revoked_at)->toBeNull();

    // Guests are sent to login.
    auth()->logout();
    $this->get(route('my-legacy.edit', $story->public_id))->assertRedirect(route('login'));
    $this->get(route('my-legacy.index'))->assertRedirect(route('login'));
});

test('the owner edits the story and adds a real photo through MediaStorage', function () {
    [$story, $owner] = ownedStory();
    $this->actingAs($owner);

    $this->get(route('my-legacy.edit', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('my-legacy/Edit')->has('visibilityOptions', 3));

    $this->patch(route('my-legacy.update', $story->public_id), [
        'title' => 'Para mamá, mi primer maratón',
        'visibility' => 'private',
        'recipient_label' => 'Mamá',
        'dedication' => 'Cada kilómetro fue por ti.',
        'race_mode' => 'manual',
        'event_name' => 'Maratón CDMX',
        'result_time' => '3:58:12',
        'external_url' => 'https://strava.com/activities/1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $story->refresh();
    expect($story->visibility)->toBe(StoryVisibility::Private)
        ->and($story->event_name)->toBe('Maratón CDMX')
        ->and($story->dedication)->toBe('Cada kilómetro fue por ti.');

    $this->post(route('my-legacy.media.store', $story->public_id), ['role' => 'photo', 'file' => UploadedFile::fake()->image('meta.jpg', 1200, 800), 'caption' => 'La meta'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $file = MediaFile::query()->sole();
    expect($file->mediable_type)->toBe($story->getMorphClass())
        ->and($file->mediable_id)->toBe($story->id)
        ->and($file->visibility->value)->toBe('private')
        ->and(DB::table('legacy_story_media')->value('role'))->toBe(StoryMediaRole::Photo->value);

    $this->get(route('my-legacy.edit', $story->public_id))
        ->assertInertia(fn ($page) => $page->where('story.photos.0.caption', 'La meta'));

    // Library groups by year.
    $this->get(route('my-legacy.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('my-legacy/Index')->where('total', 1)->has('years.0.stories', 1));
});

test('uploads with a fake extension are rejected by the sniffed type', function () {
    [$story, $owner] = ownedStory();
    $this->actingAs($owner);

    $this->post(route('my-legacy.media.store', $story->public_id), [
        'role' => 'photo',
        'file' => sniffedUpload('foto.jpg', '<?php echo "not an image"; ?>'),
    ])->assertSessionHasErrors('file');

    $png = sniffedUpload('voz.mp3', pngBytes());
    $this->post(route('my-legacy.media.store', $story->public_id), ['role' => 'audio', 'file' => $png])
        ->assertSessionHasErrors('file');

    $this->post(route('my-legacy.media.store', $story->public_id), [
        'role' => 'certificate',
        'file' => sniffedUpload('certificado.pdf', 'MZ fake executable'),
    ])->assertSessionHasErrors('file');

    expect(DB::table('legacy_story_media')->count())->toBe(0)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('a real pdf certificate is accepted and replaces the previous one', function () {
    [$story, $owner] = ownedStory();
    $this->actingAs($owner);
    $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    $this->post(route('my-legacy.media.store', $story->public_id), ['role' => 'certificate', 'file' => sniffedUpload('c1.pdf', $pdf)])->assertSessionHasNoErrors();
    $this->post(route('my-legacy.media.store', $story->public_id), ['role' => 'certificate', 'file' => sniffedUpload('c2.pdf', $pdf)])->assertSessionHasNoErrors();

    expect(DB::table('legacy_story_media')->where('role', 'certificate')->count())->toBe(1);
});

test('the owner downloads a print-ready pdf from their own photo', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->get(route('my-legacy.print', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('my-legacy/Print')->has('layouts', 3));

    $response = $this->actingAs($owner)->post(route('my-legacy.print.download', $story->public_id), [
        'layout' => 'classic',
        'photo' => UploadedFile::fake()->image('mia.jpg', 900, 1200),
        'dedication' => 'Para ti',
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-');

    $this->actingAs($owner)->postJson(route('my-legacy.print.download', $story->public_id), [
        'layout' => 'classic',
        'photo' => sniffedUpload('x.jpg', 'nope'),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');
});
