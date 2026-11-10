<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Audio side of the media pipeline (Legacy story voice notes, Entrenamientos
 * cheer messages): measures the REAL duration with ffprobe — the client
 * value is never trusted when ffprobe exists — and optionally re-encodes to
 * AAC in an .m4a container (mono, 64–96 kbps, faststart). That plays on iOS
 * Safari/AVPlayer and Android ExoPlayer alike, which a Chrome/Firefox
 * WebM/Opus recording does not, and is usually a fraction of the size.
 *
 * Binaries come from MediaBinaries (config → env → PATH). Without ffprobe
 * probe() returns null and the caller keeps its client-side duration cap.
 */
class AudioTranscoder
{
    private ?bool $ffmpegWorks = null;

    public function __construct(private readonly MediaBinaries $binaries) {}

    public function canProbe(): bool
    {
        return $this->binaries->ffprobe() !== null;
    }

    public function canTranscode(): bool
    {
        if ($this->ffmpegWorks !== null) {
            return $this->ffmpegWorks;
        }

        $ffmpeg = $this->binaries->ffmpeg();

        return $this->ffmpegWorks = $ffmpeg !== null
            && Process::timeout(15)->run([$ffmpeg, '-hide_banner', '-version'])->successful();
    }

    /**
     * Null when ffprobe is unavailable or cannot read the file at all.
     * `has_audio` false = a readable container with no audio stream.
     *
     * @return array{duration_ms: int|null, has_audio: bool, codec: string|null, channels: int|null, bit_rate: int|null}|null
     */
    public function probe(string $path): ?array
    {
        $ffprobe = $this->binaries->ffprobe();

        if ($ffprobe === null) {
            return null;
        }

        $result = Process::timeout(60)->run([
            $ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path,
        ]);

        if (! $result->successful()) {
            return null;
        }

        $data = json_decode($result->output(), true);
        if (! is_array($data)) {
            return null;
        }

        $audio = null;
        foreach ((array) ($data['streams'] ?? []) as $stream) {
            if (is_array($stream) && ($stream['codec_type'] ?? null) === 'audio') {
                $audio = $stream;
                break;
            }
        }

        $format = is_array($data['format'] ?? null) ? $data['format'] : [];
        // WebM from MediaRecorder often has no container duration ("N/A");
        // the stream duration or the DURATION tag is the fallback.
        $duration = $this->seconds($format['duration'] ?? null)
            ?? $this->seconds($audio['duration'] ?? null)
            ?? $this->tagSeconds($audio['tags']['DURATION'] ?? null);

        $bitRate = $audio['bit_rate'] ?? ($format['bit_rate'] ?? null);

        return [
            'duration_ms' => $duration !== null ? (int) round($duration * 1000) : null,
            'has_audio' => $audio !== null,
            'codec' => isset($audio['codec_name']) ? (string) $audio['codec_name'] : null,
            'channels' => isset($audio['channels']) ? (int) $audio['channels'] : null,
            'bit_rate' => is_numeric($bitRate) ? (int) $bitRate : null,
        ];
    }

    /**
     * True when re-encoding would gain nothing: already mono AAC at or
     * under the target bitrate.
     *
     * @param  array{codec: string|null, channels: int|null, bit_rate: int|null}  $probe
     */
    public function alreadyCompact(array $probe, string $extension): bool
    {
        $target = $this->bitrateBps();

        return $probe['codec'] === 'aac'
            && in_array(strtolower($extension), ['m4a', 'mp4', 'aac'], true)
            && $probe['channels'] === 1
            && $probe['bit_rate'] !== null && $probe['bit_rate'] <= $target + 8000;
    }

    /**
     * Re-encodes to mono AAC/m4a. Throws when ffmpeg fails.
     */
    public function toM4a(string $source, string $destination): void
    {
        $ffmpeg = $this->binaries->ffmpeg();

        if ($ffmpeg === null) {
            throw new RuntimeException('ffmpeg is not available.');
        }

        $result = Process::timeout((int) config('finisher.media.audio.timeout_seconds', 300))->run([
            $ffmpeg, '-hide_banner', '-y', '-i', $source,
            '-vn', '-map', '0:a:0',
            '-ac', '1',
            '-ar', (string) config('finisher.media.audio.sample_rate', 44100),
            '-c:a', 'aac', '-b:a', (int) floor($this->bitrateBps() / 1000).'k',
            '-movflags', '+faststart',
            '-f', 'ipod',
            $destination,
        ]);

        if (! $result->successful() || ! is_file($destination) || (int) filesize($destination) === 0) {
            throw new RuntimeException('ffmpeg audio transcode failed: '.mb_substr(trim($result->errorOutput()), -500));
        }
    }

    /** Clamped to the 64–96 kbps band (voice). */
    public function bitrateBps(): int
    {
        $kbps = (int) config('finisher.media.audio.bitrate_kbps', 80);

        return max(64, min(96, $kbps)) * 1000;
    }

    private function seconds(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /** "00:01:02.500000000" → 62.5 */
    private function tagSeconds(mixed $value): ?float
    {
        if (! is_string($value) || preg_match('/^(\d+):(\d{2}):(\d{2}(?:\.\d+)?)$/', trim($value), $m) !== 1) {
            return null;
        }

        $seconds = (int) $m[1] * 3600 + (int) $m[2] * 60 + (float) $m[3];

        return $seconds > 0 ? $seconds : null;
    }
}
