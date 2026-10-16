<?php

use App\Actions\Media\UpdateAthleteEventMediaVisibility;
use App\Actions\Media\UploadAthleteEventMedia;
use App\Actions\Social\CreateMoment;
use App\Enums\MediaVisibility;
use App\Http\Resources\Api\V1\AthleteEventMediaResource;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use App\Services\Media\MediaUrlGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['finisher.media_disk' => 'synology_media']);
    Storage::fake('synology_media');
    Storage::fake('media_tmp');
    config(['finisher.media.video.ffmpeg' => null]);
});

/**
 * @return array{0: User, 1: AthleteEventMedia}
 */
function deliveredMedia(bool $public = false, string $kind = 'image'): array
{
    $owner = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $owner->id]);
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $upload = $kind === 'image'
        ? UploadedFile::fake()->image('p.jpg', 1000, 700)
        : UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4');

    if ($kind !== 'image') {
        file_put_contents($upload->getPathname(), str_repeat('v', 10 * 1024));
    }

    $media = app(UploadAthleteEventMedia::class)->handle($athlete, $participant, $upload, $public);

    return [$owner, $media->fresh()];
}

function mediaBody(TestResponse $response): string
{
    return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
}

function unsignedUrl(MediaFile $file, string $variant = 'default'): string
{
    return route('media.file', ['mediaFile' => $file->uuid, 'variant' => $variant]);
}

test('private media: a signed URL works for anyone holding it, without it only the owner gets in', function () {
    [$owner, $media] = deliveredMedia(public: false);
    $file = $media->mediaFile;

    $this->get($media->url('card'))->assertOk()->assertHeader('Content-Type', $file->variants['card']['mime']);

    $this->get(unsignedUrl($file, 'card'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(unsignedUrl($file, 'card'))->assertForbidden();
    $this->actingAs($owner)->get(unsignedUrl($file, 'card'))->assertOk();
});

test('signed URLs expire', function () {
    [, $media] = deliveredMedia(public: false);
    $url = $media->url();

    $this->travel(config('finisher.media.urls.private_ttl_minutes') + 20)->minutes();

    $this->get($url)->assertForbidden();
});

test('a tampered signature or a swapped variant is rejected', function () {
    [, $media] = deliveredMedia(public: false);

    $this->get(str_replace('/card?', '/display?', $media->url('card')))->assertForbidden();
    $this->get($media->url('card').'x')->assertForbidden();
});

test('public media: long-lived cacheable URLs, guests may fetch it', function () {
    [, $media] = deliveredMedia(public: true);
    $file = $media->mediaFile;

    $response = $this->get($media->url('display'))->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and(mediaBody($response))->toBe(Storage::disk('synology_media')->get($file->variants['display']['path']));

    // Same URL for the whole bucket (browser/CDN cache friendly).
    expect($media->url('display'))->toBe($media->fresh()->url('display'));

    $this->get(unsignedUrl($file, 'thumb'))->assertOk();

    $this->travel(2)->days();
    $this->get($media->url('display'))->assertOk();
});

test('making media private revokes its long-lived public URL immediately', function () {
    [, $media] = deliveredMedia(public: true);
    $publicUrl = $media->url('display');

    app(UpdateAthleteEventMediaVisibility::class)->handle($media, false);

    $this->get($publicUrl)->assertForbidden();
});

test('originals (camera EXIF) are owner-only, even with a valid signature', function () {
    [$owner, $media] = deliveredMedia(public: true);
    $file = $media->mediaFile;

    $signedOriginal = URL::temporarySignedRoute('media.file', now()->addHour(), ['mediaFile' => $file->uuid, 'variant' => 'original'], absolute: false);

    $this->get($signedOriginal)->assertForbidden();
    $this->actingAs($owner)->get($signedOriginal)->assertOk()->assertHeader('Cache-Control', 'max-age=1800, private');
});

test('unknown variants 404 and responses never reveal disk paths', function () {
    [, $media] = deliveredMedia(public: true);
    $file = $media->mediaFile;

    $this->get(app(MediaUrlGenerator::class)->url($file, 'nope'))->assertNotFound();

    $response = $this->get($media->url());
    foreach ($response->headers->all() as $values) {
        foreach ($values as $value) {
            expect((string) $value)->not->toContain('athletes/')->not->toContain($file->directory);
        }
    }
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('video delivery supports range requests', function () {
    [$owner, $media] = deliveredMedia(public: false, kind: 'video');

    $this->actingAs($owner)->get($media->url(), ['Range' => 'bytes=0-99'])
        ->assertStatus(206)
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Range', 'bytes 0-99/'.(10 * 1024));
});

test('while processing only the uploader gets a no-store preview', function () {
    Bus::fake();
    [$owner, $media] = deliveredMedia(public: true);
    $file = $media->mediaFile;
    $url = app(MediaUrlGenerator::class)->url($file, 'default');

    $this->get($url)->assertNotFound();
    $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

test('files without an owning row fall back to their own visibility', function () {
    $file = app(MediaStorage::class)->store(
        UploadedFile::fake()->image('a.jpg', 300, 300),
        'products/'.Str::uuid().'/images',
        MediaUploadRules::images(visibility: MediaVisibility::Public),
    );

    $this->get(unsignedUrl($file, 'thumb'))->assertOk();

    $file->update(['visibility' => MediaVisibility::Private]);
    $this->get(unsignedUrl($file, 'thumb'))->assertForbidden();
});

test('public URLs can be pointed at a CDN, private ones never are', function () {
    config(['finisher.media_cdn_url' => 'https://cdn.example.test']);
    [, $public] = deliveredMedia(public: true);
    [, $private] = deliveredMedia(public: false);

    expect($public->url())->toStartWith('https://cdn.example.test/media/f/')
        ->and($private->url())->not->toStartWith('https://cdn.example.test');

    // The CDN forwards path + query; the relative signature still validates.
    $this->get(Str::after($public->url(), 'https://cdn.example.test'))->assertOk();
});

test('moment photos go through the pipeline and follow the moment visibility', function () {
    $author = User::factory()->create();
    $moment = app(CreateMoment::class)->handle(
        $author,
        ['type' => 'memory', 'visibility' => 'private'],
        [UploadedFile::fake()->image('m.jpg', 600, 400)],
    );

    $item = $moment->media()->firstOrFail();
    $file = $item->mediaFile;

    expect($file->directory)->toBe('users/'.$author->id.'/posts')
        ->and($file->isServable())->toBeTrue()
        ->and($item->url())->toContain('/media/f/');

    $this->get(unsignedUrl($file, 'card'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(unsignedUrl($file, 'card'))->assertForbidden();
    $this->actingAs($author)->get(unsignedUrl($file, 'card'))->assertOk();
});

test('the API resource exposes status and variant URLs additively', function () {
    [$owner, $media] = deliveredMedia(public: false);

    $data = (new AthleteEventMediaResource($media))->toArray(request());

    expect($data)->toHaveKeys(['uuid', 'url', 'status', 'thumb_url', 'card_url', 'poster_url'])
        ->and($data['status'])->toBe('ready')
        ->and($data['thumb_url'])->toContain('/media/f/');
});
