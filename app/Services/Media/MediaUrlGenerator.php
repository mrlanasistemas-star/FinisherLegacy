<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Temporary signed URLs to App\Http\Controllers\MediaFileController
 * (spec §68) — never a disk URL, never a NAS path. Signed RELATIVE (host
 * independent) so the same URL works behind a future CDN
 * (`finisher.media_cdn_url`, unused by default — only public media would
 * go through it). Expiry is rounded up to a bucket, so the same URL is
 * handed out for a while and browsers/CDNs can cache it.
 */
class MediaUrlGenerator
{
    public function url(MediaFile $file, string $variant = 'default', ?bool $public = null): string
    {
        $public ??= $file->isPublic();
        $config = (array) config('finisher.media.urls');

        $ttl = (int) ($public ? $config['public_ttl_minutes'] : $config['private_ttl_minutes']) * 60;
        $bucket = max(60, (int) ($public ? $config['public_bucket_minutes'] : $config['private_bucket_minutes']) * 60);
        $expiresAt = Carbon::createFromTimestamp((int) (ceil((now()->getTimestamp() + $ttl) / $bucket) * $bucket));

        $relative = URL::temporarySignedRoute(
            'media.file',
            $expiresAt,
            // `scope=public` lets the controller stop honouring a long-lived
            // public URL the moment the owner makes the file private.
            array_filter(['mediaFile' => $file->uuid, 'variant' => $variant, 'scope' => $public ? 'public' : null]),
            absolute: false,
        );

        $cdn = config('finisher.media_cdn_url');

        return $public && is_string($cdn) && $cdn !== ''
            ? rtrim($cdn, '/').$relative
            : url($relative);
    }
}
