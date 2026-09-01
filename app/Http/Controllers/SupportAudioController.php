<?php

namespace App\Http\Controllers;

use App\Models\AthleteSupportMessage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Audio is never served from a public disk URL (product UX consolidation
 * brief §41) — only through this route, which requires a valid Laravel
 * signed-URL signature (see AthleteSupportMessage::signedAudioUrl()) that
 * expires. No session/bearer auth needed on top of that: the signature
 * itself is the authorization, which is what lets the manifest hand a
 * usable link to a future mobile app without a second auth round-trip.
 */
class SupportAudioController extends Controller
{
    public function show(AthleteSupportMessage $message): Response
    {
        abort_if($message->audio_disk === null || $message->audio_path === null, 404);

        $disk = Storage::disk($message->audio_disk);
        abort_unless($disk->exists($message->audio_path), 404);

        return response($disk->get($message->audio_path), 200, [
            'Content-Type' => $message->audio_mime ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
