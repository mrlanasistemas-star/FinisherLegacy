<?php

namespace App\Actions\Media;

use App\Models\AthleteEventMedia;
use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\Storage;

class DeleteAthleteEventMedia
{
    public function __construct(private readonly MediaStorage $storage) {}

    public function handle(AthleteEventMedia $media): void
    {
        if ($media->mediaFile !== null) {
            // Every rendition (variants, poster, original) + temp leftovers.
            $this->storage->delete($media->mediaFile);
        } else {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();
    }
}
