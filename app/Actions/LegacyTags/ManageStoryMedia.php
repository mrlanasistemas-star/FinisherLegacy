<?php

namespace App\Actions\LegacyTags;

use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Enums\MediaVisibility;
use App\Enums\StoryMediaRole;
use App\Enums\StoryQuotaKind;
use App\Exceptions\Media\PhotoQuotaExceededException;
use App\Exceptions\MediaTooLargeException;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\PhotoSale;
use App\Models\User;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use App\Services\Media\StoryMediaQuota;
use App\Services\Media\VideoTranscoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Throwable;

/**
 * Photos, videos, voice messages and the certificate of a Legacy story —
 * all through MediaStorage (private by default, MIME sniffed from the
 * bytes, size-capped, processed on the queue). The pivot
 * legacy_story_media keeps role, source, caption and order; the first
 * photo is the cover.
 *
 * Every kind with a limit (StoryMediaQuota: photos, videos, audio) is
 * gated twice — a cheap check before any bytes are stored and the
 * authoritative one under a lock on the story row.
 *
 * Purchased event photos (`source = purchased`): the story needs web
 * renditions, signed URLs and the story's own access rules, so each
 * imported sale gets its own MediaFile — but the bytes are NOT copied: the
 * pipeline reads the full-resolution original straight from the private
 * `event_photo_originals` disk, renders the display/card/thumb variants
 * into the story folder and keeps no second original. Removing the photo
 * from the story (or a refund, RemoveRefundedStoryPhotos) deletes only
 * those renditions; the purchased original is never touched.
 */
class ManageStoryMedia
{
    /**
     * Sniffed types. Browsers record voice as webm/ogg/mp4; iOS voice
     * memos are m4a, which fileinfo reports as audio/mp4, audio/x-m4a or
     * even video/mp4 (an audio-only MP4 container).
     */
    public const AUDIO_MIMES = [
        'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/x-aac',
        'audio/x-hx-aac-adts', 'audio/webm', 'audio/ogg', 'video/webm', 'video/mp4', 'application/ogg',
    ];

    public const CERTIFICATE_FILE_MIMES = ['application/pdf'];

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_PURCHASED = 'purchased';

    /** Private disk that holds the photographers' full-resolution originals. */
    public const PURCHASED_DISK = 'event_photo_originals';

    public function __construct(
        private readonly MediaStorage $storage,
        private readonly StoryMediaQuota $quota,
        private readonly VideoTranscoder $video,
    ) {}

    public static function rulesFor(StoryMediaRole $role): MediaUploadRules
    {
        return match ($role) {
            StoryMediaRole::Photo => MediaUploadRules::images((int) config('legacy.stories.max_photo_bytes')),
            StoryMediaRole::Video => new MediaUploadRules(
                [MediaKind::Video->value => MediaUploadRules::VIDEO_MIMES],
                [MediaKind::Video->value => (int) config('legacy.stories.max_video_bytes')],
            ),
            StoryMediaRole::Audio => new MediaUploadRules(
                [MediaKind::Audio->value => self::AUDIO_MIMES],
                [MediaKind::Audio->value => (int) config('legacy.stories.max_audio_bytes')],
            ),
            StoryMediaRole::Certificate => new MediaUploadRules(
                [MediaKind::Image->value => MediaUploadRules::IMAGE_MIMES, MediaKind::File->value => self::CERTIFICATE_FILE_MIMES],
                [
                    MediaKind::Image->value => (int) config('legacy.stories.max_certificate_bytes'),
                    MediaKind::File->value => (int) config('legacy.stories.max_certificate_bytes'),
                ],
            ),
        };
    }

    /**
     * Atomic: the media_files row and the pivot are written in ONE
     * transaction, under a lock on the story row, so two concurrent
     * uploads at the last free slot serialize and only one is attached.
     * On any rollback the temp upload is removed too: no orphan row, no
     * orphan file. A temp-disk write failure (media_tmp throws) becomes a
     * friendly `file` error instead of a 500.
     *
     * `$clientSeconds` is the duration the client measured (optional): a
     * cheap early refusal only — the real duration is always measured
     * from the bytes (here when ffprobe exists, and again in the pipeline).
     *
     * @throws ValidationException
     * @throws PhotoQuotaExceededException
     */
    public function add(LegacyStory $story, User $user, UploadedFile $upload, StoryMediaRole $role, ?string $caption = null, ?int $clientSeconds = null): MediaFile
    {
        $kind = StoryQuotaKind::forRole($role);

        if ($kind !== null) {
            $this->gate($story, $kind);
            $this->assertDuration($story, $kind, $clientSeconds);
        }

        /** @var MediaFile|null $file */
        $file = null;

        try {
            DB::transaction(function () use ($story, $user, $upload, $role, $kind, $caption, &$file) {
                $locked = LegacyStory::query()->whereKey($story->id)->lockForUpdate()->firstOrFail();
                $replace = $kind !== null ? $this->gate($locked, $kind) : true;

                $file = $this->accept($user, $upload, $role);

                if ($role === StoryMediaRole::Video) {
                    $this->assertVideoFits($locked, $file);
                }

                if ($replace) {
                    $this->detachRole($story, $role);
                }

                $story->media()->attach($file->id, [
                    'role' => $role->value,
                    'source' => self::SOURCE_UPLOAD,
                    'caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null,
                    'sort_order' => in_array($role, [StoryMediaRole::Photo, StoryMediaRole::Video], true) ? $this->nextOrder($story) : 0,
                ]);
            });
        } catch (Throwable $e) {
            if ($file !== null) {
                // The row rolled back with the transaction; drop the bytes too.
                $this->discardUpload($file);
            }

            throw $e;
        }

        $this->storage->process($file, $story);

        return $file;
    }

    /**
     * Adds event photos the owner bought on the site (PhotoSale rows that
     * still grant access — a refunded sale never does). They count against
     * the photo quota like any other photo; a sale already in the story is
     * skipped (idempotent). See the class comment for why this creates a
     * MediaFile without copying the original.
     *
     * @param  list<string>  $saleUuids
     * @return list<MediaFile> the files newly added (already-present sales are not repeated)
     *
     * @throws ValidationException
     * @throws PhotoQuotaExceededException
     */
    public function importPurchased(LegacyStory $story, User $user, array $saleUuids): array
    {
        $saleUuids = array_values(array_unique($saleUuids));

        $sales = PhotoSale::query()
            ->grantingAccess()
            ->where('buyer_user_id', $user->id)
            ->whereIn('uuid', $saleUuids)
            ->with('photo')
            ->get()
            ->sortBy(fn (PhotoSale $sale) => array_search($sale->uuid, $saleUuids, true))
            ->values();

        if ($sales->count() !== count($saleUuids) || $sales->contains(fn (PhotoSale $sale) => $sale->photo === null)) {
            throw ValidationException::withMessages(['sale_ids' => __('Alguna de esas fotos no está entre tus compras.')]);
        }

        $originals = Storage::disk(self::PURCHASED_DISK);

        foreach ($sales as $sale) {
            if (! $originals->exists((string) $sale->photo?->original_path)) {
                throw ValidationException::withMessages(['sale_ids' => __('No pudimos encontrar el archivo original de una de tus fotos. Escríbenos desde Contacto y lo resolvemos.')]);
            }
        }

        /** @var list<MediaFile> $files */
        $files = [];

        DB::transaction(function () use ($story, $user, $sales, $originals, &$files) {
            $locked = LegacyStory::query()->whereKey($story->id)->lockForUpdate()->firstOrFail();

            $present = DB::table('legacy_story_media')
                ->where('legacy_story_id', $locked->id)
                ->whereIn('photo_sale_id', $sales->pluck('id'))
                ->pluck('photo_sale_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $new = $sales->reject(fn (PhotoSale $sale) => in_array($sale->id, $present, true))->values();

            if ($new->isEmpty()) {
                return;
            }

            $this->quota->assertCanAdd($locked, StoryQuotaKind::Photos, $new->count());

            foreach ($new as $sale) {
                $photo = $sale->photo;
                $path = (string) $photo?->original_path;
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
                $size = (int) $originals->size($path);

                $file = MediaFile::create([
                    'uuid' => (string) Str::uuid(),
                    'owner_user_id' => $user->id,
                    'kind' => MediaKind::Image,
                    'status' => MediaFileStatus::Processing,
                    'visibility' => MediaVisibility::Private,
                    'disk' => $this->storage->mediaDisk(),
                    'directory' => $this->storage->paths()->forUser($user, 'legacy'),
                    'mime' => match ($extension) {
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        default => 'image/jpeg',
                    },
                    'extension' => preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? $extension : 'jpg',
                    'size_bytes' => $size,
                    // Identity of the purchased original (never re-hashed: it can be tens of MB on a NAS).
                    'checksum' => hash('sha256', 'event-photo:'.$photo?->uuid.':'.$size),
                    'width' => $photo?->width,
                    'height' => $photo?->height,
                    // Read in place by the pipeline; never copied, never deleted by it.
                    'source_disk' => self::PURCHASED_DISK,
                    'source_path' => $path,
                ]);

                $story->media()->attach($file->id, [
                    'role' => StoryMediaRole::Photo->value,
                    'source' => self::SOURCE_PURCHASED,
                    'photo_sale_id' => $sale->id,
                    'sort_order' => $this->nextOrder($story),
                ]);

                $files[] = $file;
            }
        });

        foreach ($files as $file) {
            $this->storage->process($file, $story);
        }

        return $files;
    }

    /**
     * A refunded sale no longer grants the photo: every story that
     * imported it loses that photo (its renditions are deleted; the
     * photographer's original stays where it is). Idempotent.
     *
     * @return int rows removed
     */
    public function removePurchasedSale(PhotoSale $sale): int
    {
        $rows = DB::table('legacy_story_media')->where('photo_sale_id', $sale->id)->get();

        foreach ($rows as $row) {
            $story = LegacyStory::withTrashed()->find((int) $row->legacy_story_id);

            if ($story !== null) {
                $this->deleteRow($story, (int) $row->id, (int) $row->media_file_id);
            } else {
                DB::table('legacy_story_media')->where('id', $row->id)->delete();
            }
        }

        return $rows->count();
    }

    /**
     * Whether the next item of this kind must REPLACE the current one
     * (true only for the single voice message while its limit is 1) —
     * throws when the story is full.
     *
     * @throws PhotoQuotaExceededException
     */
    private function gate(LegacyStory $story, StoryQuotaKind $kind): bool
    {
        if ($kind === StoryQuotaKind::Audio) {
            $limit = $this->quota->limitFor($story, $kind);
            $used = $this->quota->usedFor($story, $kind);

            // One voice message: a new recording replaces it, as it always did.
            if ($limit === 1 && $used >= 1) {
                return true;
            }
        }

        $this->quota->assertCanAdd($story, $kind);

        return false;
    }

    /**
     * @throws ValidationException
     */
    private function assertDuration(LegacyStory $story, StoryQuotaKind $kind, ?int $seconds): void
    {
        $max = $this->quota->maxSecondsFor($story, $kind);

        // One second of slack: recorders stop a hair late.
        if ($max !== null && $seconds !== null && $seconds > $max + 1) {
            throw ValidationException::withMessages(['file' => $kind === StoryQuotaKind::Videos
                ? __('El video dura más de lo permitido (:duration). Recórtalo y vuelve a subirlo.', ['duration' => $this->humanSeconds($max)])
                : __('El audio dura más de lo permitido (:duration). Graba uno más corto.', ['duration' => $this->humanSeconds($max)])]);
        }
    }

    /**
     * The real duration, measured from the parked bytes when ffprobe is
     * installed (the pipeline measures it again and fails the file when
     * this host could not).
     *
     * @throws ValidationException
     */
    private function assertVideoFits(LegacyStory $story, MediaFile $file): void
    {
        if ($file->kind !== MediaKind::Video || $file->source_disk === null || $file->source_path === null) {
            return;
        }

        $probe = $this->video->probe(Storage::disk($file->source_disk)->path($file->source_path));

        if ($probe['duration_ms'] === null) {
            return;
        }

        $this->assertDuration($story, StoryQuotaKind::Videos, (int) floor($probe['duration_ms'] / 1000));
        $file->forceFill(['duration_ms' => $probe['duration_ms'], 'width' => $probe['width'], 'height' => $probe['height']])->save();
    }

    private function humanSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return trans_choice(':count segundo|:count segundos', $seconds);
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $rest === 0
            ? trans_choice(':count minuto|:count minutos', $minutes)
            : __(':time min', ['time' => sprintf('%d:%02d', $minutes, $rest)]);
    }

    /**
     * @throws ValidationException
     */
    private function accept(User $user, UploadedFile $upload, StoryMediaRole $role): MediaFile
    {
        try {
            return $this->storage->accept($upload, $this->storage->paths()->forUser($user, 'legacy'), self::rulesFor($role), $user);
        } catch (MediaTooLargeException) {
            throw ValidationException::withMessages(['file' => __('El archivo es demasiado pesado. Prueba con uno más ligero.')]);
        } catch (FilesystemException) {
            throw ValidationException::withMessages(['file' => __('No pudimos guardar el archivo en este momento. Intenta de nuevo en unos minutos.')]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['file' => match ($role) {
                StoryMediaRole::Photo => __('Sube una foto JPG, PNG o WebP.'),
                StoryMediaRole::Video => __('Sube un video MP4, MOV o WebM.'),
                StoryMediaRole::Audio => __('Sube un audio M4A, MP3, AAC, WebM u OGG.'),
                StoryMediaRole::Certificate => __('Sube tu certificado en PDF, JPG, PNG o WebP.'),
            }.' '.collect($e->errors())->flatten()->first()]);
        }
    }

    private function discardUpload(MediaFile $file): void
    {
        try {
            // Only ever the parked temp upload — never a purchased original.
            if (is_string($file->source_path) && $file->source_path !== '' && $file->source_disk === $this->storage->tmpDisk()) {
                Storage::disk($this->storage->tmpDisk())->delete($file->source_path);
            }
            Storage::disk($this->storage->tmpDisk())->deleteDirectory($this->storage->workDirectory($file));
        } catch (Throwable) {
            // Best effort: the original error is what the user must see.
        }
    }

    public function caption(LegacyStory $story, int $pivotId, ?string $caption): void
    {
        DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->where('id', $pivotId)
            ->update(['caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null, 'updated_at' => now()]);
    }

    /**
     * New order of the gallery (pivot ids of photos and videos). Ids that
     * are not this story's are ignored — never trusted from the client.
     *
     * @param  array<int, int>  $pivotIds
     */
    public function reorder(LegacyStory $story, array $pivotIds): void
    {
        $owned = DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->whereIn('role', [StoryMediaRole::Photo->value, StoryMediaRole::Video->value, StoryMediaRole::Audio->value])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($pivotIds, $owned) {
            $position = 0;
            foreach ($pivotIds as $id) {
                if (in_array((int) $id, $owned, true)) {
                    DB::table('legacy_story_media')->where('id', (int) $id)->update(['sort_order' => $position++]);
                }
            }
        });
    }

    /**
     * Same as reorder(), by media uuid (what the API exposes).
     *
     * @param  list<string>  $uuids
     */
    public function reorderByUuids(LegacyStory $story, array $uuids): void
    {
        $ids = DB::table('legacy_story_media')
            ->join('media_files', 'media_files.id', '=', 'legacy_story_media.media_file_id')
            ->where('legacy_story_media.legacy_story_id', $story->id)
            ->whereIn('media_files.uuid', $uuids)
            ->pluck('legacy_story_media.id', 'media_files.uuid');

        $this->reorder($story, array_values(array_filter(array_map(
            fn (string $uuid) => $ids->has($uuid) ? (int) $ids->get($uuid) : null,
            $uuids,
        ))));
    }

    public function remove(LegacyStory $story, int $pivotId): bool
    {
        $row = DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->where('id', $pivotId)->first();

        if ($row === null) {
            return false;
        }

        $this->deleteRow($story, (int) $row->id, (int) $row->media_file_id);

        return true;
    }

    public function removeByUuid(LegacyStory $story, string $uuid): bool
    {
        $pivotId = DB::table('legacy_story_media')
            ->join('media_files', 'media_files.id', '=', 'legacy_story_media.media_file_id')
            ->where('legacy_story_media.legacy_story_id', $story->id)
            ->where('media_files.uuid', $uuid)
            ->value('legacy_story_media.id');

        return $pivotId !== null && $this->remove($story, (int) $pivotId);
    }

    private function detachRole(LegacyStory $story, StoryMediaRole $role): void
    {
        DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->where('role', $role->value)
            ->get()
            ->each(fn (object $row) => $this->deleteRow($story, (int) $row->id, (int) $row->media_file_id));
    }

    private function deleteRow(LegacyStory $story, int $pivotId, int $mediaFileId): void
    {
        DB::table('legacy_story_media')->where('id', $pivotId)->delete();

        $file = MediaFile::query()->find($mediaFileId);
        // Only files that belong to this story are physically removed
        // (MediaStorage::delete never touches a file's external source).
        if ($file !== null && $file->mediable_type === $story->getMorphClass() && (int) $file->mediable_id === $story->id) {
            DB::afterCommit(fn () => $this->storage->delete($file));
        }
    }

    private function nextOrder(LegacyStory $story): int
    {
        return (int) DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->max('sort_order') + 1;
    }
}
