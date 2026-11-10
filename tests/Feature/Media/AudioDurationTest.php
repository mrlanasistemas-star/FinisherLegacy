<?php

/**
 * Real audio duration in the media pipeline: ffprobe measures the voice
 * note in ProcessMediaFile (the client value is never trusted), over-limit
 * audio fails the file without retry, the note is re-encoded to mono
 * AAC/m4a when ffmpeg exists, and without the binaries the original is
 * kept (warning in production). Also covers binary discovery (config →
 * env → PATH) and the `storage:health` report.
 *
 * The audio fixture is generated on the fly with ffmpeg (a 3 s sine as
 * WebM/Opus, like Chrome's MediaRecorder); tests that need it are skipped
 * when ffmpeg is not installed on this machine.
 */

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\Training\SubmitCheerMessage;
use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Models\CheerMessage;
use App\Models\MediaFile;
use App\Services\Media\AudioTranscoder;
use App\Services\Media\MediaBinaries;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Training/training-helpers.php';

beforeEach(function () {
    config(['finisher.media_disk' => 'synology_media']);
    Storage::fake('synology_media');
    Storage::fake('media_tmp');
    Notification::fake();
});

/** Turns on PATH discovery and returns ffmpeg, or skips the test. */
function audioTestFfmpeg(): string
{
    config(['finisher.media.binaries.path_lookup' => true]);
    $ffmpeg = app(MediaBinaries::class)->ffmpeg();

    if ($ffmpeg === null || app(MediaBinaries::class)->ffprobe() === null) {
        test()->markTestSkipped('ffmpeg/ffprobe no están instalados en esta máquina (sudo apt install ffmpeg o FFMPEG_BINARY); se omite la prueba con audio real.');
    }

    return $ffmpeg;
}

/** A real stereo WebM/Opus clip of `$seconds` seconds. */
function audioFixture(float $seconds = 3.0): UploadedFile
{
    $ffmpeg = audioTestFfmpeg();
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'flaudio_'.bin2hex(random_bytes(6)).'.webm';

    $result = Process::timeout(60)->run([
        $ffmpeg, '-hide_banner', '-y', '-f', 'lavfi', '-i', "sine=frequency=440:duration={$seconds}",
        '-ac', '2', '-c:a', 'libopus', '-b:a', '96k', $path,
    ]);

    if (! $result->successful() || ! is_file($path)) {
        test()->markTestSkipped('Este ffmpeg no pudo generar el audio de prueba (¿sin libopus?): '.mb_substr($result->errorOutput(), -200));
    }

    return new UploadedFile($path, 'voz.webm', null, null, true);
}

function audioRules(): MediaUploadRules
{
    return new MediaUploadRules([MediaKind::Audio->value => ManageStoryMedia::AUDIO_MIMES], [MediaKind::Audio->value => 15 * 1024 * 1024]);
}

test('the pipeline stores the ffprobe duration and re-encodes to mono AAC/m4a', function () {
    $upload = audioFixture(3.0);
    $storage = app(MediaStorage::class);

    $file = $storage->process($storage->accept($upload, 'tests/audio', audioRules()))->fresh();

    expect($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->duration_ms)->toBeGreaterThan(2800)->toBeLessThan(3300)
        ->and($file->path)->toEndWith('_audio.m4a')
        ->and($file->variants['audio']['mime'])->toBe('audio/mp4')
        ->and($file->resolveVariant('default')['mime'])->toBe('audio/mp4')
        ->and($file->original_path)->toBeNull();

    Storage::disk('synology_media')->assertExists($file->path);

    $local = sys_get_temp_dir().DIRECTORY_SEPARATOR.'flaudio_check_'.$file->uuid.'.m4a';
    file_put_contents($local, Storage::disk('synology_media')->get($file->path));
    $probe = app(AudioTranscoder::class)->probe($local);
    @unlink($local);

    expect($probe)->not->toBeNull()
        ->and($probe['codec'])->toBe('aac')
        ->and($probe['channels'])->toBe(1);
});

test('transcoding can be turned off: the measured original is served as-is', function () {
    config(['finisher.media.audio.transcode' => false]);
    $upload = audioFixture(2.0);
    $storage = app(MediaStorage::class);

    $file = $storage->process($storage->accept($upload, 'tests/audio', audioRules()))->fresh();

    expect($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->duration_ms)->toBeGreaterThan(1800)->toBeLessThan(2300)
        ->and($file->path)->toEndWith('_original.webm')
        ->and($file->variants)->toBeNull();
});

test('over-limit audio fails the file without retry and nothing reaches the media disk', function () {
    config(['finisher.media.audio.max_seconds' => 1]);
    $upload = audioFixture(3.0);
    $storage = app(MediaStorage::class);

    $file = $storage->process($storage->accept($upload, 'tests/audio', audioRules()))->fresh();

    expect($file->status)->toBe(MediaFileStatus::Failed)
        ->and($file->error)->toContain('audio_too_long')
        ->and($file->attempts)->toBe(1)
        ->and($file->path)->toBeNull();

    expect(Storage::disk('synology_media')->allFiles())->toBeEmpty();
});

test('a cheer message keeps the ffprobe duration, not the browser value', function () {
    $upload = audioFixture(3.0);
    $link = cheerLinkFor();

    $message = app(SubmitCheerMessage::class)->handle($link, [
        'sender_name' => 'Mamá', 'icon' => 'heart', 'duration_seconds' => 150,
    ], $upload, '203.0.113.9');

    $message = CheerMessage::query()->findOrFail($message?->id);
    $file = MediaFile::query()->findOrFail($message->audio_media_file_id);

    expect($message->duration_seconds)->toBe(3)
        ->and($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->path)->toEndWith('_audio.m4a');
});

test('the cheer limit applies to cheer audio in the pipeline', function () {
    $upload = audioFixture(3.0);
    $link = cheerLinkFor();
    $storage = app(MediaStorage::class);
    config(['training.cheers.max_audio_seconds' => 1]);

    $file = $storage->accept($upload, 'tests/audio', audioRules());
    $message = CheerMessage::create([
        'cheer_link_id' => $link->id, 'recipient_user_id' => $link->user_id, 'sender_name' => 'Papá',
        'icon' => 'heart', 'audio_media_file_id' => $file->id, 'duration_seconds' => 1, 'status' => 'visible',
    ]);
    $storage->process($file, $message);

    expect($file->fresh()->status)->toBe(MediaFileStatus::Failed)
        ->and($message->fresh()->duration_seconds)->toBe(1);
});

test('without ffprobe the original is kept and production logs a warning', function () {
    config([
        'finisher.media.binaries.path_lookup' => false,
        'finisher.media.video.ffmpeg' => null,
        'finisher.media.video.ffprobe' => null,
    ]);
    app()->detectEnvironment(fn () => 'production');
    Log::spy();

    $storage = app(MediaStorage::class);
    $file = $storage->process($storage->accept(cheerUpload('voz.mp3', mp3Bytes()), 'tests/audio', audioRules()))->fresh();

    app()->detectEnvironment(fn () => 'testing');

    expect($file->status)->toBe(MediaFileStatus::Ready)
        ->and($file->duration_ms)->toBeNull()
        ->and($file->path)->toEndWith('_original.mp3');

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'media.ffprobe_missing')->once();
})->skip(fn () => getenv('FFPROBE_BINARY') !== false || getenv('FFMPEG_BINARY') !== false, 'FFMPEG_BINARY/FFPROBE_BINARY están en el entorno del proceso.');

test('binaries resolve config first, then the PATH', function () {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'flbin_'.bin2hex(random_bytes(4));
    mkdir($dir);
    $windows = DIRECTORY_SEPARATOR === '\\';

    foreach (['ffmpeg', 'ffprobe'] as $name) {
        $bin = $dir.DIRECTORY_SEPARATOR.$name.($windows ? '.exe' : '');
        file_put_contents($bin, '');
        @chmod($bin, 0755);
    }

    $originalPath = getenv('PATH');
    putenv('PATH='.$dir);

    try {
        config(['finisher.media.video.ffmpeg' => null, 'finisher.media.video.ffprobe' => null, 'finisher.media.binaries.path_lookup' => true]);
        $status = (new MediaBinaries)->status();
        expect($status['ffmpeg']['source'])->toBe('path')
            ->and($status['ffmpeg']['path'])->toStartWith($dir)
            ->and($status['ffprobe']['source'])->toBe('path');

        config(['finisher.media.video.ffmpeg' => '/opt/ffmpeg/bin/ffmpeg']);
        $status = (new MediaBinaries)->status();
        expect($status['ffmpeg'])->toBe(['path' => '/opt/ffmpeg/bin/ffmpeg', 'source' => 'config'])
            ->and($status['ffprobe']['source'])->toBe('path');

        config(['finisher.media.video.ffmpeg' => null, 'finisher.media.binaries.path_lookup' => false]);
        expect((new MediaBinaries)->ffmpeg())->toBeNull();
    } finally {
        putenv('PATH='.$originalPath);
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $bin) {
            @unlink($bin);
        }
        @rmdir($dir);
    }
});

test('storage:health reports the media binaries without failing on them', function () {
    config([
        'finisher.media.binaries.path_lookup' => false,
        'finisher.media.video.ffmpeg' => null,
        'finisher.media.video.ffprobe' => null,
    ]);
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'flhealth_'.bin2hex(random_bytes(4));
    mkdir($root);
    config([
        'filesystems.disks.probe_media' => ['driver' => 'local', 'root' => $root, 'throw' => true],
        'finisher.media_disk' => 'probe_media',
        'filesystems.health.disks' => ['probe_media'],
        'filesystems.health.min_free_mb' => 1,
        'filesystems.health.media_sentinel' => '',
        'filesystems.health.require_mount_point' => false,
    ]);
    Storage::forgetDisk('probe_media');

    try {
        $this->artisan('storage:health')
            ->expectsOutputToContain('FALTA')
            ->assertSuccessful();
    } finally {
        @rmdir($root.DIRECTORY_SEPARATOR.'.health');
        @rmdir($root);
    }
})->skip(fn () => getenv('FFPROBE_BINARY') !== false || getenv('FFMPEG_BINARY') !== false, 'FFMPEG_BINARY/FFPROBE_BINARY están en el entorno del proceso.');
