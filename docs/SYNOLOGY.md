# Media storage on Synology (over Tailscale)

Spec §60–§68, §117/§120 (security), §121 (queues), §123/§129 (tests).

Heavy media (event photos/videos, moment photos, later product media and
posts) lives on a Synology NAS. Laravel never knows where the NAS is: it
only sees a filesystem disk (`synology_media`) whose root comes from env.
No IP, no Tailscale address, no `/volume1/...` path is ever committed —
`tests/Feature/Media/NoHardcodedMediaHostsTest.php` enforces it.

```
 browser / app ──HTTPS──▶ VPS (nginx + PHP-FPM + queue worker)
                              │  signed /media/f/{uuid}/{variant}
                              │  (MediaFileController, policy + signature)
                              ▼
                     /mnt/finisher-media  (OS-level NFS/SMB mount)
                              │
                         Tailscale (WireGuard, private tailnet)
                              ▼
                     Synology shared folder (never exposed publicly)
```

## 1. Network: Tailscale between VPS and NAS

1. Install Tailscale on the VPS (`curl -fsSL https://tailscale.com/install.sh | sh`,
   `sudo tailscale up --ssh=false`) and the Tailscale package on the Synology
   (Package Center → Tailscale). Log both into the same tailnet.
2. Use MagicDNS names (e.g. `<nas-hostname>.<tailnet>.ts.net`) in the mount
   configuration below — never a raw address in the repo.
3. Tailscale ACLs: allow **only** `tag:finisher-vps → tag:finisher-nas` on the
   NFS (2049) or SMB (445) port. Nothing else reaches the NAS.
4. On the Synology: disable QuickConnect / port forwarding / DDNS for this
   share. The NAS is never reachable from the internet (spec §120).

## 2. Mount the share on the VPS (OS level)

Create a dedicated shared folder (e.g. `finisher-media`) on the Synology with
a dedicated user. Then on the VPS, pick ONE of:

### NFS (recommended on Linux)

Synology: Control Panel → File Services → enable NFS (v4.1); shared folder →
NFS Permissions → hostname = the VPS tailnet name, privilege read/write,
squash "Map all users to admin" **off**, "Map root to guest" or a mapped UID
that matches the PHP user.

```bash
sudo apt install nfs-common
sudo mkdir -p /mnt/finisher-media
# /etc/fstab
<nas-hostname>.<tailnet>.ts.net:/<volume>/finisher-media  /mnt/finisher-media  nfs4  rw,hard,noatime,_netdev,x-systemd.automount,x-systemd.after=tailscaled.service  0 0
sudo mount -a
```

### SMB/CIFS

```bash
sudo apt install cifs-utils
# /root/.finisher-smb (chmod 600) — credentials live ONLY on the server
username=<nas-user>
password=<secret>
# /etc/fstab
//<nas-hostname>.<tailnet>.ts.net/finisher-media  /mnt/finisher-media  cifs  credentials=/root/.finisher-smb,uid=www-data,gid=www-data,file_mode=0640,dir_mode=0750,vers=3.1.1,_netdev,x-systemd.automount,x-systemd.after=tailscaled.service  0 0
```

### SFTP (only if a mount is impossible)

`FINISHER_MEDIA_DRIVER=sftp` switches the disk to Flysystem SFTP. It needs
`composer require league/flysystem-sftp-v3` (not installed by default) and the
`FINISHER_MEDIA_SFTP_*` env vars (host = tailnet name, key path outside the
repo). Prefer the mount: local reads give `BinaryFileResponse` + kernel
sendfile and cheap Range requests; SFTP streams through PHP.

### Permissions

- The PHP-FPM user and the queue worker user must be the same (or share a
  group) and own `/mnt/finisher-media`.
- The disk writes files `0640` and directories `0750` (`private` visibility).
- `x-systemd.automount` + `_netdev` avoid boot hangs if the NAS is down; uploads
  keep working meanwhile (they wait on the temp disk) and the transfer job
  retries with backoff (30 s → 2 min → 10 min).

## 3. Environment variables

| Var | Default | Purpose |
| --- | --- | --- |
| `FINISHER_MEDIA_ROOT` | empty | Mount path (e.g. `/mnt/finisher-media`). When set, `finisher.media_disk` becomes `synology_media`. |
| `FINISHER_MEDIA_DRIVER` | `local` | `local` (mount) or `sftp`. |
| `FINISHER_MEDIA_STORAGE_DISK` | auto | Force the media disk name (`synology_media`, `local`, `s3`…). (Not `FINISHER_MEDIA_DISK`: that older var is the legacy `athlete_media` setting.) |
| `FINISHER_MEDIA_TMP_ROOT` | `storage/app/private/media-tmp` | Local temp disk for uploads in flight. Must be local to the web host AND the media worker. |
| `FINISHER_MEDIA_QUEUE` | `default` | Queue name for the pipeline. Recommended: `media`, with a dedicated worker. |
| `FINISHER_MEDIA_QUEUE_CONNECTION` | app default | Optional connection override. |
| `FFMPEG_BINARY` / `FFPROBE_BINARY` | empty | Absolute paths. Without ffmpeg, videos are kept as uploaded (`pending_transcode`). `FFPROBE_BINARY` defaults to the `ffprobe` next to ffmpeg. |
| `FINISHER_MEDIA_KEEP_IMAGE_ORIGINAL` | `true` | Keep the original photo (owner-only, it carries EXIF/GPS). |
| `FINISHER_MEDIA_KEEP_VIDEO_ORIGINAL` | `false` | Keep the uploaded video next to the MP4. |
| `FINISHER_MEDIA_AVIF` | `false` | Also render AVIF when the GD/Imagick build supports it (slow to encode). |
| `FINISHER_MEDIA_WEBM` | `false` | Also render VP9 WebM. |
| `FINISHER_MEDIA_MAX_*` | see `config/finisher.php` | Image/video/file byte limits, max pixels, max video seconds. |
| `FINISHER_MEDIA_PUBLIC_URL_TTL` / `..._PRIVATE_URL_TTL` | 7 days / 120 min | Signed URL lifetimes. |
| `FINISHER_MEDIA_CDN_URL` | empty | Future CDN in front of the signed routes (public media only). Unused by default. |

Queue worker for media (Horizon/Supervisor — owned by the platform config):

```bash
php artisan queue:work --queue=media --timeout=1920 --tries=4
```

The transcode job's timeout is `FINISHER_MEDIA_TRANSCODE_TIMEOUT` (1800 s) +
120 s, so the connection's `retry_after` must be larger than that (e.g. 2000).

## 4. Folder convention (`App\Services\Media\MediaPathResolver`)

```
athletes/{athlete_uuid}--{slug-del-nombre}/
    profile/  legacy/  gear/  posts/  support/
    events/{event_uuid}/photos|videos|tracking|support/
products/{product_uuid}/images|videos|models3d/
users/{user_id}/posts/      # only for a user that has no Athlete yet (internal)
```

- The DB stores **relative** paths only (`media_files.directory/path/variants`).
- The **uuid is the identity**; the slug (ASCII, ≤ 40 chars, never empty) is
  only for humans browsing the NAS.
- **Renaming an athlete does not move files.** The resolver first looks up an
  existing `athletes/{uuid}--*` folder in `media_files.directory` and keeps
  using it, even if the slug is now stale. A new slug only appears for an
  athlete with no stored media yet. (Renaming the folder by hand on the NAS
  would break the stored paths — don't.)
- `event_uuid` is `event_editions.uuid` (added by an additive migration and
  backfilled; assigned lazily for rows created without one).
- Each file: `{file_uuid}_original.{ext}`, `{file_uuid}_thumb.webp`,
  `_card.webp`, `_display.webp` (+ `.avif`), `_video.mp4`, `_poster.jpg`,
  `_poster_thumb.webp`, `_poster_card.webp`.

## 5. Upload pipeline (`App\Services\Media\MediaStorage`)

1. **Request** (fast): sniff the real MIME (fileinfo), check size and image
   pixel dimensions (decompression-bomb guard), copy to the temp disk
   (`incoming/{file_uuid}/source.ext`), create a `media_files` row with
   `status=processing`. Returns immediately.
2. **Queue chain** (dispatched after the DB commit):
   `ProcessMediaFile` (variants / ffmpeg into the temp work dir) →
   `TransferMediaFile` (streams to the media disk, verifies sizes) →
   `FinalizeMediaFile` (status, owner row sync, temp cleanup).
3. Status: `processing` → `ready` | `pending_transcode` (video without ffmpeg,
   original served as-is) | `failed`.

### Failures and retries

- A failed step sets `status=failed`, `error`, `failed_at`, and logs
  `media.pipeline_failed` with `media_uuid`, `step`, `kind`, `disk`,
  `directory`, `attempts`, exception. The temp source is **kept**.
- `php artisan media:retry-failed [--uuid=…] [--limit=100] [--dry-run]`
  re-queues failed files whose source still exists.
- `php artisan media:retry-failed --pending-transcode` also transcodes the
  videos stored before ffmpeg was installed (and drops their original unless
  `FINISHER_MEDIA_KEEP_VIDEO_ORIGINAL=true`).
- A missing ffmpeg is logged as `media.ffmpeg_missing` (warning), not a failure.

### API for other domains (product media, community posts…)

```php
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use App\Enums\MediaVisibility;

$storage = app(MediaStorage::class);
$paths = $storage->paths(); // MediaPathResolver

// One call (validate + temp + queue). $for = the owning row (optional).
$file = $storage->store(
    $request->file('image'),
    $paths->product($product, 'images'),            // or athleteSection($athlete, 'posts') …
    MediaUploadRules::images(visibility: MediaVisibility::Public),
    owner: $request->user(),
    for: $productMedia,
);

// Two steps, when the owning row is created after validation:
$file = $storage->accept($upload, $directory, MediaUploadRules::imagesAndVideos(), $user);
$row  = DB::transaction(fn () => ProductMedia::create([...]));
$storage->process($file, $row);

$file->status;            // MediaFileStatus
$file->url();             // primary (display image / MP4 / original) — null until servable
$file->url('thumb');      // thumb | card | display | *_avif | poster | poster_thumb | poster_card | video_webm
$file->urls();            // the useful set for a client
$storage->delete($file);  // every rendition + temp + row
```

Rules: `MediaUploadRules::images()`, `::imagesAndVideos()`, `::files(['model/gltf-binary'])`
(stored as-is, e.g. 3D models). Limits default to `config('finisher.media.*')`.

The owning model implements `App\Contracts\Media\OwnsMediaFiles`:

- `canViewMedia(?User, MediaFile)` — who may see it (owner, visibility,
  **purchase entitlement** for paid photos…);
- `mediaIsPublic(MediaFile)` — long-lived, cache-friendly URLs if true;
- `syncFromMediaFile(MediaFile)` — copy width/height/duration/path when ready.

and exposes `morphOne(MediaFile::class, 'mediable')->chaperone('mediable')`
(plus `protected $with = ['mediaFile']` to avoid N+1 in lists).

Integrated today: `AthleteEventMedia` (event photos/videos, web + API
upload) and `LegacyMomentMedia` (photos uploaded with a Moment). Not
migrated (on purpose): `ProductMedia` (A2 can switch using the API above),
avatars/covers and medal images (still `ImageProcessingService` on the
`public` disk — many URL consumers), support audio (signed
`SupportAudioController`), and the photographer marketplace
(`EventPhoto`: private originals on `event_photo_originals`, delivered only to
the buyer by `PhotosController@download`; watermarked previews public).
Older `AthleteEventMedia` rows without a `MediaFile` keep being served by
`AthleteEventMediaFileController` (now with Range support).

## 6. Delivery (`GET /media/f/{uuid}/{variant}`, route `media.file`)

- Access = a valid **temporary signed URL** (relative signature, so it also
  works through a CDN host) **or** `MediaFilePolicy@view` for the session user
  (uploader, `media.manage`, or the owning row's `canViewMedia`).
- Public media: 7-day URLs with the expiry rounded to the day (same URL all
  day → browser/CDN cache). `Cache-Control: public, max-age=604800, immutable`.
  Making the media private revokes those URLs immediately (`scope=public`).
- Private media: ~2 h URLs, `Cache-Control: private, max-age=1800`.
- `original` (EXIF/GPS) is uploader/staff-only, never by signature alone.
- While processing, only the uploader gets a `no-store` preview from the temp
  disk; everyone else gets 404.
- Range requests / 206 (video seeking), ETag/304, `nosniff`. The response
  never contains a disk path. Throttled `600/min` per client.

## 7. Backups

- The NAS share is the source of truth for media; the DB holds metadata.
  Back up both **together** (same night): Hyper Backup of the shared folder
  to a second location (C2 / another NAS / USB rotation) + the MySQL dump.
- Enable Btrfs snapshots on the shared folder (hourly, 7-day retention) —
  protects against accidental deletes and ransomware on the VPS side.
- Restore check (quarterly): restore a random `athletes/<uuid>--*/` folder and
  compare against `media_files.checksum` (sha256 of the uploaded original).
- The temp disk is disposable; files there are either in flight or `failed`
  (retryable). Do not back it up.

## 8. Testing without a NAS

- Tests use `Storage::fake('synology_media')` / `Storage::fake('media_tmp')`:
  same relative path semantics as the mount (`tests/Feature/Media/*`).
- Dev without a NAS: leave `FINISHER_MEDIA_ROOT` empty → the media disk is the
  private `local` disk (`storage/app/private`). With `QUEUE_CONNECTION=sync`
  the pipeline runs inline.
- Simulate the mount locally: `FINISHER_MEDIA_ROOT=/absolute/path/to/fake-nas`
  (any local folder) — the code path is identical to production.
- No ffmpeg locally? Uploading a video yields `pending_transcode` and the
  original plays. Tests never require ffmpeg (they fake `Process`).
