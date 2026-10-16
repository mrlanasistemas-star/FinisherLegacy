<?php

namespace App\Jobs\Media;

use App\Jobs\Media\Concerns\MarksMediaFileFailed;
use App\Models\MediaFile;
use App\Services\Media\MediaStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pipeline step 2: streams the rendered files (and the original, when it
 * is kept) from the local temp disk to the media disk — the Synology
 * mount in production. Streams, never loads a 300 MB video in memory;
 * verifies sizes; retried with backoff because a NAS/Tailscale hiccup is
 * the most likely failure here.
 */
class TransferMediaFile implements ShouldQueue
{
    use MarksMediaFileFailed, Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public int $timeout = 1800;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public MediaFile $mediaFile) {}

    public function handle(MediaStorage $storage): void
    {
        $file = $this->mediaFile;
        $tmp = Storage::disk($storage->tmpDisk());
        $target = Storage::disk($file->disk);
        $work = $storage->workDirectory($file);

        foreach ($tmp->files($work.'/out') as $rendered) {
            $this->copy($tmp, $rendered, $target, $file->directory.'/'.basename($rendered));
        }

        $alreadyThere = $file->source_disk === $file->disk && $file->source_path === $file->original_path;

        if ($file->original_path !== null && ! $alreadyThere) {
            if ($file->source_disk === null || $file->source_path === null) {
                throw new RuntimeException('Media file has no source to transfer.');
            }

            $this->copy(Storage::disk($file->source_disk), $file->source_path, $target, $file->original_path);
        }
    }

    private function copy(Filesystem $from, string $fromPath, Filesystem $to, string $toPath): void
    {
        $stream = $from->readStream($fromPath);

        if (! is_resource($stream)) {
            throw new RuntimeException("Cannot read [{$fromPath}] for transfer.");
        }

        try {
            $to->writeStream($toPath, $stream);
        } finally {
            fclose($stream);
        }

        if ($to->size($toPath) !== $from->size($fromPath)) {
            throw new RuntimeException("Size mismatch after transferring [{$toPath}].");
        }
    }
}
