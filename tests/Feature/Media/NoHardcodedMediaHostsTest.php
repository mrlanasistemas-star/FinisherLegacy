<?php

use Illuminate\Support\Facades\File;

/**
 * Spec §60/§120: the NAS is reached only through env-configured disks —
 * never a LAN IP, a Tailscale (100.64.0.0/10) address or a /volume path
 * in config or application code.
 */
test('config and app code contain no NAS hosts or Synology volume paths', function () {
    $offenders = [];

    foreach ([config_path(), app_path(), base_path('routes')] as $directory) {
        foreach (File::allFiles($directory) as $file) {
            // Code only — docblocks may legitimately say what NOT to do.
            $contents = collect(token_get_all($file->getContents()))
                ->reject(fn ($token) => is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true))
                ->map(fn ($token) => is_array($token) ? $token[1] : $token)
                ->implode('');

            if (preg_match('/\b192\.168\.\d{1,3}\.\d{1,3}\b|\b100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.\d{1,3}\.\d{1,3}\b|\/volume\d+\//', $contents) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('the synology_media disk is driven purely by env', function () {
    $disk = config('filesystems.disks.synology_media');

    expect($disk['driver'])->toBeIn(['local', 'sftp'])
        ->and($disk)->not->toHaveKey('url');
});
