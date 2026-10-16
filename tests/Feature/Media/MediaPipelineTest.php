<?php

use App\Actions\Media\UploadAthleteEventMedia;
use App\Enums\MediaFileStatus;
use App\Jobs\Media\FinalizeMediaFile;
use App\Jobs\Media\ProcessMediaFile;
use App\Jobs\Media\TransferMediaFile;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\MediaFile;
use App\Services\Media\ImageVariantGenerator;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['finisher.media_disk' => 'synology_media']);
    Storage::fake('synology_media');
    Storage::fake('media_tmp');
    config(['finisher.media.video.ffmpeg' => null, 'finisher.media.video.ffprobe' => null]);
});

function pipelineParticipant(): array
{
    $athlete = Athlete::factory()->create(['full_name' => 'Mariana Pérez']);
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    return [$athlete, $participant];
}

test('the request only parks the upload and queues the chain: process → transfer → finalize', function () {
    Bus::fake();
    [$athlete, $participant] = pipelineParticipant();

    $media = app(UploadAthleteEventMedia::class)->handle($athlete, $participant, UploadedFile::fake()->image('race.jpg', 1200, 800));
    $file = $media->mediaFile;

    expect($media->processingStatus())->toBe('processing')
        ->and($file->status)->toBe(MediaFileStatus::Processing)
        ->and($file->disk)->toBe('synology_media')
        ->and($file->directory)->toStartWith('athletes/'.$athlete->uuid.'--mariana-perez/events/')
        ->and($file->directory)->toEndWith('/photos')
        ->and($file->mediable->is($media))->toBeTrue();

    Storage::disk('media_tmp')->assertExists($file->source_path);
    expect(Storage::disk('synology_media')->allFiles())->toBeEmpty();

    Bus::assertChained([ProcessMediaFile::class, TransferMediaFile::class, FinalizeMediaFile::class]);
});

test('the full pipeline renders WebP variants, moves them to the media disk and cleans the temp folder', function () {
    [$athlete, $participant] = pipelineParticipant();

    $media = app(UploadAthleteEventMedia::class)->handle($athlete, $participant, UploadedFile::fake()->image('finish.jpg', 2400, 1600));
    $file = $media->mediaFile->fresh();

    expect($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->width)->toBe(2400)
        ->and($file->processed_at)->not->toBeNull()
        ->and(array_keys($file->variants))->toContain('thumb', 'card', 'display')
        ->and($file->variants['thumb']['width'])->toBe(320)
        ->and($file->variants['card']['width'])->toBe(800)
        ->and($file->variants['display']['width'])->toBe(1600)
        ->and($file->variants['display']['mime'])->toBe(app(ImageVariantGenerator::class)->supportsWebp() ? 'image/webp' : 'image/jpeg')
        ->and($file->path)->toBe($file->variants['display']['path']);

    foreach ($file->variants as $variant) {
        Storage::disk('synology_media')->assertExists($variant['path']);
        expect($variant['path'])->toStartWith($file->directory.'/');
    }
    Storage::disk('synology_media')->assertExists($file->original_path);
    expect(Storage::disk('media_tmp')->allFiles())->toBeEmpty();

    // The owning row is synced; DB only stores relative paths.
    $media->refresh();
    expect($media->path)->toBe($file->path)
        ->and($media->disk)->toBe('synology_media')
        ->and($media->processingStatus())->toBe('ready')
        ->and($file->path)->not->toContain(':')
        ->and($file->path)->not->toStartWith('/');
});

test('variants are never upscaled and originals can be dropped by config', function () {
    config(['finisher.media.images.keep_original' => false]);

    $file = app(MediaStorage::class)->store(
        UploadedFile::fake()->image('small.png', 500, 300),
        'products/'.Str::uuid().'/images',
        MediaUploadRules::images(),
    )->fresh();

    expect($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->variants['thumb']['width'])->toBe(320)
        ->and($file->variants['card']['width'])->toBe(500)
        ->and($file->variants['display']['width'])->toBe(500)
        ->and($file->original_path)->toBeNull();
});

test('AVIF variants only appear when enabled and supported', function () {
    config(['finisher.media.images.avif' => true]);
    $supported = app(ImageVariantGenerator::class)->supportsAvif();

    $file = app(MediaStorage::class)->store(
        UploadedFile::fake()->image('a.jpg', 400, 400),
        'products/'.Str::uuid().'/images',
        MediaUploadRules::images(),
    )->fresh();

    expect(isset($file->variants['thumb_avif']))->toBe($supported);
});

test('a video without ffmpeg keeps the original, is servable, and is marked pending_transcode', function () {
    Log::spy();
    [$athlete, $participant] = pipelineParticipant();

    $media = app(UploadAthleteEventMedia::class)->handle($athlete, $participant, UploadedFile::fake()->create('clip.mov', 3000, 'video/quicktime'));
    $file = $media->mediaFile->fresh();

    expect($file->status)->toBe(MediaFileStatus::PendingTranscode)
        ->and($file->isServable())->toBeTrue()
        ->and($file->path)->toBe($file->original_path)
        ->and($file->path)->toEndWith('_original.mov')
        ->and($file->directory)->toEndWith('/videos');
    Storage::disk('synology_media')->assertExists($file->path);

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'media.ffmpeg_missing')->once();
});

test('with ffmpeg available a video becomes an H.264 MP4 with poster and probed duration', function () {
    config(['finisher.media.video.ffmpeg' => '/opt/ffmpeg/bin/ffmpeg']);

    Process::fake(function (PendingProcess $process) {
        $command = (array) $process->command;

        if (str_ends_with($command[0], 'ffprobe')) {
            return Process::result(json_encode([
                'format' => ['duration' => '12.48'],
                'streams' => [['codec_type' => 'video', 'width' => 1920, 'height' => 1080]],
            ]));
        }

        $destination = end($command);
        if (str_ends_with($destination, '.mp4')) {
            file_put_contents($destination, 'mp4-bytes');
        } elseif (str_ends_with($destination, '.jpg')) {
            $image = imagecreatetruecolor(1920, 1080);
            imagejpeg($image, $destination);
        }

        return Process::result('ok');
    });

    [$athlete, $participant] = pipelineParticipant();
    $media = app(UploadAthleteEventMedia::class)->handle($athlete, $participant, UploadedFile::fake()->create('clip.mp4', 3000, 'video/mp4'));
    $file = $media->mediaFile->fresh();

    expect($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->duration_ms)->toBe(12480)
        ->and($file->variants['video']['mime'])->toBe('video/mp4')
        ->and($file->path)->toBe($file->variants['video']['path'])
        ->and($file->poster_path)->not->toBeNull()
        ->and($file->variants)->toHaveKey('poster_card')
        ->and($file->original_path)->toBeNull()
        ->and($media->fresh()->duration_seconds)->toBe(12);

    Storage::disk('synology_media')->assertExists($file->path);
    Process::assertRan(fn (PendingProcess $process) => in_array('libx264', (array) $process->command, true)
        && in_array('yuv420p', (array) $process->command, true)
        && in_array('+faststart', (array) $process->command, true));
});

test('a failing step marks the file failed with context, and media:retry-failed recovers it', function () {
    Log::spy();
    $storage = app(MediaStorage::class);
    $file = $storage->accept(UploadedFile::fake()->image('x.jpg', 600, 400), 'products/'.Str::uuid().'/images', MediaUploadRules::images());

    // Simulate a broken processing step (e.g. corrupted temp file).
    $good = Storage::disk('media_tmp')->get($file->source_path);
    Storage::disk('media_tmp')->put($file->source_path, 'not an image');

    expect(fn () => $storage->process($file))->toThrow(Exception::class);

    $file->refresh();
    expect($file->status)->toBe(MediaFileStatus::Failed)
        ->and($file->error)->not->toBeEmpty()
        ->and($file->failed_at)->not->toBeNull()
        ->and($file->url())->toBeNull();
    Storage::disk('media_tmp')->assertExists($file->source_path); // kept for retry
    Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'media.pipeline_failed'
        && $context['media_uuid'] === $file->uuid && $context['step'] === 'ProcessMediaFile')->once();

    Storage::disk('media_tmp')->put($file->source_path, $good);
    $this->artisan('media:retry-failed')->expectsOutputToContain('Re-queued 1')->assertSuccessful();

    expect($file->fresh()->status)->toBe(MediaFileStatus::Ready);
});

test('media:retry-failed skips files whose source is gone', function () {
    $file = app(MediaStorage::class)->accept(UploadedFile::fake()->image('x.jpg', 60, 40), 'products/'.Str::uuid().'/images', MediaUploadRules::images());
    $file->forceFill(['status' => MediaFileStatus::Failed])->save();
    Storage::disk('media_tmp')->delete($file->source_path);

    $this->artisan('media:retry-failed')->expectsOutputToContain('skipped 1')->assertSuccessful();
    expect($file->fresh()->status)->toBe(MediaFileStatus::Failed);
});

test('uploads are validated by sniffed content, size and pixel dimensions', function () {
    $storage = app(MediaStorage::class);
    $directory = 'products/'.Str::uuid().'/images';

    $spoofed = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "hi";');
    expect(fn () => $storage->accept($spoofed, $directory, MediaUploadRules::images()))->toThrow(ValidationException::class);

    config(['finisher.media.max_image_pixels' => 1000]);
    expect(fn () => $storage->accept(UploadedFile::fake()->image('big.png', 100, 100), $directory, MediaUploadRules::images()))
        ->toThrow(ValidationException::class);

    expect(MediaFile::count())->toBe(0)
        ->and(Storage::disk('media_tmp')->allFiles())->toBeEmpty();
});
