<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fl-storage-health-'.Str::random(8);
    File::ensureDirectoryExists($this->root);

    config([
        'filesystems.disks.probe_media' => ['driver' => 'local', 'root' => $this->root, 'throw' => true],
        'finisher.media_disk' => 'probe_media',
        'filesystems.health.disks' => ['probe_media'],
        'filesystems.health.min_free_mb' => 1,
        'filesystems.health.media_sentinel' => '',
        'filesystems.health.require_mount_point' => false,
    ]);
    Storage::forgetDisk('probe_media');
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

test('a healthy local disk passes and leaves no probe file behind', function () {
    $this->artisan('storage:health')->assertSuccessful();

    expect(File::allFiles($this->root))->toBe([]);
});

test('json output reports free space for a local disk', function () {
    expect(Artisan::call('storage:health', ['--json' => true]))->toBe(0);

    $report = json_decode(Artisan::output(), true);

    expect($report['ok'])->toBeTrue()
        ->and($report['disks'][0]['disk'])->toBe('probe_media')
        ->and($report['disks'][0]['free_mb'])->toBeInt()->toBeGreaterThan(0);
});

test('a missing mount root fails without creating the directory', function () {
    Log::spy();
    $missing = $this->root.DIRECTORY_SEPARATOR.'not-mounted';
    config(['filesystems.disks.probe_media.root' => $missing]);
    Storage::forgetDisk('probe_media');

    $this->artisan('storage:health')->assertFailed();

    expect(is_dir($missing))->toBeFalse();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => $message === 'storage.health_failed')->once();
});

test('a missing sentinel file means the NAS is not mounted', function () {
    config(['filesystems.health.media_sentinel' => '.fl-media-mounted']);

    $this->artisan('storage:health')->assertFailed();

    File::put($this->root.DIRECTORY_SEPARATOR.'.fl-media-mounted', '');

    $this->artisan('storage:health')->assertSuccessful();
});

test('low free space fails the check', function () {
    config(['filesystems.health.min_free_mb' => PHP_INT_MAX]);

    $this->artisan('storage:health')->assertFailed();
});

test('an unknown disk fails', function () {
    $this->artisan('storage:health', ['--disk' => ['does_not_exist']])->assertFailed();
});

test('a faked (non-local-config) disk is probed through the filesystem API', function () {
    Storage::fake('faked_disk');
    config(['filesystems.disks.faked_disk' => ['driver' => 'memory-like']]);

    $this->artisan('storage:health', ['--disk' => ['faked_disk']])->assertSuccessful();

    expect(Storage::disk('faked_disk')->allFiles())->toBe([]);
});

test('the command is scheduled', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'storage:health'));

    expect($events)->toHaveCount(1);
});
