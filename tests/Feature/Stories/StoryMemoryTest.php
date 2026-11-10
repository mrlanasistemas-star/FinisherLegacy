<?php

use App\Enums\StoryVisibility;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../LegacyTags/story-helpers.php';

/*
| "Mis recuerdos": sporting result, photo quota in the editor, download of
| everything, the portrait builder and its privacy (owner only).
*/

beforeEach(function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
});

function storyJpeg(int $w, int $h): UploadedFile
{
    $img = imagecreatetruecolor($w, $h);
    imagefilledrectangle($img, 0, 0, $w, $h, (int) imagecolorallocate($img, 30, 90, 160));
    ob_start();
    imagejpeg($img, null, 80);

    return sniffedUpload('foto.jpg', (string) ob_get_clean());
}

test('a user with several products sees every memory in Mis recuerdos', function () {
    $owner = User::factory()->create();
    ownedStory(StoryVisibility::Public, $owner);
    ownedStory(StoryVisibility::Private, $owner);
    ownedStory(StoryVisibility::Unlisted, $owner);
    ownedStory(); // somebody else's

    $this->actingAs($owner)->get(route('my-legacy.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('my-legacy/Index')->where('total', 3));
});

test('the sporting result keeps time, distance and position', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->patch(route('my-legacy.update', $story->public_id), [
        'visibility' => 'unlisted',
        'race_mode' => 'manual',
        'event_name' => 'Maratón CDMX',
        'distance' => '42 km',
        'result_time' => '3:58:12',
        'result_position' => '812 de 23,000',
        'race_date' => '2026-08-30',
        'location' => 'Ciudad de México',
    ])->assertSessionHasNoErrors();

    expect($story->fresh()->result_position)->toBe('812 de 23,000');

    $this->get(route('legacy.story.show', $story->public_id))
        ->assertInertia(fn ($page) => $page
            ->where('story.race.position', '812 de 23,000')
            ->where('story.race.time', '3:58:12')
            ->where('story.race.distance', '42 km'));
});

test('the editor receives the photo quota summary', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->get(route('my-legacy.edit', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('my-legacy/Edit')
            ->has('photoQuota', fn ($quota) => $quota
                ->where('used', 0)
                ->has('limit')
                ->has('remaining')
                ->has('can_expand')
                ->has('expansion')
                ->etc())
            ->has('supportUrl'));
});

test('private and link-only stories are noindex, public ones are not', function () {
    [$public] = ownedStory(StoryVisibility::Public);
    [$unlisted] = ownedStory(StoryVisibility::Unlisted);

    $this->get(route('legacy.story.show', $public->public_id))->assertHeaderMissing('X-Robots-Tag');
    $this->get(route('legacy.story.show', $unlisted->public_id))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->where('noindex', true));
});

test('a private story shows strangers no media at all', function () {
    [$story, $owner] = ownedStory(StoryVisibility::Private);
    $this->actingAs($owner)->post(route('my-legacy.media.store', $story->public_id), ['role' => 'photo', 'file' => storyJpeg(800, 600)])
        ->assertSessionHasNoErrors();
    $file = MediaFile::query()->sole();

    auth()->logout();
    $response = $this->get(route('legacy.story.show', $story->public_id))->assertOk();

    expect((string) $response->getContent())->not->toContain($file->uuid)
        ->and((string) $response->getContent())->not->toContain('Mi medio maratón');
});

test('the owner downloads the whole memory as a zip, strangers cannot', function () {
    [$story, $owner] = ownedStory();
    $story->forceFill(['dedication' => 'Para mamá', 'body' => 'Kilómetro 30.'])->save();

    $response = $this->actingAs($owner)->get(route('my-legacy.archive', $story->public_id));
    $response->assertOk()->assertHeader('Content-Type', 'application/zip');

    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $text = (string) $zip->getFromName('recuerdo.txt');
    $zip->close();

    expect($text)->toContain('Para mamá')->toContain('Kilómetro 30.');

    $this->actingAs(User::factory()->create())->get(route('my-legacy.archive', $story->public_id))->assertForbidden();
});

test('the portrait builder is owner only and lists the print settings', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->get(route('my-legacy.portrait', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('my-legacy/Portrait')
            ->where('spec.width_px', 2362)
            ->where('spec.height_px', 2953)
            ->has('templates', 2)
            ->where('defaultTemplate', 'carbono'));

    $this->actingAs(User::factory()->create())->get(route('my-legacy.portrait', $story->public_id))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'carbono',
        'photo' => storyJpeg(1600, 1600),
    ])->assertForbidden();
});

test('the owner downloads a print-size portrait from an uploaded photo', function () {
    [$story, $owner] = ownedStory();

    $response = $this->actingAs($owner)->post(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'hueso',
        'photo' => storyJpeg(2400, 2400),
        'crop' => ['x' => 0.05, 'y' => 0.05, 'w' => 0.9, 'h' => 0.9],
        'name' => 'Zuriel Ávila',
        'event' => 'IRONMAN 70.3 Cozumel',
        'details' => 'Cozumel · 20 de septiembre de 2026',
        'time' => '06:57:34',
        'result' => '70.3 mi',
        'dedication' => 'Para mamá',
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $size = getimagesizefromstring((string) $response->getContent());

    expect($size[0])->toBe(2362)->and($size[1])->toBe(2953);
    // The uploaded photo is never stored.
    expect(MediaFile::query()->count())->toBe(0);
});

test('a photo too small for the frame gets a friendly validation error', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->postJson(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'carbono',
        'photo' => storyJpeg(500, 500),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');

    $this->actingAs($owner)->postJson(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'carbono',
        'photo' => sniffedUpload('x.jpg', 'not an image'),
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');

    $this->actingAs($owner)->postJson(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'no-existe',
        'photo' => storyJpeg(2000, 2000),
    ])->assertUnprocessable()->assertJsonValidationErrors('template');
});
