<?php

namespace App\Services\Media;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one stored file with the right headers (spec §68): Range
 * requests (video seeking / iOS needs 206), ETag/304, public vs private
 * cache policy, `nosniff`. Local disks (the NFS/SMB-mounted Synology)
 * use BinaryFileResponse; other drivers (sftp) stream with manual Range.
 * The response never reveals the disk path.
 */
class MediaStreamResponder
{
    public const CACHE_PUBLIC = 'public';

    public const CACHE_PRIVATE = 'private';

    public const CACHE_NONE = 'none';

    public function respond(Request $request, string $diskName, string $path, string $mime, string $cache, ?string $etag = null): Response
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        abort_unless($disk->exists($path), 404);

        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => $this->cacheControl($cache),
        ];

        if ($disk instanceof LocalFilesystemAdapter) {
            $response = new BinaryFileResponse($disk->path($path), 200, $headers, false, 'inline', false, false);
            $response->headers->set('Content-Disposition', 'inline');

            if ($etag !== null) {
                $response->setEtag($etag);
            }

            $response->isNotModified($request);

            return $response;
        }

        return $this->streamed($request, $disk, $path, $headers, $etag);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function streamed(Request $request, FilesystemAdapter $disk, string $path, array $headers, ?string $etag): Response
    {
        $size = (int) $disk->size($path);
        [$start, $end] = [0, max(0, $size - 1)];
        $status = 200;

        if ($etag !== null) {
            $headers['ETag'] = '"'.$etag.'"';
            if (in_array('"'.$etag.'"', $request->getETags(), true)) {
                return new Response('', 304, $headers);
            }
        }

        $range = $request->headers->get('Range');
        if (is_string($range) && $size > 0) {
            if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) !== 1 || ($m[1] === '' && $m[2] === '')) {
                return new Response('', 416, $headers + ['Content-Range' => "bytes */{$size}"]);
            }

            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $end : min($end, (int) $m[2]);
            }

            if ($start > $end || $start >= $size) {
                return new Response('', 416, $headers + ['Content-Range' => "bytes */{$size}"]);
            }

            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;
        $headers['Content-Disposition'] = 'inline';

        return new StreamedResponse(function () use ($disk, $path, $start, $length) {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return;
            }

            if ($start > 0) {
                $meta = stream_get_meta_data($stream);
                if ($meta['seekable']) {
                    fseek($stream, $start);
                } else {
                    $skip = $start;
                    while ($skip > 0 && ! feof($stream)) {
                        $chunk = fread($stream, (int) min(1048576, $skip));
                        $skip -= $chunk === false ? $skip : strlen($chunk);
                    }
                }
            }

            $remaining = $length;
            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, (int) min(1048576, $remaining));
                if ($chunk === false) {
                    break;
                }
                $remaining -= strlen($chunk);
                echo $chunk;
                flush();
            }

            fclose($stream);
        }, $status, $headers);
    }

    private function cacheControl(string $cache): string
    {
        return match ($cache) {
            self::CACHE_PUBLIC => 'public, max-age='.(int) config('finisher.media.urls.public_max_age', 604800).', immutable',
            self::CACHE_PRIVATE => 'private, max-age='.(int) config('finisher.media.urls.private_max_age', 1800),
            default => 'private, no-store',
        };
    }
}
