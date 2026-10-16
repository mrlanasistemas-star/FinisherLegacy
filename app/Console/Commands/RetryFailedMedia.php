<?php

namespace App\Console\Commands;

use App\Enums\MediaFileStatus;
use App\Models\MediaFile;
use App\Services\Media\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-runs the media pipeline for `failed` files whose source still exists
 * (the temp source is kept on failure; after a transfer the original on
 * the media disk is the source). `--pending-transcode` also re-queues
 * videos stored without ffmpeg, once FFMPEG_BINARY is configured.
 */
class RetryFailedMedia extends Command
{
    protected $signature = 'media:retry-failed
        {--uuid=* : Only these media file uuids}
        {--pending-transcode : Also re-queue videos waiting for ffmpeg}
        {--limit=100 : Max files to re-queue}
        {--dry-run : List what would be retried}';

    protected $description = 'Re-queue failed media processing (and optionally pending video transcodes).';

    public function handle(MediaStorage $storage): int
    {
        $statuses = [MediaFileStatus::Failed];
        if ($this->option('pending-transcode')) {
            $statuses[] = MediaFileStatus::PendingTranscode;
        }

        $uuids = array_values(array_filter((array) $this->option('uuid'), 'is_string'));

        $files = MediaFile::query()
            ->whereIn('status', $statuses)
            ->when($uuids !== [], fn ($q) => $q->whereIn('uuid', $uuids))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if ($file->source_disk === null || $file->source_path === null || ! Storage::disk($file->source_disk)->exists($file->source_path)) {
                $this->warn("{$file->uuid}: source missing — cannot retry ({$file->error})");
                $skipped++;

                continue;
            }

            $this->line("{$file->uuid}: {$file->status->value} → processing".($file->error ? " (last error: {$file->error})" : ''));

            if (! $this->option('dry-run')) {
                $file->forceFill(['status' => MediaFileStatus::Processing, 'failed_at' => null])->save();
                $storage->dispatchPipeline($file);
            }

            $queued++;
        }

        $this->info("Re-queued {$queued}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
