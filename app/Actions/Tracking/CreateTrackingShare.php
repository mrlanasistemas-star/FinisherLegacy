<?php

namespace App\Actions\Tracking;

use App\Models\EventParticipant;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "Compartir seguimiento" (SPEC §81-§82). Returns the plaintext token
 * exactly once; only its SHA-256 is stored. Tokens are 48 random
 * alphanumeric chars (~285 bits), never derived from ids or names.
 *
 * Without a session the share follows the participation (the family can
 * get the link before the start); with one it is pinned to that session.
 */
class CreateTrackingShare
{
    /**
     * @return array{share: TrackingShare, token: string, url: string}
     */
    public function handle(EventParticipant $participant, ?User $createdBy, ?TrackingSession $session = null, ?int $ttlHours = null): array
    {
        $maxTtl = (int) config('tracking.share_max_ttl_hours', 336);
        $ttl = max(1, min($maxTtl, $ttlHours ?? (int) config('tracking.share_ttl_hours', 72)));
        $token = Str::random(48);

        $share = TrackingShare::create([
            'uuid' => (string) Str::uuid(),
            'event_participant_id' => $participant->id,
            'tracking_session_id' => $session?->id,
            'token_hash' => TrackingShare::hashToken($token),
            'channel_key' => Str::random(40),
            'expires_at' => now()->addHours($ttl),
            'created_by' => $createdBy?->id,
        ]);

        return ['share' => $share, 'token' => $token, 'url' => route('live.show', ['token' => $token])];
    }
}
