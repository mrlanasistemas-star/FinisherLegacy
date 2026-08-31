<?php

namespace App\Actions\Media;

use App\Enums\AthleteEventMediaType;
use App\Exceptions\MediaLimitReachedException;
use App\Exceptions\MediaTooLargeException;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use App\Services\Media\ResolveMediaEntitlement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Belongs to Athlete + EventParticipant, never generically to User (brief
 * §41/§94-§101). MIME is checked from the file's real content
 * (UploadedFile::getMimeType() uses fileinfo), never trusted from the
 * client-supplied extension (brief §98).
 */
class UploadAthleteEventMedia
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm'];

    public function __construct(private readonly ResolveMediaEntitlement $entitlement) {}

    public function handle(Athlete $athlete, EventParticipant $participant, UploadedFile $file, bool $isPublic = false): AthleteEventMedia
    {
        $mime = (string) $file->getMimeType();
        $type = $this->resolveType($mime);

        $maxBytes = $type === AthleteEventMediaType::Image
            ? (int) config('finisher.event_media.max_image_bytes')
            : (int) config('finisher.event_media.max_video_bytes');

        if ($file->getSize() > $maxBytes) {
            throw new MediaTooLargeException;
        }

        $remaining = $this->entitlement->remaining($participant);

        if ($type === AthleteEventMediaType::Image && $remaining['images'] <= 0) {
            throw new MediaLimitReachedException('Alcanzaste el límite de fotos incluidas para este evento.');
        }

        if ($type === AthleteEventMediaType::Video && $remaining['videos'] <= 0) {
            throw new MediaLimitReachedException('Alcanzaste el límite de videos incluidos para este evento.');
        }

        $disk = (string) config('finisher.event_media.disk', 'public');
        $checksum = (string) hash_file('sha256', $file->getRealPath());
        $basename = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin');
        $path = $file->storeAs("event-media/{$participant->id}", "{$basename}.{$extension}", $disk);

        [$width, $height] = $type === AthleteEventMediaType::Image ? $this->imageDimensions($file) : [null, null];

        $nextSort = (int) (AthleteEventMedia::query()->where('event_participant_id', $participant->id)->max('sort_order')) + 1;

        return AthleteEventMedia::create([
            'uuid' => (string) Str::uuid(),
            'athlete_id' => $athlete->id,
            'event_participant_id' => $participant->id,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'mime' => $mime,
            'size_bytes' => $file->getSize(),
            'checksum' => $checksum,
            'width' => $width,
            'height' => $height,
            'is_public' => $isPublic,
            'sort_order' => $nextSort,
        ]);
    }

    private function resolveType(string $mime): AthleteEventMediaType
    {
        return match (true) {
            in_array($mime, self::IMAGE_MIMES, true) => AthleteEventMediaType::Image,
            in_array($mime, self::VIDEO_MIMES, true) => AthleteEventMediaType::Video,
            default => throw ValidationException::withMessages(['file' => "Formato de archivo no soportado: {$mime}."]),
        };
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function imageDimensions(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        return $size ? [$size[0], $size[1]] : [null, null];
    }
}
