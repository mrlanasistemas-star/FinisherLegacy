<?php

namespace App\Actions\Media;

use App\Models\AthleteEventMedia;
use Illuminate\Support\Facades\Storage;

class DeleteAthleteEventMedia
{
    public function handle(AthleteEventMedia $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }
}
