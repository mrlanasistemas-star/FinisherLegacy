<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * ffmpeg/ffprobe wrapper (spec §67): H.264 + yuv420p MP4 with faststart
 * (plays everywhere, starts before fully downloaded), a poster frame, and
 * duration/dimensions via ffprobe. Binaries come ONLY from env
 * (FFMPEG_BINARY / FFPROBE_BINARY); when ffmpeg is absent the pipeline
 * keeps the original and marks it `pending_transcode`.
 */
class VideoTranscoder
{
    private ?bool $available = null;

    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $ffmpeg = $this->ffmpeg();

        return $this->available = $ffmpeg !== null
            && Process::timeout(15)->run([$ffmpeg, '-hide_banner', '-version'])->successful();
    }

    /**
     * @return array{duration_ms: int|null, width: int|null, height: int|null}
     */
    public function probe(string $path): array
    {
        $empty = ['duration_ms' => null, 'width' => null, 'height' => null];
        $ffprobe = $this->ffprobe();

        if ($ffprobe === null) {
            return $empty;
        }

        $result = Process::timeout(60)->run([
            $ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path,
        ]);

        if (! $result->successful()) {
            return $empty;
        }

        $data = json_decode($result->output(), true);
        if (! is_array($data)) {
            return $empty;
        }

        $video = [];
        foreach ((array) ($data['streams'] ?? []) as $stream) {
            if (is_array($stream) && ($stream['codec_type'] ?? null) === 'video') {
                $video = $stream;
                break;
            }
        }
        $duration = $data['format']['duration'] ?? ($video['duration'] ?? null);

        return [
            'duration_ms' => is_numeric($duration) ? (int) round((float) $duration * 1000) : null,
            'width' => isset($video['width']) ? (int) $video['width'] : null,
            'height' => isset($video['height']) ? (int) $video['height'] : null,
        ];
    }

    public function toMp4(string $source, string $destination): void
    {
        $maxWidth = (int) config('finisher.media.video.max_width', 1920);

        $this->run([
            (string) $this->ffmpeg(), '-hide_banner', '-y', '-i', $source,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-c:v', 'libx264', '-preset', (string) config('finisher.media.video.preset', 'medium'),
            '-crf', (string) config('finisher.media.video.crf', 23),
            '-pix_fmt', 'yuv420p', '-profile:v', 'high',
            '-vf', "scale='min({$maxWidth},iw)':-2",
            '-c:a', 'aac', '-b:a', (string) config('finisher.media.video.audio_bitrate', '128k'),
            '-movflags', '+faststart',
            $destination,
        ]);
    }

    public function toWebm(string $source, string $destination): void
    {
        $maxWidth = (int) config('finisher.media.video.max_width', 1920);

        $this->run([
            (string) $this->ffmpeg(), '-hide_banner', '-y', '-i', $source,
            '-c:v', 'libvpx-vp9', '-crf', '33', '-b:v', '0', '-vf', "scale='min({$maxWidth},iw)':-2",
            '-c:a', 'libopus', $destination,
        ]);
    }

    public function poster(string $source, string $destination, float $atSeconds): void
    {
        $this->run([
            (string) $this->ffmpeg(), '-hide_banner', '-y', '-ss', (string) $atSeconds, '-i', $source,
            '-frames:v', '1', '-q:v', '3', $destination,
        ]);

        if (! is_file($destination)) {
            // Clip shorter than the seek point: take the very first frame.
            $this->run([(string) $this->ffmpeg(), '-hide_banner', '-y', '-i', $source, '-frames:v', '1', '-q:v', '3', $destination]);
        }
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): void
    {
        $result = Process::timeout((int) config('finisher.media.video.timeout_seconds', 1800))->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('ffmpeg failed: '.mb_substr(trim($result->errorOutput()), -500));
        }
    }

    private function ffmpeg(): ?string
    {
        $binary = config('finisher.media.video.ffmpeg');

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    private function ffprobe(): ?string
    {
        $binary = config('finisher.media.video.ffprobe');

        if (is_string($binary) && $binary !== '') {
            return $binary;
        }

        // Usually installed next to ffmpeg.
        $ffmpeg = $this->ffmpeg();

        return $ffmpeg !== null ? preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', $ffmpeg) : null;
    }
}
