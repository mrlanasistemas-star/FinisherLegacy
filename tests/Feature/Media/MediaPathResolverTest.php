<?php

use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\User;
use App\Services\Media\MediaPathResolver;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function resolver(): MediaPathResolver
{
    return new MediaPathResolver;
}

test('athlete folders are {uuid}--{readable slug} with the documented sections', function () {
    $athlete = Athlete::factory()->create(['full_name' => 'José Ángel Núñez']);
    $root = 'athletes/'.$athlete->uuid.'--jose-angel-nunez';

    expect(resolver()->athleteRoot($athlete))->toBe($root)
        ->and(resolver()->athleteSection($athlete, 'profile'))->toBe($root.'/profile')
        ->and(resolver()->athleteSection($athlete, 'posts'))->toBe($root.'/posts');
});

test('names are sanitized: ascii, no separators or dots, max 40 chars, never empty', function (string $name, string $expected) {
    expect(resolver()->slug($name))->toBe($expected);
})->with([
    'path traversal' => ['../../etc/passwd', 'etcpasswd'],
    'symbols' => ['Ana   O\'Connor / Pérez!!', 'ana-oconnor-perez'],
    'empty' => ['   ', 'atleta'],
    'only symbols' => ['¿¡?!', 'atleta'],
    'long' => [str_repeat('Maratonista ', 10), 'maratonista-maratonista-maratonista-mara'],
]);

test('event media lives under events/{event_uuid}/{kind}', function () {
    $athlete = Athlete::factory()->create();
    $edition = EventEdition::factory()->create();

    $path = resolver()->athleteEvent($athlete, $edition, 'videos');
    $edition->refresh();

    expect(Str::isUuid($edition->uuid))->toBeTrue()
        ->and($path)->toBe(resolver()->athleteRoot($athlete).'/events/'.$edition->uuid.'/videos');
});

test('an event without uuid gets one assigned once, and keeps it', function () {
    $edition = EventEdition::factory()->create();
    EventEdition::query()->whereKey($edition->id)->update(['uuid' => null]);
    $edition->refresh();

    $first = resolver()->eventUuid($edition);
    $second = resolver()->eventUuid(EventEdition::query()->findOrFail($edition->id));

    expect(Str::isUuid($first))->toBeTrue()->and($second)->toBe($first);
});

test('products use products/{uuid}/(images|videos|models3d)', function () {
    $product = Product::factory()->create();

    expect(resolver()->product($product, 'models3d'))->toBe('products/'.$product->uuid.'/models3d');
});

test('the uuid is the identity: renaming an athlete never moves their folder', function () {
    $athlete = Athlete::factory()->create(['full_name' => 'Laura Gómez']);
    $original = resolver()->athleteRoot($athlete);

    MediaFile::create([
        'uuid' => (string) Str::uuid(),
        'kind' => MediaKind::Image,
        'status' => MediaFileStatus::Ready,
        'disk' => 'local',
        'directory' => $original.'/profile',
        'mime' => 'image/jpeg',
        'extension' => 'jpg',
        'size_bytes' => 10,
        'checksum' => str_repeat('a', 64),
    ]);

    $athlete->update(['full_name' => 'Laura Gómez de la Fuente']);

    expect(resolver()->athleteRoot($athlete->fresh()))->toBe($original);
});

test('a renamed athlete without stored media just gets the new readable slug', function () {
    $athlete = Athlete::factory()->create(['full_name' => 'Old Name']);
    $athlete->update(['full_name' => 'New Name']);

    expect(resolver()->athleteRoot($athlete))->toEndWith('--new-name');
});

test('users without an athlete get an internal users/{id} folder', function () {
    $user = User::factory()->create();

    expect(resolver()->forUser($user, 'posts'))->toBe('users/'.$user->id.'/posts');
});

test('unknown sections and invalid uuids are refused', function () {
    $athlete = Athlete::factory()->create();

    expect(fn () => resolver()->athleteSection($athlete, '../secret'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => resolver()->product('not-a-uuid', 'images'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => resolver()->athleteEvent($athlete, '1', 'photos'))->toThrow(InvalidArgumentException::class);
});

test('MediaStorage refuses directories that did not come from the resolver', function (string $directory) {
    Storage::fake('media_tmp');

    app(MediaStorage::class)->accept(UploadedFile::fake()->image('a.jpg', 50, 50), $directory, MediaUploadRules::images());
})->with(['../escape', '/volume1/absolute', 'Athletes/UPPER', 'a//b', ''])->throws(InvalidArgumentException::class);
