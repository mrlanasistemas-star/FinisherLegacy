<?php

namespace App\Http\Controllers;

use App\Enums\MediaFileStatus;
use App\Models\MediaFile;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaStreamResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /media/f/{uuid}/{variant}` — the ONLY way a pipeline media file
 * leaves the media disk (spec §68: the Synology is never public).
 *
 * Access: a valid temporary signature (minted by MediaUrlGenerator for a
 * viewer who already passed the domain's listing rules) OR the
 * MediaFilePolicy for the session user. `original` additionally requires
 * the uploader/staff. While still processing, only the uploader gets a
 * no-store preview of the temp source; everyone else gets 404.
 */
class MediaFileController extends Controller
{
    public function show(Request $request, MediaFile $mediaFile, MediaStreamResponder $responder, MediaStorage $storage, string $variant = 'default'): Response
    {
        $user = $request->user();

        if ($variant === 'original') {
            abort_unless(Gate::forUser($user)->allows('viewOriginal', $mediaFile), 403);
        } elseif (! $this->hasUsableSignature($request, $mediaFile)) {
            abort_unless(Gate::forUser($user)->allows('view', $mediaFile), 403);
        }

        if (! $mediaFile->isServable()) {
            return $this->uploaderPreview($request, $mediaFile, $responder, $storage);
        }

        $target = $mediaFile->resolveVariant($variant);
        abort_if($target === null, 404);

        return $responder->respond(
            $request,
            $mediaFile->disk,
            $target['path'],
            $target['mime'],
            $mediaFile->isPublic() && $variant !== 'original' ? MediaStreamResponder::CACHE_PUBLIC : MediaStreamResponder::CACHE_PRIVATE,
            substr($mediaFile->checksum, 0, 32).'-'.$variant,
        );
    }

    /**
     * A long-lived public URL stops working as soon as the file is no
     * longer public; private URLs are short-lived by construction.
     */
    private function hasUsableSignature(Request $request, MediaFile $file): bool
    {
        if (! $request->hasValidRelativeSignature()) {
            return false;
        }

        return $request->query('scope') !== 'public' || $file->isPublic();
    }

    private function uploaderPreview(Request $request, MediaFile $file, MediaStreamResponder $responder, MediaStorage $storage): Response
    {
        $isUploader = Gate::forUser($request->user())->allows('viewOriginal', $file);

        abort_unless(
            $isUploader
            && $file->status === MediaFileStatus::Processing
            && $file->source_disk === $storage->tmpDisk()
            && $file->source_path !== null
            && Storage::disk($file->source_disk)->exists($file->source_path),
            404,
        );

        return $responder->respond($request, (string) $file->source_disk, (string) $file->source_path, $file->mime, MediaStreamResponder::CACHE_NONE);
    }
}
