<?php

namespace App\Jobs\Media\Concerns;

use App\Enums\MediaFileStatus;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Failure observability for every pipeline step (spec §116): the file is
 * flagged `failed` with a short reason, a structured log line carries the
 * context, and the temp source is KEPT so `php artisan media:retry-failed`
 * can run the pipeline again.
 *
 * @property MediaFile $mediaFile
 */
trait MarksMediaFileFailed
{
    public function failed(?Throwable $exception): void
    {
        $file = MediaFile::query()->whereKey($this->mediaFile->getKey())->first();

        if ($file === null) {
            return;
        }

        $reason = $exception === null ? 'unknown' : class_basename($exception).': '.$exception->getMessage();

        $file->forceFill([
            'status' => MediaFileStatus::Failed,
            'error' => Str::limit($reason, 1000),
            'failed_at' => now(),
        ])->save();

        Log::error('media.pipeline_failed', [
            'media_uuid' => $file->uuid,
            'kind' => $file->kind->value,
            'step' => class_basename(static::class),
            'disk' => $file->disk,
            'directory' => $file->directory,
            'attempts' => $file->attempts,
            'exception' => $exception !== null ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);
    }
}
