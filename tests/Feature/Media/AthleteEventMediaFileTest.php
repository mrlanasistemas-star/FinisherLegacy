<?php

use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * No event photo/video is ever served from a direct disk URL (product
 * consolidation brief §62) — public media is served unconditionally by
 * App\Http\Controllers\AthleteEventMediaFileController, private media
 * only behind a valid Laravel signed-URL signature.
 */
function makeEventMedia(bool $isPublic): AthleteEventMedia
{
    Storage::fake('athlete_media');
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    Storage::disk('athlete_media')->put('event-media/test.jpg', 'fake-image-bytes');

    return AthleteEventMedia::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_participant_id' => $participant->id,
        'type' => 'image',
        'disk' => 'athlete_media',
        'path' => 'event-media/test.jpg',
        'mime' => 'image/jpeg',
        'size_bytes' => 17,
        'checksum' => str_repeat('a', 64),
        'is_public' => $isPublic,
        'sort_order' => 0,
    ]);
}

test('public media is served unconditionally, with no signature needed', function () {
    $media = makeEventMedia(isPublic: true);

    $this->get($media->url())->assertOk()->assertSee('fake-image-bytes');
});

test('private media requires a valid signature', function () {
    $media = makeEventMedia(isPublic: false);

    $this->get(route('athlete-media.show', ['media' => $media->uuid]))->assertForbidden();
});

test('private media is served when the signature is valid', function () {
    $media = makeEventMedia(isPublic: false);

    $this->get($media->url())->assertOk()->assertSee('fake-image-bytes');
});
