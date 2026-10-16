<?php

namespace App\Jobs\Media;

use App\Jobs\Media\Concerns\MarksMediaFileFailed;
use App\Models\MediaFile;
use App\Services\Media\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pipeline step 3: status ready / pending_transcode, owner row sync, temp
 * cleanup (MediaStorage::finalize()).
 */
class FinalizeMediaFile implements ShouldQueue
{
    use MarksMediaFileFailed, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public MediaFile $mediaFile) {}

    public function handle(MediaStorage $storage): void
    {
        $storage->finalize($this->mediaFile);
    }
}
