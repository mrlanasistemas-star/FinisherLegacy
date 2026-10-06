<?php

namespace App\Actions\Photos;

use App\Enums\EventPhotoStatus;
use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Stores a batch of a photographer's event photos:
 *  - the untouched original on the PRIVATE `event_photo_originals` disk
 *    (delivered only to paying buyers);
 *  - a watermarked preview (~1400px) and thumbnail (~640px) on `public`.
 * Photos enter the review queue; an admin publishes them.
 */
class UploadEventPhotos
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $bibs  Bib numbers applied to every photo in the batch (editable per photo later).
     * @return list<EventPhoto>
     */
    public function handle(PhotographerProfile $photographer, EventEdition $edition, array $files, int $priceMinor, array $bibs = []): array
    {
        $manager = ImageManager::usingDriver(Driver::class);
        $config = config('finisher.photos');
        $created = [];

        foreach ($files as $file) {
            $uuid = (string) Str::uuid();
            $dir = "event-photos/{$edition->id}/{$photographer->id}";
            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $originalPath = Storage::disk('event_photo_originals')->putFileAs($dir, $file, "{$uuid}.{$extension}");

            try {
                $image = $manager->decodePath($file->getRealPath());
                $width = $image->width();
                $height = $image->height();

                $preview = $this->watermark($image->scaleDown(width: (int) $config['preview_max_width']));
                $previewPath = "{$dir}/preview/{$uuid}.jpg";
                Storage::disk('public')->put($previewPath, (string) $preview->encode(new JpegEncoder(quality: 78)));

                $thumb = $manager->decodePath($file->getRealPath())->scaleDown(width: (int) $config['thumb_max_width']);
                $thumbPath = "{$dir}/thumb/{$uuid}.jpg";
                Storage::disk('public')->put($thumbPath, (string) $this->watermark($thumb)->encode(new JpegEncoder(quality: 72)));

                $photo = DB::transaction(function () use ($uuid, $photographer, $edition, $originalPath, $previewPath, $thumbPath, $width, $height, $file, $priceMinor, $bibs) {
                    $photo = EventPhoto::create([
                        'uuid' => $uuid,
                        'photographer_profile_id' => $photographer->id,
                        'event_edition_id' => $edition->id,
                        'original_path' => $originalPath,
                        'preview_path' => $previewPath,
                        'thumb_path' => $thumbPath,
                        'width' => $width,
                        'height' => $height,
                        'size_bytes' => $file->getSize(),
                        'price_minor' => $priceMinor,
                        'currency' => config('finisher.photos.currency', 'MXN'),
                        'status' => EventPhotoStatus::Review,
                    ]);
                    $photo->syncBibs($bibs);

                    return $photo;
                });

                $created[] = $photo;
            } catch (Throwable $e) {
                Storage::disk('event_photo_originals')->delete($originalPath);

                throw $e;
            }
        }

        return $created;
    }

    /**
     * Diagonal "FINISHER LEGACY" tiles — visible enough to protect the
     * photo, light enough to judge it.
     */
    private function watermark(ImageInterface $image): ImageInterface
    {
        $font = resource_path('fonts/DejaVuSans-Bold.ttf');
        $size = max((int) round($image->width() / 22), 14);
        $stepX = (int) round($size * 11);
        $stepY = (int) round($size * 5);

        for ($y = -$stepY; $y < $image->height() + $stepY; $y += $stepY) {
            for ($x = -$stepX; $x < $image->width() + $stepX; $x += $stepX) {
                $offset = (int) (($y / max($stepY, 1)) % 2) * (int) ($stepX / 2);
                $image->text('FINISHER LEGACY', $x + $offset, $y, function ($f) use ($font, $size) {
                    $f->filename($font);
                    $f->size($size);
                    $f->color('rgba(255, 255, 255, 0.32)');
                    $f->angle(-24);
                });
            }
        }

        return $image;
    }
}
