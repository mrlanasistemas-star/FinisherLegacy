<?php

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../LegacyTags/story-helpers.php';

/*
| The Portarretrato builder endpoints (docs/renovacion/portarretrato.md):
| page props, the server-rendered preview and the print downloads
| (frame size, print shop with bleed / crop marks, PDF). Owner only.
*/

beforeEach(function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
});

function portraitUpload(int $w, int $h): UploadedFile
{
    $img = imagecreatetruecolor($w, $h);
    imagefilledrectangle($img, 0, 0, $w, $h, (int) imagecolorallocate($img, 30, 90, 160));
    ob_start();
    imagejpeg($img, null, 80);

    return sniffedUpload('foto.jpg', (string) ob_get_clean());
}

test('the builder page lists the four templates, the print spec and the race defaults', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->get(route('my-legacy.portrait', $story->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('my-legacy/Portrait')
            ->where('spec.width_px', 2362)
            ->where('spec.height_px', 2953)
            ->has('templates', 4)
            ->where('templates.0.value', 'carbono')
            ->where('templates.2.value', 'minimal')
            ->where('defaultTemplate', 'carbono')
            ->where('bleedMm', 3)
            ->has('defaults.name')
            ->has('defaults.distance')
            ->has('defaults.bib')
            ->has('defaults.pace')
            ->has('defaults.category'));
});

test('the preview is rendered by the server at screen size, even from a small photo', function () {
    [$story, $owner] = ownedStory();

    $response = $this->actingAs($owner)->post(route('my-legacy.portrait.preview', $story->public_id), [
        'template' => 'editorial',
        'photo' => portraitUpload(500, 500),
        'width' => 480,
        'name' => 'Zuriel Ávila',
        'time' => '06:57:34',
        'distance' => '70.3 mi',
        'bib' => '1284',
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $size = getimagesizefromstring((string) $response->getContent());

    expect($size[0])->toBeGreaterThanOrEqual(476)->toBeLessThanOrEqual(484)
        ->and(MediaFile::query()->count())->toBe(0);
});

test('the preview is owner only and validates its input', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs(User::factory()->create())->post(route('my-legacy.portrait.preview', $story->public_id), [
        'template' => 'carbono',
        'photo' => portraitUpload(500, 500),
    ])->assertForbidden();

    $this->actingAs($owner)->postJson(route('my-legacy.portrait.preview', $story->public_id), [
        'template' => 'carbono',
        'photo' => portraitUpload(500, 500),
        'width' => 5000,
    ])->assertUnprocessable()->assertJsonValidationErrors('width');

    $this->actingAs($owner)->postJson(route('my-legacy.portrait.preview', $story->public_id), [
        'template' => 'carbono',
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');
});

test('the frame download is the exact print size with the race data', function (string $template) {
    [$story, $owner] = ownedStory();

    $response = $this->actingAs($owner)->post(route('my-legacy.portrait.download', $story->public_id), [
        'template' => $template,
        'photo' => portraitUpload(2600, 3200),
        'name' => 'Zuriel Ávila',
        'event' => 'IRONMAN 70.3 Cozumel',
        'details' => 'Cozumel · 20 de septiembre de 2026',
        'time' => '06:57:34',
        'distance' => '70.3 mi',
        'position' => '112',
        'pace' => '5:52 min/km',
        'bib' => '1284',
        'category' => 'Varonil 35-39',
        'dedication' => 'Para mamá',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Portrait-Size', '2362x2953')
        ->assertHeader('X-Portrait-Bleed-Px', '0');

    expect((string) $response->headers->get('Content-Disposition'))->toContain("-{$template}.jpg");
})->with(['carbono', 'hueso', 'minimal', 'editorial']);

test('the print-shop download carries bleed and optional crop marks', function () {
    [$story, $owner] = ownedStory();

    $bleed = $this->actingAs($owner)->post(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'minimal',
        'photo' => portraitUpload(2600, 3200),
        'output' => 'print_shop',
    ]);

    $bleed->assertOk()
        ->assertHeader('X-Portrait-Size', '2432x3023')
        ->assertHeader('X-Portrait-Trim-Size', '2362x2953')
        ->assertHeader('X-Portrait-Bleed-Px', '35');

    expect((string) $bleed->headers->get('Content-Disposition'))->toContain('-imprenta.jpg');

    $this->actingAs($owner)->post(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'minimal',
        'photo' => portraitUpload(2600, 3200),
        'output' => 'print_shop',
        'crop_marks' => '1',
    ])->assertOk()->assertHeader('X-Portrait-Size', '2574x3165');
});

test('the download can be a pdf', function () {
    [$story, $owner] = ownedStory();

    $response = $this->actingAs($owner)->post(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'hueso',
        'photo' => portraitUpload(2600, 3200),
        'format' => 'pdf',
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect((string) $response->getContent())->toStartWith('%PDF-')
        ->and((string) $response->headers->get('Content-Disposition'))->toContain('.pdf');
});

test('unknown export options are rejected', function () {
    [$story, $owner] = ownedStory();

    $this->actingAs($owner)->postJson(route('my-legacy.portrait.download', $story->public_id), [
        'template' => 'carbono',
        'photo' => portraitUpload(2600, 3200),
        'output' => 'billboard',
        'format' => 'tiff',
    ])->assertUnprocessable()->assertJsonValidationErrors(['output', 'format']);
});
