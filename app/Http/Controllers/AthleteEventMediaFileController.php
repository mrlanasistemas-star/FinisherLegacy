<?php

namespace App\Http\Controllers;

use App\Models\AthleteEventMedia;
use App\Services\Media\MediaStreamResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No event photo/video is ever served from a direct disk URL (product
 * consolidation brief §62: "no URLs directas eternas al NAS") — public
 * media (`is_public: true`) is served here unconditionally, since that's
 * what public means; private media additionally requires a valid
 * Laravel signed-URL signature (see AthleteEventMedia::url()), the same
 * pattern App\Http\Controllers\SupportAudioController already uses for
 * support audio. Streams with Range support (video seeking).
 *
 * Rows uploaded through the media pipeline are linked to
 * MediaFileController instead; this keeps serving their primary file
 * for any old link.
 */
class AthleteEventMediaFileController extends Controller
{
    public function show(Request $request, AthleteEventMedia $media, MediaStreamResponder $responder): Response
    {
        abort_unless($media->is_public || $request->hasValidSignature(), 403);

        if ($media->mediaFile !== null) {
            abort_unless($media->mediaFile->isServable(), 404);
        }

        $target = $media->mediaFile?->resolveVariant(null);

        return $responder->respond(
            $request,
            $target !== null ? $media->mediaFile->disk : $media->disk,
            $target['path'] ?? $media->path,
            $target['mime'] ?? $media->mime,
            $media->is_public ? MediaStreamResponder::CACHE_PUBLIC : MediaStreamResponder::CACHE_PRIVATE,
            substr($media->checksum, 0, 32),
        );
    }
}
