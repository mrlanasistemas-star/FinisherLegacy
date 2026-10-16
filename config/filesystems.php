<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Finisher Legacy media disks (product consolidation brief §7-§9/§61)
        |----------------------------------------------------------------------
        |
        | One named disk per media concern — the domain code only ever calls
        | Storage::disk('athlete_media')/('product_media')/('support_audio'),
        | never a raw path or NAS hostname. Today these point at local/test
        | storage; production repoints ATHLETE_MEDIA_DISK_ROOT etc. to
        | wherever the Synology (or S3/R2 later) is mounted — no domain code
        | changes either way (brief §61: "Laravel NO debe conocer /volume1/...").
        |
        */

        // No 'url'/'visibility': every event photo/video — public or not —
        // is served through App\Http\Controllers\AthleteEventMediaFileController,
        // never a direct disk URL (brief §62). That's what lets the disk
        // become a private NAS mount later with zero app code changes.
        'athlete_media' => [
            'driver' => env('ATHLETE_MEDIA_DISK_DRIVER', 'local'),
            'root' => env('ATHLETE_MEDIA_DISK_ROOT', storage_path('app/private/athlete-media')),
            'throw' => false,
            'report' => false,
        ],

        'product_media' => [
            'driver' => env('PRODUCT_MEDIA_DISK_DRIVER', 'local'),
            'root' => env('PRODUCT_MEDIA_DISK_ROOT', storage_path('app/public/product-media')),
            'url' => env('PRODUCT_MEDIA_DISK_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage/product-media'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Photographer marketplace: full-resolution originals are PRIVATE
        // (only a paid buyer downloads them, through PhotoDownloadController);
        // watermarked previews/thumbnails are public.
        'event_photo_originals' => [
            'driver' => env('EVENT_PHOTO_ORIGINALS_DISK_DRIVER', 'local'),
            'root' => env('EVENT_PHOTO_ORIGINALS_DISK_ROOT', storage_path('app/private/event-photos')),
            'throw' => false,
            'report' => false,
        ],

        // No 'url'/'visibility': support audio is never linked to directly —
        // only App\Http\Controllers\SupportAudioController serves it, behind
        // a temporary signed URL (brief §62: "no URLs directas eternas").
        'support_audio' => [
            'driver' => env('SUPPORT_AUDIO_DISK_DRIVER', 'local'),
            'root' => env('SUPPORT_AUDIO_DISK_ROOT', storage_path('app/private/support-audio')),
            'throw' => false,
            'report' => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Synology media (spec §60-§68, docs/SYNOLOGY.md)
        |----------------------------------------------------------------------
        |
        | Configured ONLY by env — never a NAS IP, Tailscale address or
        | /volume1 path in the repo. Default (and recommended) driver is
        | `local` on FINISHER_MEDIA_ROOT, an OS-level NFS/SMB mount of the
        | Synology share made over Tailscale. FINISHER_MEDIA_DRIVER=sftp
        | is supported only once `league/flysystem-sftp-v3` is installed.
        | No 'url': files are only ever served through the signed
        | App\Http\Controllers\MediaFileController (the NAS is never public).
        |
        */
        'synology_media' => env('FINISHER_MEDIA_DRIVER', 'local') === 'sftp' ? [
            'driver' => 'sftp',
            'host' => env('FINISHER_MEDIA_SFTP_HOST'),
            'port' => (int) env('FINISHER_MEDIA_SFTP_PORT', 22),
            'username' => env('FINISHER_MEDIA_SFTP_USERNAME'),
            'privateKey' => env('FINISHER_MEDIA_SFTP_PRIVATE_KEY'),
            'passphrase' => env('FINISHER_MEDIA_SFTP_PASSPHRASE'),
            'root' => env('FINISHER_MEDIA_ROOT', ''),
            'timeout' => (int) env('FINISHER_MEDIA_SFTP_TIMEOUT', 30),
            'visibility' => 'private',
            'directory_visibility' => 'private',
            'throw' => true,
            'report' => true,
        ] : [
            'driver' => 'local',
            'root' => env('FINISHER_MEDIA_ROOT', storage_path('app/private/synology-media')),
            'visibility' => 'private',
            'directory_visibility' => 'private',
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0640],
                'dir' => ['public' => 0755, 'private' => 0750],
            ],
            'throw' => true,
            'report' => true,
        ],

        // Where an upload waits (and its variants are rendered) before the
        // queue moves it to the media disk. Must be LOCAL to the web host
        // and the media queue worker — see docs/SYNOLOGY.md §Colas.
        'media_tmp' => [
            'driver' => 'local',
            'root' => env('FINISHER_MEDIA_TMP_ROOT', storage_path('app/private/media-tmp')),
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
