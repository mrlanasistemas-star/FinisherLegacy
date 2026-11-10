<?php

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Enums\StoryMediaRole;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\MediaFile;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoQuotaRule;
use App\Services\Media\MediaStorage;
use App\Services\Media\StoryPhotoQuota;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;

require_once __DIR__.'/quota-helpers.php';

beforeEach(function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
});

function storyPhotoCount(LegacyStory $story): int
{
    return DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->where('role', StoryMediaRole::Photo->value)->count();
}

test('base limit resolves product rule → product type rule → config fallback', function () {
    config(['legacy.stories.max_photos' => 7]);
    [$story, , $tag] = ownedStory();
    $quota = app(StoryPhotoQuota::class);
    $product = $tag->productVariant->product;

    expect($quota->limitFor($story))->toBe(7);

    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::typeKey($product->type), 'product_type' => $product->type, 'included_photos' => 4]);
    expect($quota->limitFor($story->fresh()))->toBe(4);

    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::productKey($product->id), 'product_id' => $product->id, 'included_photos' => 5]);
    expect($quota->limitFor($story->fresh()))->toBe(5);
});

test('only active photo-expansion entitlements add capacity', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner] = ownedStory();

    MediaEntitlement::create(['user_id' => $owner->id, 'legacy_story_id' => $story->id, 'extra_images' => 10, 'source' => PhotoExpansionPack::ENTITLEMENT_SOURCE]);
    MediaEntitlement::create(['user_id' => $owner->id, 'legacy_story_id' => $story->id, 'extra_images' => 3, 'source' => PhotoExpansionPack::ENTITLEMENT_SOURCE, 'revoked_at' => now()]);

    expect(app(StoryPhotoQuota::class)->limitFor($story))->toBe(15);
});

test('summary offers the active expansion with its current price', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story] = ownedStory();
    $quota = app(StoryPhotoQuota::class);

    expect($quota->summary($story))->toMatchArray(['limit' => 5, 'used' => 0, 'remaining' => 5, 'can_expand' => false, 'expansion' => null]);

    $pack = expansionPack(photos: 10, amountMinor: 5000, currency: 'USD');

    expect($quota->summary($story))->toMatchArray([
        'can_expand' => true,
        'expansion' => ['id' => $pack->id, 'name' => 'Pack de prueba', 'photos' => 10, 'price' => '50.00', 'currency' => 'USD'],
    ]);

    // Archived / paused / unpriced packs are never offered.
    $pack->update(['archived_at' => now()]);
    expect($quota->summary($story)['expansion'])->toBeNull();
});

test('reaching the limit refuses a new photo with a file error and keeps every photo', function () {
    config(['legacy.stories.max_photos' => 2]);
    [$story, $owner] = ownedStory();

    foreach (['a', 'b'] as $name) {
        $this->actingAs($owner)->post(route('my-legacy.media.store', $story->public_id), ['role' => 'photo', 'file' => UploadedFile::fake()->image("{$name}.jpg", 400, 300)])
            ->assertSessionHasNoErrors();
    }

    $files = MediaFile::query()->count();

    $this->post(route('my-legacy.media.store', $story->public_id), ['role' => 'photo', 'file' => UploadedFile::fake()->image('c.jpg', 400, 300)])
        ->assertSessionHasErrors('file');

    expect(storyPhotoCount($story))->toBe(2)
        ->and(MediaFile::query()->count())->toBe($files);
});

test('lowering a quota never deletes existing photos', function () {
    config(['legacy.stories.max_photos' => 5]);
    [$story, $owner, $tag] = ownedStory();

    foreach (['a', 'b', 'c'] as $name) {
        $this->actingAs($owner)->post(route('my-legacy.media.store', $story->public_id), ['role' => 'photo', 'file' => UploadedFile::fake()->image("{$name}.jpg", 400, 300)]);
    }
    expect(storyPhotoCount($story))->toBe(3);

    $product = $tag->productVariant->product;
    PhotoQuotaRule::create(['scope_key' => PhotoQuotaRule::productKey($product->id), 'product_id' => $product->id, 'included_photos' => 1]);

    $summary = app(StoryPhotoQuota::class)->summary($story->fresh());

    expect(storyPhotoCount($story))->toBe(3)
        ->and($summary['limit'])->toBe(1)
        ->and($summary['used'])->toBe(3)
        ->and($summary['remaining'])->toBe(0);

    // Owner still sees all three in the editor.
    $this->get(route('my-legacy.edit', $story->public_id))->assertOk();
    expect(MediaFile::query()->count())->toBe(3);
});

test('race: a photo committed by a concurrent request right before the lock is never exceeded', function () {
    config(['legacy.stories.max_photos' => 1]);
    [$story, $owner] = ownedStory();

    // Simulates request B committing the last free slot between request
    // A's cheap pre-check and A acquiring the story row lock.
    $injected = false;
    DB::beforeExecuting(function (string $sql) use (&$injected, $story, $owner) {
        if ($injected || ! str_contains($sql, 'from "legacy_stories"')) {
            return;
        }
        $injected = true;

        $competitor = MediaFile::create([
            'uuid' => (string) Str::uuid(), 'owner_user_id' => $owner->id, 'kind' => 'image', 'status' => 'ready',
            'visibility' => 'private', 'disk' => 'local', 'directory' => 'users/'.$owner->id.'/legacy', 'mime' => 'image/jpeg',
            'extension' => 'jpg', 'size_bytes' => 10, 'checksum' => hash('sha256', 'competitor'),
        ]);
        DB::table('legacy_story_media')->insert([
            'legacy_story_id' => $story->id, 'media_file_id' => $competitor->id, 'role' => 'photo', 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    expect(fn () => app(ManageStoryMedia::class)->add($story, $owner, UploadedFile::fake()->image('late.jpg', 400, 300), StoryMediaRole::Photo))
        ->toThrow(PhotoQuotaExceededException::class);

    // The loser is refused under the lock and leaves no row and no temp file.
    // (In-memory SQLite has a single connection, so the simulated competitor
    // insert runs inside the loser's transaction and rolls back with it; on
    // MariaDB it would be a separate committed request. Either way the story
    // never exceeds its limit.)
    expect($injected)->toBeTrue()
        ->and(storyPhotoCount($story))->toBeLessThanOrEqual(1)
        ->and(MediaFile::query()->where('original_name', 'late.jpg')->count())->toBe(0)
        ->and(Storage::disk(app(MediaStorage::class)->tmpDisk())->allFiles('incoming'))->toBe([]);
});

test('two sequential adds at the last slot: exactly one wins', function () {
    config(['legacy.stories.max_photos' => 1]);
    [$story, $owner] = ownedStory();
    $media = app(ManageStoryMedia::class);

    $media->add($story, $owner, UploadedFile::fake()->image('one.jpg', 400, 300), StoryMediaRole::Photo);

    expect(fn () => $media->add($story, $owner, UploadedFile::fake()->image('two.jpg', 400, 300), StoryMediaRole::Photo))
        ->toThrow(PhotoQuotaExceededException::class);

    expect(storyPhotoCount($story))->toBe(1);
});

test('a temp-disk write failure becomes a friendly file error and leaves nothing behind', function () {
    [$story, $owner] = ownedStory();

    Storage::shouldReceive('disk')->andThrow(new UnableToWriteFile('disk full'));

    expect(fn () => app(ManageStoryMedia::class)->add($story, $owner, UploadedFile::fake()->image('x.jpg', 400, 300), StoryMediaRole::Photo))
        ->toThrow(ValidationException::class);

    expect(MediaFile::query()->count())->toBe(0);
});
