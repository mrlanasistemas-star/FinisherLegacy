<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Services\Privacy\ContentPin;
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
        $config = (array) config('finisher.media.urls');

        // PIN-protected files: never public, short-lived, and only marked
        // `pin=1` (the one signature MediaFileController honours for them)
        // while the owner's unlock grant is on this request.
        $guard = $file->pinGuard();
        $pin = $guard !== null && app(ContentPin::class)->reveals($guard);
        $public = $guard !== null ? false : ($public ?? $file->isPublic());

        $ttl = $guard !== null
            ? ContentPin::MEDIA_URL_TTL_MINUTES * 60
            : (int) ($public ? $config['public_ttl_minutes'] : $config['private_ttl_minutes']) * 60;
        $bucket = $guard !== null
            ? 60
            : max(60, (int) ($public ? $config['public_bucket_minutes'] : $config['private_bucket_minutes']) * 60);
        $expiresAt = Carbon::createFromTimestamp((int) (ceil((now()->getTimestamp() + $ttl) / $bucket) * $bucket));

        $relative = URL::temporarySignedRoute(
            'media.file',
            $expiresAt,
            // `scope=public` lets the controller stop honouring a long-lived
            // public URL the moment the owner makes the file private.
            array_filter(['mediaFile' => $file->uuid, 'variant' => $variant, 'scope' => $public ? 'public' : null, 'pin' => $pin ? 1 : null]),
            absolute: false,
        );

        $cdn = config('finisher.media_cdn_url');

        return $public && is_string($cdn) && $cdn !== ''
            ? rtrim($cdn, '/').$relative
            : url($relative);
    }
}
