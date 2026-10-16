<?php

namespace App\Http\Controllers;

use App\Models\AthleteEventMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * No event photo/video is ever served from a direct disk URL (product
 * consolidation brief §62: "no URLs directas eternas al NAS") — public
 * media (`is_public: true`) is served here unconditionally, since that's
 * what public means; private media additionally requires a valid
 * Laravel signed-URL signature (see AthleteEventMedia::url()), the same
 * pattern App\Http\Controllers\SupportAudioController already uses for
 * support audio.
 */
class AthleteEventMediaFileController extends Controller
{
    public function show(Request $request, AthleteEventMedia $media): Response
    {
        abort_unless($media->is_public || $request->hasValidSignature(), 403);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        // Streamed (videos can be large — never loaded whole into memory);
        // nosniff so a mislabeled upload is never re-interpreted as HTML.
        return $disk->response($media->path, null, [
            'Content-Type' => $media->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $media->is_public ? 'public, max-age=3600' : 'private, max-age=3600',
        ]);
    }
}
