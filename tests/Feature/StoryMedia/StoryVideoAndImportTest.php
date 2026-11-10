<?php

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\Photos\ReversePhotoSale;
use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Enums\StoryQuotaKind;
use App\Enums\StoryVisibility;
use App\Events\Commerce\OrderRefunded;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Models\MediaFile;
use App\Models\PhotoQuotaRule;
use App\Models\User;
use App\Services\Media\StoryMediaQuota;
use App\Services\Media\VideoTranscoder;
use App\Support\LegacyTags\StoryPresenter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/story-media-helpers.php';

/*
| Videos in the story (type, size, duration and count limits, signed URLs
| for private stories) and event photos bought on the site (import,
| quota, no duplicated original, removal on refund) — web and API.
*/

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('media_tmp');
    Storage::fake('local');
    Storage::fake('event_photo_originals');
    Storage::fake('public');
});

/** Makes ffprobe "measure" the given duration for every upload. */
function fakeVideoDuration(?int $seconds): void
{
    $probe = Mockery::mock(VideoTranscoder::class);
    $probe->shouldReceive('probe')->andReturn(['duration_ms' => $seconds !== null ? $seconds * 1000 : null, 'width' => 1280, 'height' => 720]);
    $probe->shouldReceive('available')->andReturn(false);
    app()->instance(VideoTranscoder::class, $probe);
}

function mp4Upload(string $name = 'clip.mp4', int $kb = 500): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, 'video/mp4');
}

/* ---------------- videos ---------------- */

test('a video is accepted, attached with its role and queued for the pipeline', function () {
    Bus::fake();
    fakeVideoDuration(20);
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->post(route('my-legacy.media.store', $story->public_id), ['role' => 'video', 'file' => mp4Upload()])
        ->assertSessionHasNoErrors();

    $file = MediaFile::query()->sole();

    expect(storyMediaCount($story, 'video'))->toBe(1)
        ->and($file->kind)->toBe(MediaKind::Video)
        ->and($file->status)->toBe(MediaFileStatus::Processing)
        ->and($file->duration_ms)->toBe(20000);

    // The owner sees it as "processing"; a visitor sees nothing until it is ready.
    $presenter = app(StoryPresenter::class);
    expect($presenter->story($story->fresh(), forOwner: true)['videos'])->toHaveCount(1)
        ->and($presenter->story($story->fresh())['videos'])->toBe([]);
});

test('only real video types are accepted and the size limit comes from config', function () {
    Bus::fake();
    fakeVideoDuration(10);
    [$story, $owner] = ownedStory();
    $media = app(ManageStoryMedia::class);

    // A text file renamed .mp4: the sniffed type decides, not the name.
    expect(fn () => $media->add($story, $owner, sniffedUpload('clip.mp4', 'esto no es un video'), StoryMediaRole::Video))
        ->toThrow(ValidationException::class);

    // An image sent as a video is refused too.
    expect(fn () => $media->add($story, $owner, UploadedFile::fake()->image('foto.jpg', 400, 300), StoryMediaRole::Video))
        ->toThrow(ValidationException::class);

    config(['legacy.stories.max_video_bytes' => 100 * 1024]);
    expect(fn () => $media->add($story, $owner, mp4Upload('big.mp4', 500), StoryMediaRole::Video))
        ->toThrow(ValidationException::class, 'demasiado pesado');

    foreach (['clip.mov' => 'video/quicktime', 'clip.webm' => 'video/webm'] as $name => $mime) {
        config(['legacy.stories.max_videos' => 5, 'legacy.stories.max_video_bytes' => 10 * 1024 * 1024]);
        $media->add($story, $owner, UploadedFile::fake()->create($name, 200, $mime), StoryMediaRole::Video);
    }

    expect(storyMediaCount($story, 'video'))->toBe(2)
        ->and(Storage::disk('media_tmp')->allFiles('incoming'))->toHaveCount(2);
});

test('a video longer than the admin limit is refused and leaves nothing behind', function () {
    Bus::fake();
    [$story, $owner] = ownedStory();
    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::GLOBAL_KEY, 'max_video_seconds' => 30, 'included_videos' => 3]);
    $media = app(ManageStoryMedia::class);

    // The client-measured duration is a cheap early refusal…
    fakeVideoDuration(null);
    expect(fn () => $media->add($story, $owner, mp4Upload(), StoryMediaRole::Video, null, 45))
        ->toThrow(ValidationException::class, 'dura más de lo permitido');

    // …and the real one, measured from the bytes, is authoritative.
    fakeVideoDuration(45);
    expect(fn () => app(ManageStoryMedia::class)->add($story, $owner, mp4Upload(), StoryMediaRole::Video, null, 10))
        ->toThrow(ValidationException::class, 'dura más de lo permitido');

    expect(MediaFile::query()->count())->toBe(0)
        ->and(storyMediaCount($story, 'video'))->toBe(0)
        ->and(Storage::disk('media_tmp')->allFiles('incoming'))->toBe([]);

    // One second of slack; exactly at the limit passes.
    fakeVideoDuration(31);
    app(ManageStoryMedia::class)->add($story, $owner, mp4Upload(), StoryMediaRole::Video);
    expect(storyMediaCount($story, 'video'))->toBe(1);
});

test('the video count limit answers MEDIA_LIMIT_REACHED with the kind on the API', function () {
    Bus::fake();
    fakeVideoDuration(10);
    config(['legacy.stories.max_videos' => 1]);
    [$story, $owner] = ownedStory();
    $headers = apiAuthHeader($owner);

    $this->post("/api/v1/me/stories/{$story->public_id}/media", ['role' => 'video', 'file' => mp4Upload()], [...$headers, 'Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.role', 'video')
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.source', 'upload')
        ->assertJsonPath('data.url', null)
        ->assertJsonPath('meta.quota.videos.used', 1)
        ->assertJsonPath('meta.quota.videos.remaining', 0);

    $this->post("/api/v1/me/stories/{$story->public_id}/media", ['role' => 'video', 'file' => mp4Upload('two.mp4')], [...$headers, 'Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'MEDIA_LIMIT_REACHED')
        ->assertJsonPath('error.code', 'MEDIA_LIMIT_REACHED')
        ->assertJsonPath('error.details.kind', 'videos')
        ->assertJsonPath('error.details.limit', 1)
        ->assertJsonPath('error.details.used', 1)
        ->assertJsonPath('error.details.can_expand', false);

    // Photos have their own, independent limit.
    $this->post("/api/v1/me/stories/{$story->public_id}/media", ['role' => 'photo', 'file' => UploadedFile::fake()->image('a.jpg', 400, 300)], [...$headers, 'Accept' => 'application/json'])
        ->assertCreated();

    expect(storyMediaCount($story, 'video'))->toBe(1)->and(storyMediaCount($story, 'photo'))->toBe(1);
});

test('a ready video is served with signed urls: short-lived for a private story, public for a public one', function () {
    Bus::fake();
    fakeVideoDuration(12);
    [$story, $owner] = ownedStory(StoryVisibility::Private);
    $file = app(ManageStoryMedia::class)->add($story, $owner, mp4Upload(), StoryMediaRole::Video);

    Storage::disk('local')->put('v/clip.mp4', 'mp4-bytes');
    Storage::disk('local')->put('v/poster.jpg', 'jpg-bytes');
    $file->forceFill([
        'status' => MediaFileStatus::Ready, 'disk' => 'local', 'path' => 'v/clip.mp4', 'poster_path' => 'v/poster.jpg',
        'variants' => ['video' => ['path' => 'v/clip.mp4', 'mime' => 'video/mp4'], 'poster' => ['path' => 'v/poster.jpg', 'mime' => 'image/jpeg']],
    ])->save();

    $video = app(StoryPresenter::class)->story($story->fresh(), forOwner: true)['videos'][0];

    expect($video['url'])->toContain('signature=')->not->toContain('scope=public')
        ->and($video['poster_url'])->toContain('signature=')
        ->and($video['duration_seconds'])->toBe(12)
        ->and($video['status'])->toBe('ready');

    // Without the signature a stranger cannot fetch the private video.
    $unsigned = route('media.file', ['mediaFile' => $file->uuid, 'variant' => 'default']);
    $this->actingAs(User::factory()->create())->get($unsigned)->assertForbidden();
    auth()->logout();
    $this->get($video['url'])->assertOk();

    $story->forceFill(['visibility' => StoryVisibility::Public])->save();
    expect(app(StoryPresenter::class)->story($story->fresh())['videos'][0]['url'])->toContain('scope=public');
});

/* ---------------- API: list, delete, reorder, ownership ---------------- */

test('api: media list, reorder and delete — owner only', function () {
    Bus::fake();
    fakeVideoDuration(8);
    config(['legacy.stories.max_photos' => 5, 'legacy.stories.max_videos' => 2]);
    [$story, $owner] = ownedStory();
    $media = app(ManageStoryMedia::class);
    $a = $media->add($story, $owner, UploadedFile::fake()->image('a.jpg', 400, 300), StoryMediaRole::Photo);
    $b = $media->add($story, $owner, UploadedFile::fake()->image('b.jpg', 400, 300), StoryMediaRole::Photo);
    $v = $media->add($story, $owner, mp4Upload(), StoryMediaRole::Video);
    $headers = apiAuthHeader($owner);

    $this->getJson("/api/v1/me/stories/{$story->public_id}/media", $headers)
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['uuid', 'role', 'url', 'thumb_url', 'poster_url', 'duration_seconds', 'status', 'sort_order', 'source']],
            'meta' => ['quota' => [
                'photos' => ['limit', 'used', 'remaining', 'can_expand', 'plan'],
                'videos' => ['limit', 'used', 'remaining', 'can_expand', 'max_seconds', 'plan'],
                'audio' => ['limit', 'used', 'remaining', 'can_expand', 'max_seconds', 'plan'],
            ]],
        ])
        ->assertJsonPath('data.0.uuid', $a->uuid)
        ->assertJsonPath('meta.quota.photos.limit', 5)
        ->assertJsonPath('meta.quota.photos.used', 2)
        ->assertJsonPath('meta.quota.videos.max_seconds', 60)
        ->assertJsonPath('meta.quota.audio.max_seconds', 180);

    $this->postJson("/api/v1/me/stories/{$story->public_id}/media/reorder", ['order' => [$v->uuid, $b->uuid, $a->uuid]], $headers)->assertNoContent();

    expect(collect($this->getJson("/api/v1/me/stories/{$story->public_id}/media", $headers)->json('data'))->pluck('uuid')->all())
        ->toBe([$v->uuid, $b->uuid, $a->uuid]);

    $this->deleteJson("/api/v1/me/stories/{$story->public_id}/media/{$b->uuid}", [], $headers)->assertNoContent();
    $this->deleteJson("/api/v1/me/stories/{$story->public_id}/media/{$b->uuid}", [], $headers)->assertNotFound();
    expect(storyMediaCount($story, 'photo'))->toBe(1);

    [$other] = ownedStory();
    $this->getJson("/api/v1/me/stories/{$other->public_id}/media", $headers)->assertNotFound();
    $this->postJson("/api/v1/me/stories/{$other->public_id}/media/reorder", ['order' => [$a->uuid]], $headers)->assertNotFound();
    $this->deleteJson("/api/v1/me/stories/{$other->public_id}/media/{$a->uuid}", [], $headers)->assertNotFound();
    $this->post("/api/v1/me/stories/{$other->public_id}/media", ['role' => 'photo', 'file' => UploadedFile::fake()->image('x.jpg', 400, 300)], [...$headers, 'Accept' => 'application/json'])->assertNotFound();
    $this->postJson("/api/v1/me/stories/{$other->public_id}/media/import", ['sale_ids' => [$a->uuid]], $headers)->assertNotFound();
});

/* ---------------- purchased photos ---------------- */

test('the owner adds a purchased photo: it counts against the quota and the original is not duplicated', function () {
    // The real pipeline runs (sync queue): renditions are rendered from the purchased original.
    config(['legacy.stories.max_photos' => 2]);
    [$story, $owner] = ownedStory();
    $sale = purchasedPhotoSale($owner);
    $originalPath = $sale->photo->original_path;

    $files = app(ManageStoryMedia::class)->importPurchased($story, $owner, [$sale->uuid]);
    $file = $files[0]->fresh();

    expect($files)->toHaveCount(1)
        ->and($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->mediable_id)->toBe($story->id)
        // No second full-resolution copy: only web renditions live with the story.
        ->and($file->original_path)->toBeNull()
        ->and(array_keys($file->variants ?? []))->toContain('display', 'card', 'thumb')
        ->and(Storage::disk('event_photo_originals')->exists($originalPath))->toBeTrue()
        ->and(app(StoryMediaQuota::class)->usedFor($story, StoryQuotaKind::Photos))->toBe(1);

    $photo = app(StoryPresenter::class)->story($story->fresh(), forOwner: true)['photos'][0];
    expect($photo['source'])->toBe('purchased')->and($photo['status'])->toBe('ready');

    // Importing the same sale again is a no-op.
    expect(app(ManageStoryMedia::class)->importPurchased($story, $owner, [$sale->uuid]))->toBe([])
        ->and(storyMediaCount($story, 'photo'))->toBe(1);

    // Removing it from the story deletes the renditions, never the purchased original.
    $rendition = $file->variants['display']['path'];
    expect(app(ManageStoryMedia::class)->removeByUuid($story, $file->uuid))->toBeTrue()
        ->and(Storage::disk('local')->exists($rendition))->toBeFalse()
        ->and(Storage::disk('event_photo_originals')->exists($originalPath))->toBeTrue()
        ->and(MediaFile::query()->whereKey($file->id)->exists())->toBeFalse();
});

test('import respects the photo quota and only accepts the caller\'s own, non-refunded purchases', function () {
    Bus::fake();
    config(['legacy.stories.max_photos' => 1]);
    [$story, $owner] = ownedStory();
    $first = purchasedPhotoSale($owner);
    $second = purchasedPhotoSale($owner);
    $foreign = purchasedPhotoSale(User::factory()->create());
    $media = app(ManageStoryMedia::class);

    expect(fn () => $media->importPurchased($story, $owner, [$foreign->uuid]))->toThrow(ValidationException::class);

    // Two at once into one free slot: all-or-nothing.
    expect(fn () => $media->importPurchased($story, $owner, [$first->uuid, $second->uuid]))->toThrow(PhotoQuotaExceededException::class);
    expect(storyMediaCount($story, 'photo'))->toBe(0)->and(MediaFile::query()->count())->toBe(0);

    $media->importPurchased($story, $owner, [$first->uuid]);
    expect(fn () => $media->importPurchased($story, $owner, [$second->uuid]))->toThrow(PhotoQuotaExceededException::class);

    app(ReversePhotoSale::class)->handle($second, 'Reembolso de prueba');
    config(['legacy.stories.max_photos' => 5]);
    expect(fn () => $media->importPurchased($story, $owner, [$second->uuid]))->toThrow(ValidationException::class);
});

test('a refunded sale removes the imported photo from the story (single reversal and full order refund)', function () {
    Bus::fake();
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner] = ownedStory();
    $kept = purchasedPhotoSale($owner);
    $reversed = purchasedPhotoSale($owner);
    $refunded = purchasedPhotoSale($owner);
    $own = app(ManageStoryMedia::class)->add($story, $owner, UploadedFile::fake()->image('mia.jpg', 400, 300), StoryMediaRole::Photo);
    app(ManageStoryMedia::class)->importPurchased($story, $owner, [$kept->uuid, $reversed->uuid, $refunded->uuid]);

    expect(storyMediaCount($story, 'photo'))->toBe(4);

    // An admin reverses one sale (partial refund).
    app(ReversePhotoSale::class)->handle($reversed, 'Reembolso parcial');
    expect(storyMediaCount($story, 'photo'))->toBe(3);

    // The gateway reports a full refund of the order of another sale.
    OrderRefunded::dispatch($refunded->order, $refunded->order->total_minor, true);

    $left = collect(app(StoryPresenter::class)->story($story->fresh(), forOwner: true)['photos']);
    expect($left)->toHaveCount(2)
        ->and($left->pluck('uuid')->all())->toContain($own->uuid)
        ->and($left->where('source', 'purchased'))->toHaveCount(1)
        // The photographers' originals are never deleted by any of this.
        ->and(Storage::disk('event_photo_originals')->exists($refunded->photo->original_path))->toBeTrue();

    // Replaying the reversal changes nothing.
    app(ReversePhotoSale::class)->handle($reversed->fresh(), 'otra vez');
    expect(storyMediaCount($story, 'photo'))->toBe(2);
});

test('api: purchased photos are listed with the stories that hold them and imported by sale id', function () {
    Bus::fake();
    config(['legacy.stories.max_photos' => 1]);
    [$story, $owner] = ownedStory();
    $sale = purchasedPhotoSale($owner);
    $other = purchasedPhotoSale($owner);
    purchasedPhotoSale(User::factory()->create());
    $headers = apiAuthHeader($owner);

    $this->getJson('/api/v1/me/purchased-photos?page=1', $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['sale_id', 'thumb_url', 'event_name', 'purchased_at', 'already_in_story_ids']], 'meta' => ['current_page', 'last_page', 'total']])
        ->assertJsonPath('data.0.sale_id', $other->uuid)
        ->assertJsonPath('data.1.already_in_story_ids', []);

    $this->postJson("/api/v1/me/stories/{$story->public_id}/media/import", ['sale_ids' => [$sale->uuid]], $headers)
        ->assertCreated()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.role', 'photo')
        ->assertJsonPath('data.0.source', 'purchased')
        ->assertJsonPath('meta.quota.photos.remaining', 0);

    $this->getJson('/api/v1/me/purchased-photos', $headers)
        ->assertJsonPath('data.1.already_in_story_ids', [$story->public_id]);

    $this->postJson("/api/v1/me/stories/{$story->public_id}/media/import", ['sale_ids' => [$other->uuid]], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'MEDIA_LIMIT_REACHED')
        ->assertJsonPath('error.details.kind', 'photos');

    // Web picker + import use the same action.
    $this->actingAs($owner)->getJson(route('my-legacy.purchased-photos', $story->public_id))
        ->assertOk()
        ->assertJsonPath('data.1.in_story', true)
        ->assertJsonPath('data.0.in_story', false);
});
