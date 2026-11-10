<?php

namespace App\Services\Media;

/**
 * Locates the ffmpeg / ffprobe binaries used by the media pipeline
 * (video transcode, real audio duration, AAC/m4a transcode).
 *
 * Resolution order, per binary:
 *   1. config (`finisher.media.video.ffmpeg|ffprobe`, from FFMPEG_BINARY /
 *      FFPROBE_BINARY in .env);
 *   2. the raw process environment (getenv) — survives a stale config cache;
 *   3. ffprobe only: next to a configured ffmpeg, when that file exists;
 *   4. a PATH lookup (`/usr/bin/ffmpeg` after `sudo apt install ffmpeg`),
 *      unless `finisher.media.binaries.path_lookup` is false (the test
 *      suite disables it so results do not depend on the developer box);
 *   5. ffprobe only: the unverified sibling name of a configured ffmpeg.
 *
 * Only filesystem checks — no process is spawned here.
 */
class MediaBinaries
{
    /** @var array<string, array{path: string|null, source: string|null}> */
    private array $resolved = [];

    public function ffmpeg(): ?string
    {
        return $this->resolve('ffmpeg')['path'];
    }

    public function ffprobe(): ?string
    {
        return $this->resolve('ffprobe')['path'];
    }

    /**
     * For `storage:health` and logs.
     *
     * @return array{ffmpeg: array{path: string|null, source: string|null}, ffprobe: array{path: string|null, source: string|null}}
     */
    public function status(): array
    {
        return ['ffmpeg' => $this->resolve('ffmpeg'), 'ffprobe' => $this->resolve('ffprobe')];
    }

    /**
     * @param  'ffmpeg'|'ffprobe'  $name
     * @return array{path: string|null, source: string|null}
     */
    private function resolve(string $name): array
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $configured = config("finisher.media.video.{$name}");
        if (is_string($configured) && trim($configured) !== '') {
            return $this->resolved[$name] = ['path' => trim($configured), 'source' => 'config'];
        }

        $env = getenv(strtoupper($name).'_BINARY');
        if (is_string($env) && trim($env) !== '') {
            return $this->resolved[$name] = ['path' => trim($env), 'source' => 'env'];
        }

        $sibling = null;
        if ($name === 'ffprobe') {
            // Usually installed next to a configured ffmpeg.
            $ffmpeg = $this->resolve('ffmpeg');
            if ($ffmpeg['path'] !== null && $ffmpeg['source'] !== 'path') {
                $guess = preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', $ffmpeg['path']);
                if (is_string($guess) && $guess !== $ffmpeg['path']) {
                    $sibling = ['path' => $guess, 'source' => $ffmpeg['source']];
                    if (is_file($guess)) {
                        return $this->resolved[$name] = $sibling;
                    }
                }
            }
        }

        if ((bool) config('finisher.media.binaries.path_lookup', true)) {
            $found = $this->findOnPath($name);
            if ($found !== null) {
                return $this->resolved[$name] = ['path' => $found, 'source' => 'path'];
            }
        }

        // Unverified sibling (e.g. a bare "ffmpeg" command): best guess.
        return $this->resolved[$name] = $sibling ?? ['path' => null, 'source' => null];
    }

    private function findOnPath(string $name): ?string
    {
        $path = getenv('PATH');
        if (! is_string($path) || $path === '') {
            return null;
        }

        $windows = DIRECTORY_SEPARATOR === '\\';
        $candidates = $windows ? [$name.'.exe'] : [$name];

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $directory = trim($directory, " \t\"");
            if ($directory === '') {
                continue;
            }

            foreach ($candidates as $candidate) {
                $full = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$candidate;
                // Windows has no exec bit: the .exe name is the signal.
                if (@is_file($full) && ($windows || @is_executable($full))) {
                    return $full;
                }
            }
        }

        return null;
    }
}
