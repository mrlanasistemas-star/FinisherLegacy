<?php

use App\Actions\Media\DeleteAthleteEventMedia;
use App\Actions\Media\UploadAthleteEventMedia;
use App\Exceptions\MediaLimitReachedException;
use App\Exceptions\MediaTooLargeException;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('athlete_media');
    Storage::fake(config('finisher.media_disk'));
    Storage::fake('media_tmp');
    config(['finisher.media.video.ffmpeg' => null, 'finisher.media.video.ffprobe' => null]);
});

function uploadPhoto(Athlete $athlete, EventParticipant $participant, string $name = 'photo.jpg')
{
    return app(UploadAthleteEventMedia::class)->handle(
        $athlete,
        $participant,
        UploadedFile::fake()->image($name, 800, 600)->size(500),
    );
}

test('the first 5 photos for a participation are allowed, the 6th is blocked', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create();

    for ($i = 1; $i <= 5; $i++) {
        $media = uploadPhoto($athlete, $participant, "photo{$i}.jpg");
        expect($media->athlete_id)->toBe($athlete->id)
            ->and($media->event_participant_id)->toBe($participant->id);
    }

    uploadPhoto($athlete, $participant, 'photo6.jpg');
})->throws(MediaLimitReachedException::class);

test('1 video is allowed, a 2nd is blocked', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create();

    app(UploadAthleteEventMedia::class)->handle(
        $athlete, $participant, UploadedFile::fake()->create('clip1.mp4', 2000, 'video/mp4'),
    );

    app(UploadAthleteEventMedia::class)->handle(
        $athlete, $participant, UploadedFile::fake()->create('clip2.mp4', 2000, 'video/mp4'),
    );
})->throws(MediaLimitReachedException::class);

test('an oversized image is rejected before it counts against the limit', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create();

    app(UploadAthleteEventMedia::class)->handle(
        $athlete, $participant, UploadedFile::fake()->image('huge.jpg')->size(9000), // > 8MB default
    );
})->throws(MediaTooLargeException::class);

test('a real checksum and mime are recorded, and deleting removes every stored rendition', function () {
    $athlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create();

    $media = uploadPhoto($athlete, $participant);
    $file = $media->mediaFile;

    expect($media->checksum)->toHaveLength(64)
        ->and($media->mime)->toStartWith('image/')
        ->and($media->disk)->toBe(config('finisher.media_disk'));

    Storage::disk($media->disk)->assertExists($media->path);
    $paths = collect($file->variants)->pluck('path')->push($file->original_path)->filter();
    expect($paths)->not->toBeEmpty();

    app(DeleteAthleteEventMedia::class)->handle($media);

    foreach ($paths as $path) {
        Storage::disk($file->disk)->assertMissing($path);
    }
    expect(AthleteEventMedia::find($media->id))->toBeNull()
        ->and(MediaFile::find($file->id))->toBeNull();
});
