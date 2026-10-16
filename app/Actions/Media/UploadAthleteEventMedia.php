<?php

namespace App\Actions\Media;

use App\Enums\AthleteEventMediaType;
use App\Exceptions\MediaLimitReachedException;
use App\Exceptions\MediaTooLargeException;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use App\Services\Media\ResolveMediaEntitlement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Belongs to Athlete + EventParticipant, never generically to User (brief
 * §41/§94-§101). MIME is checked from the file's real content
 * (UploadedFile::getMimeType() uses fileinfo), never trusted from the
 * client-supplied extension (brief §98).
 *
 * Returns immediately: the upload is parked on the temp disk and the media
 * pipeline (App\Services\Media\MediaStorage) renders variants / transcodes
 * and moves it to `athletes/{athlete}/events/{event}/photos|videos/` on
 * the media disk in the queue. `processingStatus()` tells clients when.
 */
class UploadAthleteEventMedia
{
    private const IMAGE_MIMES = MediaUploadRules::IMAGE_MIMES;

    public const IMAGE_MIME_TYPES = self::IMAGE_MIMES;

    // video/quicktime = iOS's native .mov recording — transcoded to MP4
    // by the pipeline when ffmpeg is available.
    private const VIDEO_MIMES = MediaUploadRules::VIDEO_MIMES;

    public const VIDEO_MIME_TYPES = self::VIDEO_MIMES;

    public function __construct(
        private readonly ResolveMediaEntitlement $entitlement,
        private readonly MediaStorage $storage,
    ) {}

    public function handle(Athlete $athlete, EventParticipant $participant, UploadedFile $file, bool $isPublic = false): AthleteEventMedia
    {
        $mime = (string) $file->getMimeType();
        $type = $this->resolveType($mime);

        $maxImageBytes = (int) config('finisher.event_media.max_image_bytes');
        $maxVideoBytes = (int) config('finisher.event_media.max_video_bytes');

        if ($file->getSize() > ($type === AthleteEventMediaType::Image ? $maxImageBytes : $maxVideoBytes)) {
            throw new MediaTooLargeException;
        }

        $remaining = $this->entitlement->remaining($participant);

        if ($type === AthleteEventMediaType::Image && $remaining['images'] <= 0) {
            throw new MediaLimitReachedException('Alcanzaste el límite de fotos incluidas para este evento.');
        }

        if ($type === AthleteEventMediaType::Video && $remaining['videos'] <= 0) {
            throw new MediaLimitReachedException('Alcanzaste el límite de videos incluidos para este evento.');
        }

        $edition = $participant->eventEdition()->first();
        if ($edition === null) {
            throw ValidationException::withMessages(['file' => 'Este registro no pertenece a un evento válido.']);
        }

        $directory = $this->storage->paths()->athleteEvent(
            $athlete,
            $edition,
            $type === AthleteEventMediaType::Image ? 'photos' : 'videos',
        );

        $mediaFile = $this->storage->accept(
            $file,
            $directory,
            MediaUploadRules::imagesAndVideos($maxImageBytes, $maxVideoBytes),
            $athlete->user()->first(),
        );

        try {
            $media = DB::transaction(function () use ($athlete, $participant, $type, $mediaFile, $isPublic) {
                $nextSort = (int) (AthleteEventMedia::query()->where('event_participant_id', $participant->id)->max('sort_order')) + 1;

                return AthleteEventMedia::create([
                    'uuid' => (string) Str::uuid(),
                    'athlete_id' => $athlete->id,
                    'event_participant_id' => $participant->id,
                    'type' => $type,
                    'disk' => $mediaFile->disk,
                    // Placeholder until the pipeline syncs the real primary path.
                    'path' => $mediaFile->directory.'/'.$mediaFile->uuid,
                    'mime' => $mediaFile->mime,
                    'size_bytes' => $mediaFile->size_bytes,
                    'checksum' => $mediaFile->checksum,
                    'width' => $mediaFile->width,
                    'height' => $mediaFile->height,
                    'is_public' => $isPublic,
                    'sort_order' => $nextSort,
                ]);
            });
        } catch (Throwable $e) {
            $this->storage->delete($mediaFile);

            throw $e;
        }

        $this->storage->process($mediaFile, $media);

        return $media->fresh() ?? $media;
    }

    private function resolveType(string $mime): AthleteEventMediaType
    {
        return match (true) {
            in_array($mime, self::IMAGE_MIMES, true) => AthleteEventMediaType::Image,
            in_array($mime, self::VIDEO_MIMES, true) => AthleteEventMediaType::Video,
            default => throw ValidationException::withMessages(['file' => "Formato de archivo no soportado: {$mime}."]),
        };
    }
}
