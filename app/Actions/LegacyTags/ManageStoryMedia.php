<?php

namespace App\Actions\LegacyTags;

use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Exceptions\MediaTooLargeException;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Photos, the voice message and the certificate of a Legacy story — all
 * through MediaStorage (private by default, MIME sniffed from the bytes,
 * size-capped, processed on the queue). The pivot legacy_story_media
 * keeps role, caption and order; the first photo is the cover.
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

    public function __construct(private readonly MediaStorage $storage) {}

    public static function rulesFor(StoryMediaRole $role): MediaUploadRules
    {
        return match ($role) {
            StoryMediaRole::Photo => MediaUploadRules::images((int) config('legacy.stories.max_photo_bytes')),
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
     * @throws ValidationException
     */
    public function add(LegacyStory $story, User $user, UploadedFile $upload, StoryMediaRole $role, ?string $caption = null): MediaFile
    {
        if ($role === StoryMediaRole::Photo && $this->count($story, $role) >= (int) config('legacy.stories.max_photos', 12)) {
            throw ValidationException::withMessages(['file' => 'Ya tienes el máximo de fotos en este recuerdo. Quita una para subir otra.']);
        }

        try {
            $file = $this->storage->accept($upload, $this->storage->paths()->forUser($user, 'legacy'), self::rulesFor($role), $user);
        } catch (MediaTooLargeException) {
            throw ValidationException::withMessages(['file' => 'El archivo es demasiado pesado. Prueba con uno más ligero.']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['file' => match ($role) {
                StoryMediaRole::Photo => 'Sube una foto JPG, PNG o WebP.',
                StoryMediaRole::Audio => 'Sube un audio M4A, MP3, AAC, WebM u OGG.',
                StoryMediaRole::Certificate => 'Sube tu certificado en PDF, JPG, PNG o WebP.',
            }.' '.collect($e->errors())->flatten()->first()]);
        }

        DB::transaction(function () use ($story, $file, $role, $caption) {
            if ($role->isSingle()) {
                $this->detachRole($story, $role);
            }

            $story->media()->attach($file->id, [
                'role' => $role->value,
                'caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null,
                'sort_order' => $role === StoryMediaRole::Photo ? $this->nextOrder($story) : 0,
            ]);
        });

        $this->storage->process($file, $story);

        return $file;
    }

    public function caption(LegacyStory $story, int $pivotId, ?string $caption): void
    {
        DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->where('id', $pivotId)
            ->update(['caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null, 'updated_at' => now()]);
    }

    /**
     * New photo order (pivot ids). Ids that are not this story's photos
     * are ignored — never trusted from the client.
     *
     * @param  array<int, int>  $pivotIds
     */
    public function reorder(LegacyStory $story, array $pivotIds): void
    {
        $owned = DB::table('legacy_story_media')
            ->where('legacy_story_id', $story->id)
            ->where('role', StoryMediaRole::Photo->value)
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

    public function remove(LegacyStory $story, int $pivotId): bool
    {
        $row = DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->where('id', $pivotId)->first();

        if ($row === null) {
            return false;
        }

        $this->deleteRow($story, (int) $row->id, (int) $row->media_file_id);

        return true;
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
        // Only files that belong to this story are physically removed.
        if ($file !== null && $file->mediable_type === $story->getMorphClass() && (int) $file->mediable_id === $story->id) {
            DB::afterCommit(fn () => $this->storage->delete($file));
        }
    }

    private function count(LegacyStory $story, StoryMediaRole $role): int
    {
        return DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->where('role', $role->value)->count();
    }

    private function nextOrder(LegacyStory $story): int
    {
        return (int) DB::table('legacy_story_media')->where('legacy_story_id', $story->id)->max('sort_order') + 1;
    }
}
