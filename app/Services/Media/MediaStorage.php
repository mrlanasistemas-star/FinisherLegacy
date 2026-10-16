<?php

namespace App\Services\Media;

use App\Contracts\Media\OwnsMediaFiles;
use App\Enums\MediaFileStatus;
use App\Enums\MediaKind;
use App\Exceptions\MediaTooLargeException;
use App\Jobs\Media\FinalizeMediaFile;
use App\Jobs\Media\ProcessMediaFile;
use App\Jobs\Media\TransferMediaFile;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * THE entry point every domain uses to store media (spec §65; API in
 * docs/SYNOLOGY.md). The request only validates and parks the upload on
 * the local temp disk; the queue does the heavy work:
 *
 *   ProcessMediaFile (variants / ffmpeg) → TransferMediaFile (to the media
 *   disk, i.e. the Synology) → FinalizeMediaFile (metadata, status, temp
 *   cleanup, owner sync).
 *
 *   $file = $storage->store($upload, $paths->product($product, 'images'),
 *       MediaUploadRules::images(), owner: $user, for: $productMedia);
 *   $file->status;          // processing → ready | pending_transcode | failed
 *   $file->url('card');     // signed URL once servable, else null
 */
class MediaStorage
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'webm',
        'audio/wav' => 'wav',
        'model/gltf-binary' => 'glb',
    ];

    public function __construct(private readonly MediaPathResolver $paths) {}

    public function paths(): MediaPathResolver
    {
        return $this->paths;
    }

    public function mediaDisk(): string
    {
        return (string) config('finisher.media_disk', 'local');
    }

    public function tmpDisk(): string
    {
        return (string) config('finisher.media.tmp_disk', 'media_tmp');
    }

    /** Temp working folder of one file (source + rendered outputs). */
    public function workDirectory(MediaFile $file): string
    {
        return 'incoming/'.$file->uuid;
    }

    /**
     * Validate + park on the temp disk + dispatch the pipeline.
     */
    public function store(UploadedFile $upload, string $directory, MediaUploadRules $rules, ?User $owner = null, ?Model $for = null): MediaFile
    {
        return $this->process($this->accept($upload, $directory, $rules, $owner), $for);
    }

    /**
     * Step 1 (inside the request, fast): sniff + validate, copy the upload
     * to the temp disk, create the `processing` MediaFile. Nothing is
     * queued yet — call process() once the owning row exists.
     *
     * @throws ValidationException|MediaTooLargeException
     */
    public function accept(UploadedFile $upload, string $directory, MediaUploadRules $rules, ?User $owner = null): MediaFile
    {
        $directory = $this->assertSafeDirectory($directory);

        if (! $upload->isValid()) {
            throw ValidationException::withMessages(['file' => 'No pudimos recibir el archivo. Intenta de nuevo.']);
        }

        // Symfony guesses from the bytes (fileinfo), not the client header.
        $mime = (string) $upload->getMimeType();
        $kind = $rules->kindFor($mime);

        if ($kind === null) {
            throw ValidationException::withMessages(['file' => 'Formato de archivo no soportado.']);
        }

        $size = (int) $upload->getSize();
        if ($size <= 0 || $size > $rules->maxBytesFor($kind)) {
            throw new MediaTooLargeException;
        }

        [$width, $height] = $kind === MediaKind::Image ? $this->validateImageDimensions($upload) : [null, null];

        $uuid = (string) Str::uuid();
        $extension = $this->extensionFor($mime, $upload);
        $tmpDisk = $this->tmpDisk();
        $sourcePath = Storage::disk($tmpDisk)->putFileAs('incoming/'.$uuid, $upload, 'source.'.$extension);

        if (! is_string($sourcePath)) {
            throw ValidationException::withMessages(['file' => 'No pudimos guardar el archivo. Intenta de nuevo.']);
        }

        return MediaFile::create([
            'uuid' => $uuid,
            'owner_user_id' => $owner?->id,
            'kind' => $kind,
            'status' => MediaFileStatus::Processing,
            'visibility' => $rules->visibility,
            'disk' => $this->mediaDisk(),
            'directory' => $directory,
            'mime' => $mime,
            'extension' => $extension,
            'size_bytes' => $size,
            'checksum' => (string) hash_file('sha256', (string) $upload->getRealPath()),
            'width' => $width,
            'height' => $height,
            'source_disk' => $tmpDisk,
            'source_path' => $sourcePath,
        ]);
    }

    /**
     * Step 2: attach the owning row (optional) and queue the pipeline. The
     * chain is dispatched after the surrounding DB transaction commits.
     */
    public function process(MediaFile $file, ?Model $for = null): MediaFile
    {
        if ($for !== null) {
            $file->mediable()->associate($for);
            $file->save();
        }

        $this->dispatchPipeline($file);

        return $file;
    }

    public function dispatchPipeline(MediaFile $file): void
    {
        $chain = Bus::chain([
            new ProcessMediaFile($file),
            new TransferMediaFile($file),
            new FinalizeMediaFile($file),
        ])->onQueue((string) config('finisher.media.queue', 'default'));

        $connection = config('finisher.media.connection');
        if (is_string($connection) && $connection !== '') {
            $chain->onConnection($connection);
        }

        $chain->dispatch();
    }

    /**
     * Pipeline end: flips the status, records where the retryable source
     * now lives, tells the owning row, and removes the temp folder.
     */
    public function finalize(MediaFile $file): MediaFile
    {
        $status = $file->kind === MediaKind::Video && ! $file->hasVariant('video')
            ? MediaFileStatus::PendingTranscode
            : MediaFileStatus::Ready;

        // A retried transcode that no longer keeps the original: drop it.
        if ($file->source_disk === $file->disk && $file->source_path !== null
            && $file->source_path !== $file->original_path && $file->source_path !== $file->path) {
            Storage::disk($file->disk)->delete($file->source_path);
        }

        $file->forceFill([
            'status' => $status,
            'source_disk' => $file->original_path !== null ? $file->disk : null,
            'source_path' => $file->original_path,
            'error' => null,
            'failed_at' => null,
            'processed_at' => now(),
        ])->save();

        Storage::disk($this->tmpDisk())->deleteDirectory($this->workDirectory($file));

        $owner = $file->mediable_type !== null ? $file->mediable : null;
        if ($owner instanceof OwnsMediaFiles) {
            $owner->syncFromMediaFile($file);
        }

        Log::info('media.processed', ['media_uuid' => $file->uuid, 'kind' => $file->kind->value, 'status' => $status->value]);

        return $file;
    }

    /**
     * Removes every stored rendition, the temp folder and the row.
     */
    public function delete(MediaFile $file): void
    {
        $paths = array_filter(array_unique(array_merge(
            [$file->path, $file->original_path, $file->poster_path],
            array_map(fn (array $variant) => $variant['path'], $file->variants ?? []),
        )));

        if ($paths !== []) {
            Storage::disk($file->disk)->delete(array_values($paths));
        }

        Storage::disk($this->tmpDisk())->deleteDirectory($this->workDirectory($file));
        $file->delete();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function validateImageDimensions(UploadedFile $upload): array
    {
        $size = @getimagesize((string) $upload->getRealPath());

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw ValidationException::withMessages(['file' => 'La imagen está dañada o no se puede leer.']);
        }

        $maxSide = (int) config('finisher.media.max_image_side', 12000);
        $maxPixels = (int) config('finisher.media.max_image_pixels', 50_000_000);

        if ($size[0] > $maxSide || $size[1] > $maxSide || $maxPixels < $size[0] * $size[1]) {
            throw ValidationException::withMessages(['file' => 'La imagen es demasiado grande en resolución.']);
        }

        return [$size[0], $size[1]];
    }

    private function extensionFor(string $mime, UploadedFile $upload): string
    {
        $extension = self::EXTENSIONS[$mime] ?? strtolower((string) $upload->guessExtension());

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? $extension : 'bin';
    }

    private function assertSafeDirectory(string $directory): string
    {
        $directory = rtrim($directory, '/');

        if ($directory === '' || preg_match('#^[a-z0-9][a-z0-9/_-]*$#', $directory) !== 1 || str_contains($directory, '//')) {
            throw new InvalidArgumentException('Media directories must come from MediaPathResolver.');
        }

        return $directory;
    }
}
