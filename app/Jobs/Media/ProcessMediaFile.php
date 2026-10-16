<?php

namespace App\Jobs\Media;

use App\Enums\MediaKind;
use App\Jobs\Media\Concerns\MarksMediaFileFailed;
use App\Models\MediaFile;
use App\Services\Media\ImageVariantGenerator;
use App\Services\Media\MediaStorage;
use App\Services\Media\VideoTranscoder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pipeline step 1 (spec §65-§67): renders everything into the temp work
 * folder — image variants, or the MP4 + poster when ffmpeg exists — and
 * records the FINAL relative paths on the MediaFile. Nothing touches the
 * media disk yet (TransferMediaFile does).
 */
class ProcessMediaFile implements ShouldQueueAfterCommit
{
    use MarksMediaFileFailed, Queueable;

    public int $tries = 2;

    public int $timeout;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public MediaFile $mediaFile)
    {
        $this->timeout = (int) config('finisher.media.video.timeout_seconds', 1800) + 120;
    }

    public function handle(MediaStorage $storage, ImageVariantGenerator $images, VideoTranscoder $video): void
    {
        $file = $this->mediaFile;
        $file->increment('attempts');

        $tmp = Storage::disk($storage->tmpDisk());
        $work = $storage->workDirectory($file);
        $tmp->deleteDirectory($work.'/out');
        $tmp->makeDirectory($work.'/out');
        $out = $tmp->path($work.'/out');
        $source = $this->localSource($file, $storage);

        $file->forceFill(['variants' => null, 'poster_path' => null, 'path' => null, 'original_path' => null]);

        match ($file->kind) {
            MediaKind::Image => $this->processImage($file, $images, $source, $out),
            MediaKind::Video => $this->processVideo($file, $images, $video, $source, $out),
            MediaKind::Audio, MediaKind::File => $file->forceFill([
                'original_path' => $this->originalPath($file),
                'path' => $this->originalPath($file),
            ]),
        };

        $file->save();
    }

    private function processImage(MediaFile $file, ImageVariantGenerator $images, string $source, string $out): void
    {
        $result = $images->generate($source, $out, $file->uuid);
        $variants = $this->finalVariants($file, $result['variants']);
        $keepOriginal = (bool) config('finisher.media.images.keep_original', true);

        $file->forceFill([
            'width' => $result['width'],
            'height' => $result['height'],
            'variants' => $variants,
            'path' => $variants['display']['path'] ?? collect($variants)->last()['path'] ?? null,
            'original_path' => $keepOriginal ? $this->originalPath($file) : null,
        ]);
    }

    private function processVideo(MediaFile $file, ImageVariantGenerator $images, VideoTranscoder $video, string $source, string $out): void
    {
        if (! $video->available()) {
            // No ffmpeg on this host: keep and serve the original as-is.
            Log::warning('media.ffmpeg_missing', ['media_uuid' => $file->uuid, 'hint' => 'Set FFMPEG_BINARY and run php artisan media:retry-failed --pending-transcode']);
            $file->forceFill(['original_path' => $this->originalPath($file), 'path' => $this->originalPath($file)]);

            return;
        }

        $probe = $video->probe($source);
        $maxMs = (int) config('finisher.media.max_video_seconds', 600) * 1000;

        if ($probe['duration_ms'] !== null && $probe['duration_ms'] > $maxMs) {
            throw new RuntimeException('Video longer than finisher.media.max_video_seconds.');
        }

        $mp4 = $file->uuid.'_video.mp4';
        $video->toMp4($source, $out.DIRECTORY_SEPARATOR.$mp4);

        $variants = [
            'video' => [
                'path' => $file->directory.'/'.$mp4,
                'width' => $probe['width'],
                'height' => $probe['height'],
                'mime' => 'video/mp4',
                'size' => (int) @filesize($out.DIRECTORY_SEPARATOR.$mp4),
            ],
        ];

        if ((bool) config('finisher.media.video.webm', false)) {
            $webm = $file->uuid.'_video.webm';
            $video->toWebm($source, $out.DIRECTORY_SEPARATOR.$webm);
            $variants['video_webm'] = ['path' => $file->directory.'/'.$webm, 'width' => $probe['width'], 'height' => $probe['height'], 'mime' => 'video/webm', 'size' => (int) @filesize($out.DIRECTORY_SEPARATOR.$webm)];
        }

        $poster = $file->uuid.'_poster.jpg';
        $video->poster($out.DIRECTORY_SEPARATOR.$mp4, $out.DIRECTORY_SEPARATOR.$poster, (float) config('finisher.media.video.poster_at_seconds', 1));

        if (is_file($out.DIRECTORY_SEPARATOR.$poster)) {
            $variants['poster'] = ['path' => $file->directory.'/'.$poster, 'width' => $probe['width'], 'height' => $probe['height'], 'mime' => 'image/jpeg', 'size' => (int) filesize($out.DIRECTORY_SEPARATOR.$poster)];
            $posterVariants = $images->generate($out.DIRECTORY_SEPARATOR.$poster, $out, $file->uuid.'_poster', ['thumb' => 320, 'card' => 800]);

            foreach ($this->finalVariants($file, $posterVariants['variants']) as $name => $variant) {
                $variants['poster_'.$name] = $variant;
            }
        }

        $file->forceFill([
            'width' => $probe['width'],
            'height' => $probe['height'],
            'duration_ms' => $probe['duration_ms'],
            'variants' => $variants,
            'path' => $variants['video']['path'],
            'poster_path' => $variants['poster']['path'] ?? null,
            'original_path' => (bool) config('finisher.media.video.keep_original', false) ? $this->originalPath($file) : null,
        ]);
    }

    /**
     * @param  array<string, array{filename: string, width: int, height: int, mime: string, size: int}>  $rendered
     * @return array<string, array{path: string, width: int, height: int, mime: string, size: int}>
     */
    private function finalVariants(MediaFile $file, array $rendered): array
    {
        $variants = [];
        foreach ($rendered as $name => $variant) {
            $variants[$name] = [
                'path' => $file->directory.'/'.$variant['filename'],
                'width' => $variant['width'],
                'height' => $variant['height'],
                'mime' => $variant['mime'],
                'size' => $variant['size'],
            ];
        }

        return $variants;
    }

    private function originalPath(MediaFile $file): string
    {
        return $file->directory.'/'.$file->uuid.'_original.'.$file->extension;
    }

    /**
     * Absolute local path of the bytes to process. On a retry the source
     * may already live on the media disk — it is streamed back to temp.
     */
    private function localSource(MediaFile $file, MediaStorage $storage): string
    {
        $tmp = Storage::disk($storage->tmpDisk());

        if ($file->source_disk === null || $file->source_path === null) {
            throw new RuntimeException('Media file has no source to process.');
        }

        if ($file->source_disk === $storage->tmpDisk()) {
            if (! $tmp->exists($file->source_path)) {
                throw new RuntimeException('Temp source is missing.');
            }

            return $tmp->path($file->source_path);
        }

        $stream = Storage::disk($file->source_disk)->readStream($file->source_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Source is missing on the media disk.');
        }

        $local = $storage->workDirectory($file).'/source.'.$file->extension;
        $tmp->writeStream($local, $stream);
        fclose($stream);

        return $tmp->path($local);
    }
}
