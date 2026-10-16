<?php

namespace App\Enums;

enum MediaFileStatus: string
{
    // Accepted, waiting for / inside the queue pipeline.
    case Processing = 'processing';
    // Variants (or the MP4) are on the media disk and servable.
    case Ready = 'ready';
    // Video on a host without ffmpeg: the original is stored and served
    // as-is; `php artisan media:retry-failed --pending-transcode`
    // transcodes it once ffmpeg is installed.
    case PendingTranscode = 'pending_transcode';
    case Failed = 'failed';

    public function isServable(): bool
    {
        return $this === self::Ready || $this === self::PendingTranscode;
    }
}
